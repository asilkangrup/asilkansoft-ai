<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateWebhookReceipt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateInboundRecoveryService
{
    private const STALE_PROCESSING_MINUTES = 5;

    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly MemoryService $memoryService,
        private readonly RealEstateOpenAIService $openAIService,
        private readonly RealEstateWebhookReceiptService $receiptService,
        private readonly RealEstateOutboundDeliveryService $outboundDeliveryService,
    ) {
    }

    /**
     * Recover only genuine replies to already-received customer messages.
     * This is not a follow-up scheduler: it never starts a conversation and
     * never sends when a newer customer turn or an operator/AI reply exists.
     *
     * @return array<string,int>
     */
    public function recover(int $limit = 5, int $failedAgeSeconds = 90): array
    {
        $limit = max(1, min(20, $limit));
        $failedAgeSeconds = max(60, min(900, $failedAgeSeconds));
        $failedBefore = now()->subSeconds($failedAgeSeconds);
        $staleProcessingBefore = now()->subMinutes(self::STALE_PROCESSING_MINUTES);

        $receipts = RealEstateWebhookReceipt::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->where('event', 'messages.upsert')
            ->where(function ($query) use ($failedBefore, $staleProcessingBefore): void {
                $query
                    ->where(function ($failed) use ($failedBefore): void {
                        $failed
                            ->where('status', 'failed')
                            ->whereNotNull('processed_at')
                            ->where('processed_at', '<=', $failedBefore);
                    })
                    ->orWhere(function ($processing) use ($staleProcessingBefore): void {
                        $processing
                            ->where('status', 'processing')
                            ->whereNotNull('processing_started_at')
                            ->where('processing_started_at', '<=', $staleProcessingBefore);
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $stats = [
            'candidates' => $receipts->count(),
            'replied' => 0,
            'already_answered' => 0,
            'superseded' => 0,
            'busy' => 0,
            'skipped' => 0,
            'uncertain' => 0,
            'failed' => 0,
        ];

        foreach ($receipts as $receipt) {
            $outcome = $this->recoverReceipt($receipt);

            if (array_key_exists($outcome, $stats)) {
                $stats[$outcome]++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    private function recoverReceipt(RealEstateWebhookReceipt $receipt): string
    {
        $message = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->where('whatsapp_message_id', $receipt->whatsapp_message_id)
            ->first();

        if (! $message) {
            return 'skipped';
        }

        $latestCustomerId = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $message->session_id)
            ->where('sender_type', 'customer')
            ->max('id');

        if ((int) $latestCustomerId !== (int) $message->id) {
            $this->closeIgnored($receipt, 'superseded_by_newer_customer_message');

            return 'superseded';
        }

        $replyExists = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $message->session_id)
            ->where('id', '>', $message->id)
            ->whereIn('sender_type', ['ai', 'human'])
            ->exists();

        if ($replyExists) {
            $this->closeIgnored($receipt, 'reply_already_exists');

            return 'already_answered';
        }

        $receiptState = $this->receiptService->begin(
            instance: RealEstateIsolationService::INSTANCE,
            event: 'messages.upsert',
            messageId: (string) $receipt->whatsapp_message_id,
            phoneNumber: $receipt->phone_number,
        );

        if (! (bool) ($receiptState['should_process'] ?? false)) {
            return 'busy';
        }

        $activeReceipt = $receiptState['receipt'] ?? $receipt;

        try {
            $bot = AiBot::query()
                ->whereKey(RealEstateIsolationService::BOT_ID)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('business_sector', 'real_estate')
                ->where('whatsapp_instance', RealEstateIsolationService::INSTANCE)
                ->first();

            if (
                ! $this->isolation->supportsProductionBot($bot)
                || ! $bot->whatsappAiKullanilabilirMi()
                || (bool) $bot->follow_up_enabled
                || (bool) $bot->second_follow_up_enabled
            ) {
                $this->receiptService->fail(
                    $activeReceipt,
                    new \RuntimeException('İzole Emlak AI recovery güvenlik koşulları sağlanmıyor.')
                );

                return 'failed';
            }

            $conversation = ConversationControl::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('session_id', $message->session_id)
                ->first();

            if (
                ! $conversation
                || ! $this->isolation->supportsConversation($conversation)
                || (bool) $conversation->human_takeover
            ) {
                $this->closeIgnored($activeReceipt, 'conversation_not_ai_eligible');

                return 'skipped';
            }

            $phoneNumber = preg_replace('/\D+/', '', (string) $conversation->whatsapp_number) ?? '';

            if ($phoneNumber === '') {
                throw new \RuntimeException('İzole Emlak AI recovery telefon numarası bulunamadı.');
            }

            $history = $this->memoryService->openAIMesajlariHazirla(
                userId: RealEstateIsolationService::USER_ID,
                sessionId: (string) $message->session_id,
                limit: 20,
            );

            $answer = trim($this->openAIService->cevapVer(
                mesajlar: $history,
                aiBot: $bot,
            ));

            if ($answer === '') {
                throw new \RuntimeException('İzole Emlak AI recovery boş cevap üretti.');
            }

            $newerInboundExists = ChatMessage::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('session_id', $message->session_id)
                ->where('sender_type', 'customer')
                ->where('id', '>', $message->id)
                ->exists();

            $latestArrivalMessageId = Cache::get(
                'real-estate-latest-inbound:'.hash('sha256', $phoneNumber)
            );
            $supersededAtWebhook = is_string($latestArrivalMessageId)
                && trim($latestArrivalMessageId) !== ''
                && trim($latestArrivalMessageId) !== trim((string) $message->whatsapp_message_id);

            $bot->refresh();
            $conversation->refresh();

            if (
                $newerInboundExists
                || $supersededAtWebhook
                || (bool) $conversation->human_takeover
                || ! $bot->whatsappAiKullanilabilirMi()
            ) {
                $this->closeIgnored($activeReceipt, 'superseded_before_recovery_delivery');

                return 'superseded';
            }

            $deliveryResult = $this->outboundDeliveryService->deliver(
                bot: $bot,
                instance: RealEstateIsolationService::INSTANCE,
                inboundMessageId: (string) $message->whatsapp_message_id,
                sessionId: (string) $message->session_id,
                phoneNumber: $phoneNumber,
                answer: $answer,
            );

            $delivery = $deliveryResult['delivery'];

            if (($deliveryResult['state'] ?? null) !== 'sent') {
                $this->receiptService->complete($activeReceipt, [
                    'delivery_uncertain' => true,
                ]);

                return 'uncertain';
            }

            $this->outboundDeliveryService->persistAssistantMessage($delivery);
            $this->outboundDeliveryService->consumeTrialOnce($delivery, $bot);
            $this->receiptService->complete($activeReceipt, [
                'success' => true,
            ]);

            Log::info('REAL ESTATE UNANSWERED INBOUND RECOVERED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'message_id_hash' => hash('sha256', (string) $message->whatsapp_message_id),
                'sent_now' => (bool) ($deliveryResult['sent_now'] ?? false),
            ]);

            return 'replied';
        } catch (Throwable $exception) {
            $this->receiptService->fail($activeReceipt, $exception);

            Log::warning('REAL ESTATE UNANSWERED INBOUND RECOVERY FAILED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'message_id_hash' => hash('sha256', (string) $receipt->whatsapp_message_id),
                'error_class' => $exception::class,
            ]);

            report($exception);

            return 'failed';
        }
    }

    private function closeIgnored(
        RealEstateWebhookReceipt $receipt,
        string $reason,
    ): void {
        $receipt->forceFill([
            'status' => 'ignored',
            'last_error' => $reason,
            'processed_at' => now(),
        ])->save();
    }
}
