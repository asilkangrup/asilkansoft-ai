<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateOutboundDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RealEstateOutboundDeliveryService
{
    public const CONFIRMED_NOT_SENT_MARKER = 'Operatör kontrolü sonrası yeniden gönderilmeden abandoned olarak kapatıldı.';

    public const RECIPIENT_UNREACHABLE_MARKER = 'Evolution hedef numaranın WhatsApp hesabı olmadığını kesin olarak bildirdi; otomatik yeniden gönderim kapatıldı.';

    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly RealEstateOutboundSafetyService $outboundSafetyService,
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
     * Evolution's explicit `exists:false` recipient rejection is the one safe
     * exception to quarantine semantics: it proves the provider did not have a
     * WhatsApp destination to accept the message, so the row becomes terminal
     * abandoned and is never eligible for automatic recovery.
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

        $conversation = ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $sessionId)
            ->first();

        if (! $conversation || ! $this->isolation->supportsConversation($conversation)) {
            throw new RuntimeException('İzole Emlak AI outbound konuşma kapsamı bulunamadı.');
        }

        $answer = $this->safeAnswer(
            conversation: $conversation,
            answer: $answer,
            inboundMessageId: $inboundMessageId,
        );

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

        if ($delivery->status === 'abandoned') {
            // `abandoned` is terminal. A generic retry must never revive it:
            // only the narrow confirmed-not-sent recovery path may re-arm the
            // row after latest-turn/no-reply checks have also passed.
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
            if ($this->isExplicitRecipientRejection($exception)) {
                $delivery->forceFill([
                    'status' => 'abandoned',
                    'whatsapp_message_id' => null,
                    'sent_at' => null,
                    'last_error' => self::RECIPIENT_UNREACHABLE_MARKER,
                ])->save();

                return $this->result($delivery->fresh(), sentNow: false);
            }

            $delivery->forceFill([
                'status' => 'uncertain',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            report($exception);

            return $this->result($delivery->fresh(), sentNow: false);
        }
    }

    /**
     * Return a previously abandoned delivery only when an operator explicitly
     * recorded that no WhatsApp message was sent and there is still no provider
     * message id or sent timestamp. This is intentionally much narrower than
     * treating every abandoned row as retryable.
     */
    public function confirmedAbandonedForInbound(
        string $inboundMessageId,
    ): ?RealEstateOutboundDelivery {
        $inboundMessageId = trim($inboundMessageId);

        if ($inboundMessageId === '') {
            return null;
        }

        return RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->where('inbound_whatsapp_message_id', $inboundMessageId)
            ->where('status', 'abandoned')
            ->whereNull('whatsapp_message_id')
            ->whereNull('sent_at')
            ->where('last_error', self::CONFIRMED_NOT_SENT_MARKER)
            ->first();
    }

    /**
     * Re-arm exactly one operator-confirmed not-sent delivery with a fresh,
     * safety-filtered answer. The transaction rechecks every no-send invariant
     * so a provider reconciliation or concurrent worker can make this fail
     * closed instead of creating a duplicate.
     */
    public function reopenConfirmedAbandonedForRecovery(
        RealEstateOutboundDelivery $delivery,
        string $replacementAnswer,
    ): RealEstateOutboundDelivery {
        $this->assertDeliveryScope($delivery);

        $conversation = ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $delivery->session_id)
            ->first();

        if (! $conversation || ! $this->isolation->supportsConversation($conversation)) {
            throw new RuntimeException('İzole Emlak AI recovery konuşma kapsamı bulunamadı.');
        }

        $answer = $this->safeAnswer(
            conversation: $conversation,
            answer: $replacementAnswer,
            inboundMessageId: (string) $delivery->inbound_whatsapp_message_id,
        );

        return DB::transaction(function () use ($delivery, $answer): RealEstateOutboundDelivery {
            $locked = RealEstateOutboundDelivery::query()
                ->whereKey($delivery->id)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('instance', RealEstateIsolationService::INSTANCE)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->status !== 'abandoned'
                || filled($locked->whatsapp_message_id)
                || filled($locked->sent_at)
                || (string) $locked->last_error !== self::CONFIRMED_NOT_SENT_MARKER
            ) {
                throw new RuntimeException(
                    'Abandoned Emlak AI teslimatı yeniden açılmaya uygun değil; duplicate güvenliği nedeniyle işlem durduruldu.'
                );
            }

            $locked->forceFill([
                'status' => 'reserved',
                'answer' => $answer,
                'answer_hash' => hash('sha256', $answer),
                'sending_started_at' => null,
                'last_error' => null,
            ])->save();

            return $locked->fresh();
        }, 3);
    }

    public function persistAssistantMessage(
        RealEstateOutboundDelivery $delivery,
    ): ?ChatMessage {
        $this->assertDeliveryScope($delivery);

        if ($delivery->status !== 'sent') {
            return null;
        }

        $messageId = filled($delivery->whatsapp_message_id)
            ? (string) $delivery->whatsapp_message_id
            : 'real-estate-outbound:'.$delivery->delivery_key;

        return ChatMessage::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'whatsapp_message_id' => $messageId,
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

    private function safeAnswer(
        ConversationControl $conversation,
        string $answer,
        string $inboundMessageId,
    ): string {
        $answer = trim($answer);

        if ($answer === '') {
            throw new RuntimeException('İzole Emlak AI outbound cevabı boş olamaz.');
        }

        $safety = $this->outboundSafetyService->protect(
            conversation: $conversation,
            answer: $answer,
            inboundMessageId: $inboundMessageId,
        );
        $answer = str_replace('*', '', trim((string) $safety['answer']));

        if ($answer === '') {
            throw new RuntimeException('İzole Emlak AI outbound güvenlik filtresi boş cevap üretti.');
        }

        return $answer;
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

    private function isExplicitRecipientRejection(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $message = preg_replace('/\s+/', '', $message) ?? $message;

        return str_contains($message, '"exists":false');
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
