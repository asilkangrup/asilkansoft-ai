<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;

class RealEstateMediaSafetyService
{
    public const MAX_IMAGE_BYTES = 12 * 1024 * 1024;
    public const MAX_PDF_BYTES = 20 * 1024 * 1024;

    /** @var array<string, int> */
    private const ALLOWED_MIME_LIMITS = [
        'image/jpeg' => self::MAX_IMAGE_BYTES,
        'image/png' => self::MAX_IMAGE_BYTES,
        'image/webp' => self::MAX_IMAGE_BYTES,
        'application/pdf' => self::MAX_PDF_BYTES,
    ];

    public function __construct(
        private readonly RealEstateIsolationService $isolation,
    ) {
    }

    /**
     * @return array{
     *   allowed:bool,
     *   reason:?string,
     *   source_type:string,
     *   mime_type:?string,
     *   max_bytes:?int,
     *   declared_bytes:?int,
     *   message_id_hash:?string
     * }
     */
    public function preflight(
        ConversationControl $conversation,
        AiBot $bot,
        string $instance,
        array $mediaContext,
    ): array {
        $type = strtolower(trim((string) ($mediaContext['type'] ?? '')));
        $mime = $this->normalizeMime((string) ($mediaContext['mime_type'] ?? ''));
        $declaredBytes = $this->nullablePositiveInt($mediaContext['size'] ?? null);
        $messageId = trim((string) ($mediaContext['message_id'] ?? ''));
        $messageHash = $messageId !== '' ? hash('sha256', $messageId) : null;
        $sourceType = $type === 'document' ? 'document' : ($type === 'image' ? 'image' : 'unknown');

        if (
            ! $this->isolation->supportsConversation($conversation)
            || ! $this->isolation->supportsProductionBot($bot)
            || $instance !== RealEstateIsolationService::INSTANCE
        ) {
            return $this->result(
                allowed: false,
                reason: 'scope_rejected',
                sourceType: $sourceType,
                mime: $mime,
                maxBytes: null,
                declaredBytes: $declaredBytes,
                messageHash: $messageHash,
            );
        }

        if ($messageId === '') {
            return $this->result(false, 'missing_message_id', $sourceType, $mime, null, $declaredBytes, null);
        }

        if (! is_array($mediaContext['message_envelope'] ?? null) || ($mediaContext['message_envelope'] ?? []) === []) {
            return $this->result(false, 'missing_envelope', $sourceType, $mime, null, $declaredBytes, $messageHash);
        }

        $maxBytes = self::ALLOWED_MIME_LIMITS[$mime] ?? null;

        if ($maxBytes === null) {
            return $this->result(false, 'unsupported_mime', $sourceType, $mime, null, $declaredBytes, $messageHash);
        }

        if ($type === 'image' && ! str_starts_with($mime, 'image/')) {
            return $this->result(false, 'type_mime_mismatch', $sourceType, $mime, $maxBytes, $declaredBytes, $messageHash);
        }

        if ($type === 'document' && $mime !== 'application/pdf') {
            return $this->result(false, 'type_mime_mismatch', $sourceType, $mime, $maxBytes, $declaredBytes, $messageHash);
        }

        if (! in_array($type, ['image', 'document'], true)) {
            return $this->result(false, 'unsupported_type', $sourceType, $mime, $maxBytes, $declaredBytes, $messageHash);
        }

        if ($declaredBytes !== null && $declaredBytes > $maxBytes) {
            return $this->result(false, 'declared_size_exceeded', $sourceType, $mime, $maxBytes, $declaredBytes, $messageHash);
        }

        return $this->result(true, null, $sourceType, $mime, $maxBytes, $declaredBytes, $messageHash);
    }

    public function sanitizeUntrustedMetadata(mixed $value, int $maxLength = 500): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\r\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', '[redacted-email]', $text) ?? $text;
        $text = preg_replace('/(?<!\d)(?:\+?90\s*)?(?:0?5\d{2})(?:[\s().-]*\d){7}(?!\d)/u', '[redacted-phone]', $text) ?? $text;
        $text = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);

        return mb_substr($text, 0, max(1, min(2000, $maxLength)));
    }

    public function normalizeMime(string $mime): string
    {
        return strtolower(trim(explode(';', $mime, 2)[0] ?? ''));
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function result(
        bool $allowed,
        ?string $reason,
        string $sourceType,
        ?string $mime,
        ?int $maxBytes,
        ?int $declaredBytes,
        ?string $messageHash,
    ): array {
        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'source_type' => $sourceType,
            'mime_type' => $mime !== '' ? $mime : null,
            'max_bytes' => $maxBytes,
            'declared_bytes' => $declaredBytes,
            'message_id_hash' => $messageHash,
        ];
    }
}
