<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MemoryService
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_ORGANIZATION_ID = 37;
    private const REAL_ESTATE_BOT_ID = 35;

    public function yeniOturumId(): string
    {
        return (string) Str::uuid();
    }

    public function mesajKaydet(
        int $userId,
        ?int $aiBotId,
        string $sessionId,
        string $role,
        string $message,
        ?string $senderType = null,
        ?int $sentByUserId = null,
        array $mediaContext = []
    ): ChatMessage {
        if ($senderType === null) {
            $senderType = $role === 'assistant' ? 'ai' : 'customer';
        }

        $isolatedRealEstate = $userId === self::REAL_ESTATE_USER_ID
            && (int) $aiBotId === self::REAL_ESTATE_BOT_ID;

        if ($isolatedRealEstate) {
            // Never let the fresh Emlak AI memory fall through to another
            // organization owned by user 40.
            $organizationId = self::REAL_ESTATE_ORGANIZATION_ID;
        } else {
            $organizationId = ConversationControl::query()
                ->where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->value('organization_id');

            if ($organizationId === null) {
                $organizationId = Organization::query()
                    ->where('owner_user_id', $userId)
                    ->value('id');
            }
        }

        $attributes = [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'ai_bot_id' => $aiBotId,
            'session_id' => $sessionId,
            'role' => $role,
            'sender_type' => $senderType,
            'sent_by_user_id' => $sentByUserId,
            'message' => trim($message),
            'message_type' => $mediaContext['type'] ?? 'text',
            'media_url' => $mediaContext['url'] ?? null,
            'media_mime_type' => $mediaContext['mime_type'] ?? null,
            'media_filename' => $mediaContext['filename'] ?? null,
            'media_caption' => $mediaContext['caption'] ?? null,
            'media_transcript' => $mediaContext['transcript'] ?? null,
            'media_transcription_status' => $mediaContext['transcription_status'] ?? null,
            'media_transcription_model' => $mediaContext['transcription_model'] ?? null,
            'media_transcription_language' => $mediaContext['transcription_language'] ?? null,
            'media_transcribed_at' => $mediaContext['transcribed_at'] ?? null,
            'media_duration' => $mediaContext['duration'] ?? null,
            'media_size' => $mediaContext['size'] ?? null,
            'whatsapp_message_id' => $mediaContext['message_id'] ?? null,
            'status' => $senderType === 'customer'
                ? 'received'
                : ($isolatedRealEstate ? 'sent' : null),
        ];

        $isolatedInboundMessageId = $isolatedRealEstate
            && $role === 'user'
            && $senderType === 'customer'
            && filled($mediaContext['message_id'] ?? null)
                ? trim((string) $mediaContext['message_id'])
                : '';

        if ($isolatedInboundMessageId !== '') {
            $chatMessage = ChatMessage::query()->firstOrCreate(
                [
                    'user_id' => self::REAL_ESTATE_USER_ID,
                    'organization_id' => self::REAL_ESTATE_ORGANIZATION_ID,
                    'ai_bot_id' => self::REAL_ESTATE_BOT_ID,
                    'whatsapp_message_id' => $isolatedInboundMessageId,
                ],
                $attributes
            );
        } else {
            $chatMessage = ChatMessage::create($attributes);
        }

        if (
            $isolatedRealEstate
            && (int) $organizationId === self::REAL_ESTATE_ORGANIZATION_ID
            && $role === 'user'
            && $senderType === 'customer'
            && $chatMessage->wasRecentlyCreated
        ) {
            try {
                $conversation = ConversationControl::query()
                    ->where('user_id', self::REAL_ESTATE_USER_ID)
                    ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
                    ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                    ->where('session_id', $sessionId)
                    ->first();

                if ($conversation) {
                    app(RealEstateConversationService::class)->route(
                        conversation: $conversation,
                        message: $message,
                    );

                    $profile = app(RealEstateProfileService::class)->process(
                        conversation: $conversation,
                        message: $message,
                    );

                    if ($profile) {
                        app(RealEstateNegotiationMemoryService::class)->sync(
                            profile: $profile,
                            sourceMessage: $chatMessage,
                        );

                        app(RealEstateValuationFreshnessService::class)
                            ->refreshMetadata($profile);
                    }

                    if (
                        $mediaContext !== []
                        && filled($mediaContext['instance_name'] ?? null)
                    ) {
                        app(RealEstateMediaAnalysisService::class)->process(
                            conversation: $conversation,
                            instanceName: (string) $mediaContext['instance_name'],
                            mediaContext: $mediaContext,
                        );
                    }

                    app(RealEstateEvidenceReconciliationService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateVerificationService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateValuationService::class)->process(
                        conversation: $conversation,
                        message: $message,
                    );

                    app(RealEstateDecisionService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateValuationDecisionGuardService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateVerificationDecisionGuardService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateMatchService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateMatchValuationFreshnessFilterService::class)->process(
                        conversation: $conversation,
                    );

                    app(RealEstateMatchVerificationFilterService::class)->process(
                        conversation: $conversation,
                    );
                }
            } catch (Throwable $exception) {
                Log::warning('REAL ESTATE MEMORY PIPELINE FAILED', [
                    'user_id' => $userId,
                    'organization_id' => $organizationId,
                    'ai_bot_id' => $aiBotId,
                    'session_id' => $sessionId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $chatMessage;
    }

    public function gecmisiGetir(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): Collection {
        $query = ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId);

        $this->applyIsolatedRealEstateMessageScope(
            query: $query,
            userId: $userId,
            sessionId: $sessionId,
        );

        return $query
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function openAIMesajlariHazirla(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): array {
        $messages = $this
            ->gecmisiGetir($userId, $sessionId, $limit)
            ->map(fn (ChatMessage $mesaj): array => [
                'role' => $mesaj->role,
                'content' => $mesaj->message,
            ])
            ->all();

        if ($userId === self::REAL_ESTATE_USER_ID) {
            try {
                $conversation = ConversationControl::query()
                    ->where('user_id', self::REAL_ESTATE_USER_ID)
                    ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
                    ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                    ->where('session_id', $sessionId)
                    ->first();

                if ($conversation) {
                    $internalContext = collect([
                        trim(app(RealEstateConversationService::class)->promptFor($conversation)),
                        trim(app(RealEstateProfileService::class)->promptFor($conversation)),
                        trim(app(RealEstateNegotiationMemoryService::class)->promptFor($conversation)),
                        trim(app(RealEstateEvidenceReconciliationService::class)->promptFor($conversation)),
                        trim(app(RealEstateVerificationService::class)->promptFor($conversation)),
                        trim(app(RealEstateValuationService::class)->promptFor($conversation)),
                        trim(app(RealEstateDecisionService::class)->promptFor($conversation)),
                        trim(app(RealEstateMatchService::class)->promptFor($conversation)),
                    ])
                        ->filter()
                        ->implode("\n\n");

                    if ($internalContext !== '') {
                        $contextMessage = [
                            'role' => 'assistant',
                            'content' => $internalContext,
                        ];

                        $insertAt = max(0, count($messages) - 1);
                        array_splice($messages, $insertAt, 0, [$contextMessage]);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('REAL ESTATE INTERNAL MEMORY PROMPT FAILED', [
                    'user_id' => $userId,
                    'organization_id' => self::REAL_ESTATE_ORGANIZATION_ID,
                    'ai_bot_id' => self::REAL_ESTATE_BOT_ID,
                    'session_id' => $sessionId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $messages;
    }

    public function sohbetiTemizle(
        int $userId,
        string $sessionId
    ): void {
        $query = ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId);

        $this->applyIsolatedRealEstateMessageScope(
            query: $query,
            userId: $userId,
            sessionId: $sessionId,
        );

        $query->delete();
    }

    private function applyIsolatedRealEstateMessageScope(
        Builder $query,
        int $userId,
        string $sessionId,
    ): void {
        if (! $this->isIsolatedRealEstateSession($userId, $sessionId)) {
            return;
        }

        $query
            ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID);
    }

    private function isIsolatedRealEstateSession(
        int $userId,
        string $sessionId,
    ): bool {
        if ($userId !== self::REAL_ESTATE_USER_ID) {
            return false;
        }

        if (str_starts_with($sessionId, 'whatsapp:'.self::REAL_ESTATE_BOT_ID.':')) {
            return true;
        }

        return ConversationControl::query()
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->where('session_id', $sessionId)
            ->exists();
    }
}
