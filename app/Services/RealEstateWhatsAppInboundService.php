<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RealEstateWhatsAppInboundService
{
    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly MemoryService $memoryService,
        private readonly RealEstateOpenAIService $openAIService,
        private readonly RealEstateWhatsAppMessageParser $messageParser,
        private readonly RealEstateWebhookReceiptService $receiptService,
        private readonly RealEstateAudioTranscriptionService $audioTranscriptionService,
        private readonly RealEstateOutboundDeliveryService $outboundDeliveryService,
    ) {
    }

    public function process(array $payload): array
    {
        $event = $this->normalizeEvent($payload['event'] ?? null);

        if ($event !== 'messages.upsert') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'unsupported_event',
            ];
        }

        $instance = trim((string) data_get($payload, 'instance', ''));
        $bot = $this->botForInstance($instance);

        if (! $bot) {
            throw new RuntimeException('İzole Emlak AI botu/instance eşleşmesi bulunamadı.');
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'from_me',
            ];
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));

        if ($remoteJid === '' || str_contains($remoteJid, '@g.us')) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'unsupported_chat',
            ];
        }

        $phoneNumber = trim((string) explode('@', $remoteJid)[0]);

        if ($phoneNumber === '') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'missing_phone',
            ];
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));

        // Durable inbound/outbound idempotency depends on Evolution's message
        // identifier. Refuse an unidentifiable production message rather than
        // risk saving or replying to the same customer event multiple times.
        if ($messageId === '') {
            Log::warning('REAL ESTATE WHATSAPP MESSAGE WITHOUT ID IGNORED', [
                'instance' => $instance,
                'phone_number_suffix' => substr($phoneNumber, -4),
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'missing_message_id',
            ];
        }

        $receiptState = $this->receiptService->begin(
            instance: $instance,
            event: $event,
            messageId: $messageId,
            phoneNumber: $phoneNumber,
        );
        $receipt = $receiptState['receipt'];

        if (! $receiptState['should_process']) {
            Log::info('REAL ESTATE DUPLICATE WHATSAPP MESSAGE IGNORED', [
                'message_id' => $messageId,
                'instance' => $instance,
                'reason' => $receiptState['reason'],
                'receipt_id' => $receipt?->id,
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => $receiptState['reason'] ?: 'duplicate_message',
            ];
        }

        try {
            $result = $this->processMessage(
                payload: $payload,
                bot: $bot,
                instance: $instance,
                phoneNumber: $phoneNumber,
                messageId: $messageId,
            );

            $this->receiptService->complete($receipt, $result);

            return $result;
        } catch (Throwable $exception) {
            $this->receiptService->fail($receipt, $exception);

            Log::error('REAL ESTATE INBOUND WHATSAPP FAILED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => $instance,
                'message_id' => $messageId,
                'receipt_id' => $receipt?->id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function handleStatusUpdate(array $payload): array
    {
        $instance = trim((string) data_get($payload, 'instance', ''));
        $bot = $this->botForInstance($instance);

        if (! $bot) {
            throw new RuntimeException('İzole Emlak AI status update instance eşleşmesi bulunamadı.');
        }

        $updates = data_get($payload, 'data', []);

        if (! is_array($updates)) {
            return [
                'success' => true,
                'status_update' => true,
                'updated' => 0,
            ];
        }

        $items = array_is_list($updates) ? $updates : [$updates];
        $updated = 0;

        foreach ($items as $update) {
            if (! is_array($update)) {
                continue;
            }

            $messageId = data_get($update, 'key.id')
                ?? data_get($update, 'id')
                ?? data_get($update, 'messageId');

            if (! is_string($messageId) || trim($messageId) === '') {
                continue;
            }

            $status = $this->normalizeStatus(
                data_get($update, 'update.status')
                    ?? data_get($update, 'status')
                    ?? data_get($update, 'message.status')
            );

            $updated += ChatMessage::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('whatsapp_message_id', trim($messageId))
                ->update(['status' => $status]);
        }

        return [
            'success' => true,
            'status_update' => true,
            'updated' => $updated,
        ];
    }

    private function processMessage(
        array $payload,
        AiBot $bot,
        string $instance,
        string $phoneNumber,
        string $messageId,
    ): array {
        if (! $this->isolation->organizationValid()) {
            throw new RuntimeException('İzole Emlak AI organizasyon kimliği geçersiz.');
        }

        if (! $bot->whatsappAiKullanilabilirMi()) {
            Log::warning('REAL ESTATE AI SUBSCRIPTION/AI DISABLED', [
                'ai_bot_id' => $bot->id,
                'subscription_status' => $bot->subscription_status,
                'ai_enabled' => (bool) $bot->ai_enabled,
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'ai_unavailable',
            ];
        }

        [$message, $mediaContext] = $this->messageParser->extract(
            payload: $payload,
            instance: $instance,
            messageId: $messageId,
        );

        if (($mediaContext['type'] ?? null) === 'audio') {
            $transcription = $this->audioTranscriptionService->transcribe(
                bot: $bot,
                instance: $instance,
                mediaContext: $mediaContext,
            );

            $mediaContext['transcript'] = $transcription['text'];
            $mediaContext['transcription_status'] = $transcription['status'];
            $mediaContext['transcription_model'] = $transcription['model'];
            $mediaContext['transcription_language'] = $transcription['language'];
            $mediaContext['transcribed_at'] = $transcription['transcribed_at'];
            $mediaContext['size'] = $transcription['bytes'] ?? ($mediaContext['size'] ?? null);

            if (filled($transcription['text'])) {
                $message = trim((string) $transcription['text']);
            } else {
                $message = $this->audioFallbackMessage((string) $transcription['status']);
            }
        }

        if ($message === '') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'empty_message',
            ];
        }

        $sessionId = 'whatsapp:'.RealEstateIsolationService::BOT_ID.':'.$phoneNumber;

        $conversation = ConversationControl::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'session_id' => $sessionId,
            ],
            [
                'whatsapp_number' => $phoneNumber,
                'customer_name' => null,
                'unread_count' => 0,
                'human_takeover' => false,
            ]
        );

        if (! $this->isolation->supportsConversation($conversation)) {
            throw new RuntimeException('İzole Emlak AI konuşma kapsamı ihlali.');
        }

        $inboundAlreadyPersisted = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('whatsapp_message_id', $messageId)
            ->exists();

        $pushName = trim((string) data_get($payload, 'data.pushName', ''));

        $conversation->forceFill([
            'whatsapp_number' => $phoneNumber,
            'customer_name' => trim((string) $conversation->customer_name) !== ''
                ? $conversation->customer_name
                : ($pushName !== '' ? $pushName : null),
            'unread_count' => $inboundAlreadyPersisted
                ? (int) $conversation->unread_count
                : (int) $conversation->unread_count + 1,
            'last_contact_at' => now(),
        ])->save();

        $this->memoryService->mesajKaydet(
            userId: RealEstateIsolationService::USER_ID,
            aiBotId: RealEstateIsolationService::BOT_ID,
            sessionId: $sessionId,
            role: 'user',
            message: $message,
            senderType: 'customer',
            mediaContext: $mediaContext,
        );

        if ((bool) $conversation->human_takeover) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'human_takeover',
            ];
        }

        $history = $this->memoryService->openAIMesajlariHazirla(
            userId: RealEstateIsolationService::USER_ID,
            sessionId: $sessionId,
            limit: 20,
        );

        $answer = trim($this->openAIService->cevapVer(
            mesajlar: $history,
            aiBot: $bot,
        ));

        if ($answer === '') {
            throw new RuntimeException('Emlak AI boş cevap üretti.');
        }

        $deliveryResult = $this->outboundDeliveryService->deliver(
            bot: $bot,
            instance: $instance,
            inboundMessageId: $messageId,
            sessionId: $sessionId,
            phoneNumber: $phoneNumber,
            answer: $answer,
        );

        $delivery = $deliveryResult['delivery'];

        if ($deliveryResult['state'] !== 'sent') {
            Log::error('REAL ESTATE OUTBOUND DELIVERY UNCERTAIN', [
                'delivery_id' => $delivery->id,
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'instance' => $instance,
                'attempts' => $delivery->attempts,
            ]);

            return [
                'success' => true,
                'delivery_uncertain' => true,
                'reason' => 'outbound_delivery_uncertain',
                'message' => 'Emlak AI cevabı üretildi ancak WhatsApp teslimatı doğrulanamadı; otomatik tekrar engellendi.',
            ];
        }

        // After WhatsApp confirms the send, do not turn a local persistence or
        // trial-accounting problem into a webhook retry that could duplicate the
        // already-delivered customer reply. Repairable state remains in the
        // isolated outbound ledger.
        try {
            $this->outboundDeliveryService->persistAssistantMessage($delivery);
            $this->outboundDeliveryService->consumeTrialOnce($delivery, $bot);
        } catch (Throwable $exception) {
            Log::error('REAL ESTATE OUTBOUND POST-SEND PERSISTENCE FAILED', [
                'delivery_id' => $delivery->id,
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'instance' => $instance,
                'message' => $exception->getMessage(),
            ]);

            report($exception);
        }

        Log::info('REAL ESTATE WHATSAPP MESSAGE PROCESSED', [
            'conversation_id' => $conversation->id,
            'message_id' => $messageId,
            'message_type' => $mediaContext['type'] ?? 'text',
            'audio_transcription_status' => $mediaContext['transcription_status'] ?? null,
            'instance' => $instance,
            'delivery_id' => $delivery->id,
            'sent_now' => (bool) $deliveryResult['sent_now'],
        ]);

        return [
            'success' => true,
            'message' => 'Emlak AI cevabı gönderildi.',
            'delivery_deduplicated' => ! (bool) $deliveryResult['sent_now'],
        ];
    }

    private function botForInstance(string $instance): ?AiBot
    {
        if ($instance === '') {
            return null;
        }

        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('business_sector', 'real_estate')
            ->where('whatsapp_instance', $instance)
            ->first();

        return $this->isolation->supportsProductionBot($bot) ? $bot : null;
    }

    private function audioFallbackMessage(string $status): string
    {
        return match ($status) {
            'too_large' => '[Sesli mesaj - dosya transkripsiyon sınırını aşıyor. Kullanıcıdan daha kısa ses kaydı veya yazılı mesaj istemelisin.]',
            'unsupported_format' => '[Sesli mesaj - ses formatı desteklenmedi. Kullanıcıdan içeriği yazılı göndermesini istemelisin.]',
            default => '[Sesli mesaj - içerik güvenilir biçimde metne çevrilemedi. Duyduğunu varsayma; kullanıcıdan içeriği yazılı göndermesini istemelisin.]',
        };
    }

    private function normalizeEvent(mixed $event): string
    {
        return str_replace(
            ['_', '-'],
            '.',
            strtolower(trim((string) $event))
        );
    }

    private function normalizeStatus(mixed $rawStatus): string
    {
        if (is_numeric($rawStatus)) {
            $rawStatus = match ((int) $rawStatus) {
                0 => 'error',
                1 => 'pending',
                2 => 'sent',
                3 => 'delivered',
                4 => 'read',
                5 => 'played',
                default => 'sent',
            };
        }

        $status = strtolower(trim((string) $rawStatus));

        return match ($status) {
            'pending', 'server_ack' => 'pending',
            'sent', 'device_ack' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'played' => 'played',
            'error', 'failed' => 'error',
            default => $status !== '' ? $status : 'sent',
        };
    }
}
