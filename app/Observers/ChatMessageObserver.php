<?php

namespace App\Observers;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Services\RealEstateDecisionService;
use App\Services\RealEstateEvidenceReconciliationService;
use App\Services\RealEstateMatchService;
use App\Services\RealEstateMatchValuationFreshnessFilterService;
use App\Services\RealEstateMatchVerificationFilterService;
use App\Services\RealEstateMediaAnalysisService;
use App\Services\RealEstateValuationDecisionGuardService;
use App\Services\RealEstateVerificationDecisionGuardService;
use App\Services\RealEstateVerificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatMessageObserver
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_ORGANIZATION_ID = 37;
    private const REAL_ESTATE_BOT_ID = 35;
    private const REAL_ESTATE_INSTANCE = 'emlak-ai-35';

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

        try {
            $conversation = ConversationControl::query()
                ->where('user_id', self::REAL_ESTATE_USER_ID)
                ->where('organization_id', self::REAL_ESTATE_ORGANIZATION_ID)
                ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                ->where('session_id', $message->session_id)
                ->first();

            $aiBot = AiBot::query()
                ->whereKey(self::REAL_ESTATE_BOT_ID)
                ->where('user_id', self::REAL_ESTATE_USER_ID)
                ->where('whatsapp_instance', self::REAL_ESTATE_INSTANCE)
                ->first();

            if (! $conversation || ! $aiBot) {
                return;
            }

            $analysis = app(RealEstateMediaAnalysisService::class)->process(
                conversation: $conversation,
                instanceName: self::REAL_ESTATE_INSTANCE,
                mediaContext: [
                    'type' => $type,
                    'url' => $message->media_url,
                    'mime_type' => $message->media_mime_type,
                    'filename' => $message->media_filename,
                    'caption' => $message->media_caption,
                    'message_id' => $message->whatsapp_message_id,
                    'message_envelope' => [
                        'key' => ['id' => $message->whatsapp_message_id],
                    ],
                ],
            );

            if (! is_array($analysis)) {
                return;
            }

            // A media row can also be created outside MemoryService (imports,
            // repairs, future queues). Reconciliation therefore belongs here
            // as well: canonical CRM memory and provenance must be updated
            // before verification, decisions, valuation guards and matching.
            app(RealEstateEvidenceReconciliationService::class)->process(
                conversation: $conversation,
            );

            app(RealEstateVerificationService::class)->process($conversation);
            app(RealEstateDecisionService::class)->process($conversation);
            app(RealEstateValuationDecisionGuardService::class)->process($conversation);
            app(RealEstateVerificationDecisionGuardService::class)->process($conversation);
            app(RealEstateMatchService::class)->process($conversation);
            app(RealEstateMatchValuationFreshnessFilterService::class)->process($conversation);
            app(RealEstateMatchVerificationFilterService::class)->process($conversation);
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE MEDIA OBSERVER FAILED', [
                'chat_message_id' => $message->id,
                'message' => $exception->getMessage(),
            ]);
            report($exception);
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
