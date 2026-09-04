<?php

namespace App\Observers;

use App\Jobs\RegisterRealEstateMediaCrmFinding;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatMessageObserver
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_ORGANIZATION_ID = 37;
    private const REAL_ESTATE_BOT_ID = 35;

    private const WAI_SALES_USER_ID = 43;
    private const WAI_SALES_BOT_ID = 39;

    public function created(ChatMessage $message): void
    {
        if ($this->removeWaiSalesAssistantDuplicate($message)) {
            return;
        }

        if (
            (int) $message->user_id !== self::REAL_ESTATE_USER_ID
            || (int) $message->organization_id !== self::REAL_ESTATE_ORGANIZATION_ID
            || (int) $message->ai_bot_id !== self::REAL_ESTATE_BOT_ID
            || $message->role !== 'user'
            || $message->sender_type !== 'customer'
            || ! $message->isMedia()
            || blank($message->whatsapp_message_id)
        ) {
            return;
        }

        $this->removeLegacyPlaceholderDuplicate($message);

        $type = strtolower((string) ($message->message_type ?: 'text'));
        $mime = strtolower((string) ($message->media_mime_type ?: ''));

        if (
            $type !== 'image'
            && ! ($type === 'document' && $mime === 'application/pdf')
        ) {
            return;
        }

        $conversationId = ConversationControl::query()
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->where('session_id', $message->session_id)
            ->value('id');

        $sellerProfileExists = $conversationId !== null
            && RealEstateProfile::query()
                ->isolatedProduction()
                ->where('conversation_control_id', $conversationId)
                ->where('profile_type', 'seller')
                ->exists();

        if ($sellerProfileExists) {
            RegisterRealEstateMediaCrmFinding::dispatchSync($message->id);

            return;
        }

        RegisterRealEstateMediaCrmFinding::dispatch($message->id)
            ->delay(now()->addSeconds(2));
    }

    private function removeWaiSalesAssistantDuplicate(ChatMessage $message): bool
    {
        if (
            (int) $message->user_id !== self::WAI_SALES_USER_ID
            || (int) $message->ai_bot_id !== self::WAI_SALES_BOT_ID
            || $message->role !== 'assistant'
            || $message->sender_type !== 'ai'
        ) {
            return false;
        }

        try {
            $previous = ChatMessage::query()
                ->where('user_id', self::WAI_SALES_USER_ID)
                ->where('ai_bot_id', self::WAI_SALES_BOT_ID)
                ->where('session_id', $message->session_id)
                ->where('role', 'assistant')
                ->where('sender_type', 'ai')
                ->where('id', '<', $message->id)
                ->latest('id')
                ->first();

            if (! $previous) {
                return false;
            }

            $sameText = trim((string) $previous->message) === trim((string) $message->message);
            $sameWindow = $previous->created_at
                && $message->created_at
                && abs($previous->created_at->diffInSeconds($message->created_at, false)) <= 15;

            if (! $sameText || ! $sameWindow) {
                return false;
            }

            $message->deleteQuietly();

            Log::info('WAI SALES DUPLICATE ASSISTANT MESSAGE REMOVED', [
                'kept_chat_message_id' => $previous->id,
                'removed_chat_message_id' => $message->id,
                'session_id' => $message->session_id,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('WAI SALES DUPLICATE ASSISTANT CLEANUP FAILED', [
                'chat_message_id' => $message->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function removeLegacyPlaceholderDuplicate(ChatMessage $mediaMessage): void
    {
        try {
            $previous = ChatMessage::query()
                ->where('user_id', self::REAL_ESTATE_USER_ID)
                ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
                ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                ->where('session_id', $mediaMessage->session_id)
                ->where('id', '<', $mediaMessage->id)
                ->latest('id')
                ->first();

            if (! $previous) {
                return;
            }

            $sameLogicalMessage = $previous->role === 'user'
                && $previous->sender_type === 'customer'
                && (string) $previous->message_type === 'text'
                && blank($previous->whatsapp_message_id)
                && trim((string) $previous->message) === trim((string) $mediaMessage->message);

            if (! $sameLogicalMessage) {
                return;
            }

            $withinSameWebhookWindow = $previous->created_at
                && $mediaMessage->created_at
                && abs($previous->created_at->diffInSeconds($mediaMessage->created_at, false)) <= 15;

            if (! $withinSameWebhookWindow) {
                return;
            }

            $previous->deleteQuietly();

            Log::info('REAL ESTATE DUPLICATE MEDIA PLACEHOLDER REMOVED', [
                'removed_chat_message_id' => $previous->id,
                'media_chat_message_id' => $mediaMessage->id,
                'session_id' => $mediaMessage->session_id,
            ]);
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE MEDIA PLACEHOLDER CLEANUP FAILED', [
                'chat_message_id' => $mediaMessage->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
