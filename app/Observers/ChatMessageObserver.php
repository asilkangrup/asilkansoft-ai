<?php

namespace App\Observers;

use App\Jobs\RegisterRealEstateMediaCrmFinding;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatMessageObserver
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_ORGANIZATION_ID = 37;
    private const REAL_ESTATE_BOT_ID = 35;

    public function created(ChatMessage $message): void
    {
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

        // Production is intentionally CRM-only for inbound media. The
        // full-resolution file is already persisted by the isolated inbound
        // service. Never invoke vision/OCR from a model observer: it creates
        // hidden token spend and can promote unverified image text into CRM
        // facts. This delayed job only makes the saved file visible in the
        // seller gallery and marks it as awaiting written confirmation.
        RegisterRealEstateMediaCrmFinding::dispatch($message->id)
            ->delay(now()->addSeconds(2));
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
