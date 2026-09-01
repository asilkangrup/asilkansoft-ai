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
        ?int $sentByUserId = null
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
        ]);

        if (
            $userId === 1
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

        if ($userId === 1) {
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
