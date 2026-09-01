<?php

namespace App\Observers;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Services\RealEstateMediaAnalysisService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatMessageObserver
{
    public function created(ChatMessage $message): void
    {
        if (
            (int) $message->user_id !== 1
            || $message->role !== 'user'
            || $message->sender_type !== 'customer'
            || ! $message->isMedia()
            || blank($message->whatsapp_message_id)
        ) {
            return;
        }

        $type = strtolower((string) ($message->message_type ?: 'text'));
        $mime = strtolower((string) ($message->media_mime_type ?: ''));

        if (
            $type !== 'image'
            && ! ($type === 'document' && $mime === 'application/pdf')
        ) {
            return;
        }

        try {
            $conversation = ConversationControl::query()
                ->where('user_id', $message->user_id)
                ->where('session_id', $message->session_id)
                ->first();

            $aiBot = AiBot::query()->find($message->ai_bot_id);

            if (! $conversation || ! $aiBot || blank($aiBot->whatsapp_instance)) {
                return;
            }

            app(RealEstateMediaAnalysisService::class)->process(
                conversation: $conversation,
                instanceName: (string) $aiBot->whatsapp_instance,
                mediaContext: [
                    'type' => $type,
                    'url' => $message->media_url,
                    'mime_type' => $message->media_mime_type,
                    'filename' => $message->media_filename,
                    'caption' => $message->media_caption,
                    'message_id' => $message->whatsapp_message_id,
                    'message_envelope' => [
                        'key' => [
                            'id' => $message->whatsapp_message_id,
                        ],
                    ],
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE MEDIA OBSERVER FAILED', [
                'chat_message_id' => $message->id,
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }
}
