<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateMediaProcessingEvent;
use Illuminate\Support\Facades\Schema;

class RealEstateMediaProcessingLedgerService
{
    private const ANALYSIS_VERSION = 'v1';

    public function record(
        ConversationControl $conversation,
        array $mediaContext,
        string $outcome,
        ?string $reason = null,
        ?int $actualBytes = null,
        ?string $contentHash = null,
    ): ?RealEstateMediaProcessingEvent {
        if (! Schema::hasTable('real_estate_media_processing_events')) {
            return null;
        }

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $messageId = trim((string) ($mediaContext['message_id'] ?? ''));

        if ($messageId === '') {
            return null;
        }

        $type = strtolower(trim((string) ($mediaContext['type'] ?? '')));
        $sourceType = in_array($type, ['image', 'document'], true) ? $type : 'unknown';
        $mime = app(RealEstateMediaSafetyService::class)->normalizeMime(
            (string) ($mediaContext['mime_type'] ?? '')
        );
        $declaredBytes = $this->nullablePositiveInt($mediaContext['size'] ?? null);
        $outcome = $this->safeToken($outcome, 48) ?? 'unknown';
        $reason = $this->safeToken($reason, 80);
        $messageHash = hash('sha256', $messageId);
        $eventKey = hash('sha256', implode('|', [
            RealEstateIsolationService::USER_ID,
            RealEstateIsolationService::ORGANIZATION_ID,
            RealEstateIsolationService::BOT_ID,
            (int) $conversation->id,
            $messageHash,
            $outcome,
            $reason ?? '',
            self::ANALYSIS_VERSION,
        ]));

        return RealEstateMediaProcessingEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'conversation_control_id' => $conversation->id,
                'message_id_hash' => $messageHash,
                'source_type' => $sourceType,
                'mime_type' => $this->safeMime($mime),
                'outcome' => $outcome,
                'reason' => $reason,
                'declared_bytes' => $declaredBytes,
                'actual_bytes' => $actualBytes !== null && $actualBytes >= 0 ? $actualBytes : null,
                'content_hash' => $this->safeHash($contentHash),
                'analysis_version' => self::ANALYSIS_VERSION,
                'recorded_at' => now(),
            ]
        );
    }

    public function telemetry24h(): array
    {
        if (! Schema::hasTable('real_estate_media_processing_events')) {
            return $this->emptyTelemetry();
        }

        $base = RealEstateMediaProcessingEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('recorded_at', '>=', now()->subDay());

        return [
            'events' => (clone $base)->count(),
            'analyzed' => (clone $base)->where('outcome', 'analyzed')->count(),
            'rejected' => (clone $base)->where('outcome', 'rejected')->count(),
            'failed' => (clone $base)->where('outcome', 'failed')->count(),
            'unsupported_mime' => (clone $base)->where('reason', 'unsupported_mime')->count(),
            'size_exceeded' => (clone $base)->whereIn('reason', [
                'declared_size_exceeded',
                'actual_size_exceeded',
            ])->count(),
            'unique_conversations' => (clone $base)
                ->distinct('conversation_control_id')
                ->count('conversation_control_id'),
        ];
    }

    public function emptyTelemetry(): array
    {
        return [
            'events' => 0,
            'analyzed' => 0,
            'rejected' => 0,
            'failed' => 0,
            'unsupported_mime' => 0,
            'size_exceeded' => 0,
            'unique_conversations' => 0,
        ];
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function safeToken(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '' || preg_match('/^[a-z0-9_.:-]+$/', $value) !== 1) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function safeMime(string $mime): ?string
    {
        if ($mime === '') {
            return null;
        }

        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime) === 1
            ? mb_substr($mime, 0, 120)
            : null;
    }

    private function safeHash(?string $hash): ?string
    {
        if ($hash === null) {
            return null;
        }

        $hash = strtolower(trim($hash));

        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1 ? $hash : null;
    }
}
