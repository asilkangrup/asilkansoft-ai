<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MemoryService
{
    private const REAL_ESTATE_USER_ID = 40;

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
            $senderType =
                $role === 'assistant'
                    ? 'ai'
                    : 'customer';
        }

        $organizationId =
            ConversationControl::query()
                ->where('user_id', $userId)
                ->where('session_id', $sessionId)
                ->value('organization_id');

        if ($organizationId === null) {
            $organizationId =
                Organization::query()
                    ->where('owner_user_id', $userId)
                    ->value('id');
        }

        $chatMessage = ChatMessage::create([
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
            'whatsapp_message_id' => $mediaContext['message_id'] ?? null,
            'status' => $senderType === 'customer' ? 'received' : null,
        ]);

        if (
            $userId === self::REAL_ESTATE_USER_ID
            && $role === 'user'
            && $senderType === 'customer'
        ) {
            try {
                $conversation =
                    ConversationControl::query()
                        ->where('user_id', $userId)
                        ->where('session_id', $sessionId)
                        ->first();

                if ($conversation) {
                    app(RealEstateConversationService::class)->route(
                        conversation: $conversation,
                        message: $message,
                    );

                    app(RealEstateProfileService::class)->process(
                        conversation: $conversation,
                        message: $message,
                    );

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

                    app(RealEstateValuationService::class)->process(
                        conversation: $conversation,
                        message: $message,
                    );
                }
            } catch (Throwable $exception) {
                Log::warning(
                    'REAL ESTATE MEMORY PIPELINE FAILED',
                    [
                        'user_id' => $userId,
                        'ai_bot_id' => $aiBotId,
                        'session_id' => $sessionId,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $chatMessage;
    }

    public function gecmisiGetir(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): Collection {
        return ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
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
            ->gecmisiGetir(
                $userId,
                $sessionId,
                $limit
            )
            ->map(
                fn (ChatMessage $mesaj): array => [
                    'role' => $mesaj->role,
                    'content' => $mesaj->message,
                ]
            )
            ->all();

        if ($userId === self::REAL_ESTATE_USER_ID) {
            try {
                $conversation =
                    ConversationControl::query()
                        ->where('user_id', $userId)
                        ->where('session_id', $sessionId)
                        ->first();

                if ($conversation) {
                    $internalContext = collect([
                        trim(
                            app(RealEstateConversationService::class)
                                ->promptFor($conversation)
                        ),
                        trim(
                            app(RealEstateProfileService::class)
                                ->promptFor($conversation)
                        ),
                        trim(
                            app(RealEstateValuationService::class)
                                ->promptFor($conversation)
                        ),
                    ])
                        ->filter()
                        ->implode("\n\n");

                    if ($internalContext !== '') {
                        $contextMessage = [
                            'role' => 'assistant',
                            'content' => $internalContext,
                        ];

                        $insertAt = max(0, count($messages) - 1);

                        array_splice(
                            $messages,
                            $insertAt,
                            0,
                            [$contextMessage]
                        );
                    }
                }
            } catch (Throwable $exception) {
                Log::warning(
                    'REAL ESTATE INTERNAL MEMORY PROMPT FAILED',
                    [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $messages;
    }

    public function sohbetiTemizle(
        int $userId,
        string $sessionId
    ): void {
        ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->delete();
    }
}
