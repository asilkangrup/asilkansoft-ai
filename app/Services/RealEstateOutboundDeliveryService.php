<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\RealEstateOutboundDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RealEstateOutboundDeliveryService
{
    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly WhatsAppService $whatsAppService,
    ) {
    }

    /**
     * Deliver at most once for a single inbound WhatsApp message.
     *
     * Once a network attempt starts, an exception is treated as uncertain
     * rather than automatically retried. This deliberately prefers a visible
     * operator-recovery case over sending a customer a duplicate AI reply.
     *
     * @return array{
     *     delivery:RealEstateOutboundDelivery,
     *     state:string,
     *     sent_now:bool,
     *     answer:string,
     *     whatsapp_message_id:?string
     * }
     */
    public function deliver(
        AiBot $bot,
        string $instance,
        string $inboundMessageId,
        string $sessionId,
        string $phoneNumber,
        string $answer,
    ): array {
        $this->assertScope($bot, $instance);

        $inboundMessageId = trim($inboundMessageId);
        $sessionId = trim($sessionId);
        $phoneNumber = trim($phoneNumber);
        $answer = trim($answer);

        if ($inboundMessageId === '') {
            throw new RuntimeException('İzole Emlak AI outbound teslimatı için inbound message id zorunludur.');
        }

        if ($sessionId === '' || $phoneNumber === '' || $answer === '') {
            throw new RuntimeException('İzole Emlak AI outbound teslimat bilgileri eksik.');
        }

        $deliveryKey = hash('sha256', $instance.'|'.$inboundMessageId);
        $answerHash = hash('sha256', $answer);

        try {
            $delivery = $this->reserve(
                deliveryKey: $deliveryKey,
                instance: $instance,
                inboundMessageId: $inboundMessageId,
                sessionId: $sessionId,
                phoneNumber: $phoneNumber,
                answerHash: $answerHash,
                answer: $answer,
            );
        } catch (QueryException $exception) {
            if (! $this->looksLikeUniqueViolation($exception)) {
                throw $exception;
            }

            $delivery = RealEstateOutboundDelivery::query()
                ->where('delivery_key', $deliveryKey)
                ->firstOrFail();
        }

        if ($delivery->status === 'sent') {
            return $this->result($delivery, sentNow: false);
        }

        if ($delivery->status === 'uncertain') {
            return $this->result($delivery, sentNow: false);
        }

        if ($delivery->status === 'sending') {
            // A previous worker reached the network boundary but never marked
            // a confirmed result. Retrying could duplicate a message that was
            // actually accepted by WhatsApp, so quarantine it for review.
            $delivery->forceFill([
                'status' => 'uncertain',
                'last_error' => $delivery->last_error
                    ?: 'Önceki gönderim ağ sınırında yarım kaldı; otomatik tekrar engellendi.',
            ])->save();

            return $this->result($delivery->fresh(), sentNow: false);
        }

        // If a retry generated a different answer for the same inbound message,
        // preserve and send the first reserved answer. This keeps the customer
        // conversation deterministic across queue/process retries.
        $reservedAnswer = trim((string) $delivery->answer);

        $delivery->forceFill([
            'status' => 'sending',
            'attempts' => (int) $delivery->attempts + 1,
            'sending_started_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $sendResult = $this->whatsAppService->sendText(
                instanceName: $instance,
                number: $phoneNumber,
                text: $reservedAnswer,
            );

            $whatsappMessageId = data_get($sendResult, 'key.id')
                ?? data_get($sendResult, 'messageId')
                ?? data_get($sendResult, 'id');

            $whatsappMessageId = is_scalar($whatsappMessageId)
                ? trim((string) $whatsappMessageId)
                : '';

            $delivery->forceFill([
                'status' => 'sent',
                'whatsapp_message_id' => $whatsappMessageId !== ''
                    ? $whatsappMessageId
                    : null,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            return $this->result($delivery->fresh(), sentNow: true);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => 'uncertain',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            report($exception);

            return $this->result($delivery->fresh(), sentNow: false);
        }
    }

    public function persistAssistantMessage(
        RealEstateOutboundDelivery $delivery,
    ): ?ChatMessage {
        $this->assertDeliveryScope($delivery);

        if (
            $delivery->status !== 'sent'
            || blank($delivery->whatsapp_message_id)
        ) {
            return null;
        }

        return ChatMessage::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'whatsapp_message_id' => (string) $delivery->whatsapp_message_id,
            ],
            [
                'session_id' => $delivery->session_id,
                'role' => 'assistant',
                'sender_type' => 'ai',
                'message' => $delivery->answer,
                'message_type' => 'text',
                'status' => 'sent',
            ]
        );
    }

    public function consumeTrialOnce(
        RealEstateOutboundDelivery $delivery,
        AiBot $bot,
    ): bool {
        $this->assertScope($bot, (string) $delivery->instance);
        $this->assertDeliveryScope($delivery);

        if ($delivery->status !== 'sent') {
            return false;
        }

        return DB::transaction(function () use ($delivery, $bot): bool {
            $lockedDelivery = RealEstateOutboundDelivery::query()
                ->whereKey($delivery->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDelivery->trial_consumed_at !== null) {
                return false;
            }

            $lockedBot = AiBot::query()
                ->whereKey(RealEstateIsolationService::BOT_ID)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBot->subscription_status === 'trial') {
                $limit = max(0, (int) $lockedBot->trial_message_limit);
                $used = min($limit, (int) $lockedBot->trial_messages_used + 1);

                $attributes = ['trial_messages_used' => $used];

                if ($limit > 0 && $used >= $limit) {
                    $attributes['trial_completed_at'] = $lockedBot->trial_completed_at ?: now();
                    $attributes['subscription_status'] = 'expired';
                }

                $lockedBot->forceFill($attributes)->save();
            }

            $lockedDelivery->forceFill([
                'trial_consumed_at' => now(),
            ])->save();

            return true;
        }, 3);
    }

    private function reserve(
        string $deliveryKey,
        string $instance,
        string $inboundMessageId,
        string $sessionId,
        string $phoneNumber,
        string $answerHash,
        string $answer,
    ): RealEstateOutboundDelivery {
        return DB::transaction(function () use (
            $deliveryKey,
            $instance,
            $inboundMessageId,
            $sessionId,
            $phoneNumber,
            $answerHash,
            $answer,
        ): RealEstateOutboundDelivery {
            $delivery = RealEstateOutboundDelivery::query()
                ->where('delivery_key', $deliveryKey)
                ->lockForUpdate()
                ->first();

            if ($delivery) {
                $this->assertDeliveryScope($delivery);

                return $delivery;
            }

            return RealEstateOutboundDelivery::query()->create([
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => $instance,
                'delivery_key' => $deliveryKey,
                'inbound_whatsapp_message_id' => $inboundMessageId,
                'session_id' => $sessionId,
                'phone_number' => $phoneNumber,
                'answer_hash' => $answerHash,
                'answer' => $answer,
                'status' => 'reserved',
                'attempts' => 0,
            ]);
        }, 3);
    }

    private function result(
        RealEstateOutboundDelivery $delivery,
        bool $sentNow,
    ): array {
        return [
            'delivery' => $delivery,
            'state' => (string) $delivery->status,
            'sent_now' => $sentNow,
            'answer' => (string) $delivery->answer,
            'whatsapp_message_id' => filled($delivery->whatsapp_message_id)
                ? (string) $delivery->whatsapp_message_id
                : null,
        ];
    }

    private function assertScope(AiBot $bot, string $instance): void
    {
        if (
            ! $this->isolation->supportsProductionBot($bot)
            || trim($instance) !== RealEstateIsolationService::INSTANCE
        ) {
            throw new RuntimeException('İzole Emlak AI outbound teslimat kapsamı ihlali.');
        }
    }

    private function assertDeliveryScope(RealEstateOutboundDelivery $delivery): void
    {
        if (
            (int) $delivery->user_id !== RealEstateIsolationService::USER_ID
            || (int) $delivery->organization_id !== RealEstateIsolationService::ORGANIZATION_ID
            || (int) $delivery->ai_bot_id !== RealEstateIsolationService::BOT_ID
            || (string) $delivery->instance !== RealEstateIsolationService::INSTANCE
        ) {
            throw new RuntimeException('İzole Emlak AI outbound kayıt kapsamı ihlali.');
        }
    }

    private function looksLikeUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || str_contains($message, '23000')
            || str_contains($message, '23505');
    }
}
