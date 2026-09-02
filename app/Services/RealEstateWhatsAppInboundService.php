<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
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

    public function process(array $payload, bool $suppressReply = false): array
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

        $phoneNumber = $this->resolvePhoneNumber($payload);

        if ($phoneNumber === null) {
            Log::warning('REAL ESTATE WHATSAPP LID WITHOUT PHONE MAPPING IGNORED', [
                'instance' => $instance,
                'remote_jid_type' => str_ends_with($remoteJid, '@lid') ? 'lid' : 'unknown',
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => str_ends_with($remoteJid, '@lid')
                    ? 'unresolved_lid'
                    : 'missing_phone',
            ];
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));

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
                suppressReply: $suppressReply,
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
        bool $suppressReply = false,
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

        $inboundMessage = $this->memoryService->mesajKaydet(
            userId: RealEstateIsolationService::USER_ID,
            aiBotId: RealEstateIsolationService::BOT_ID,
            sessionId: $sessionId,
            role: 'user',
            message: $message,
            senderType: 'customer',
            mediaContext: $mediaContext,
        );

        if (in_array($mediaContext['type'] ?? null, ['image', 'document'], true)) {
            try {
                app(EvolutionMediaService::class)->persistPrivateInboundMedia(
                    message: $inboundMessage,
                    instanceName: $instance,
                    mediaContext: $mediaContext,
                );
            } catch (Throwable $exception) {
                Log::warning('REAL ESTATE INBOUND MEDIA PERSISTENCE FAILED', [
                    'chat_message_id' => $inboundMessage->id,
                    'message_id' => $messageId,
                    'instance' => $instance,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ((bool) $conversation->human_takeover) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'human_takeover',
            ];
        }

        if ($suppressReply) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'media_batch_buffered',
            ];
        }

        $history = $this->memoryService->openAIMesajlariHazirla(
            userId: RealEstateIsolationService::USER_ID,
            sessionId: $sessionId,
            limit: 20,
        );

        $answer = $this->deterministicSellerPriceAnswer(
            conversation: $conversation,
            message: $message,
        );

        if ($answer === null) {
            $answer = $this->deterministicSellerPriceSteeringAnswer(
                conversation: $conversation,
                message: $message,
            );
        }

        if ($answer === null) {
            $answer = trim($this->openAIService->cevapVer(
                mesajlar: $history,
                aiBot: $bot,
            ));
        }

        if ($answer === '') {
            throw new RuntimeException('Emlak AI boş cevap üretti.');
        }

        // AI generation can take longer than a fast customer message burst.
        // Never deliver an answer prepared for an older turn after a newer
        // customer message has already been persisted.
        $newerInboundExists = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $sessionId)
            ->where('sender_type', 'customer')
            ->where('id', '>', $inboundMessage->id)
            ->exists();

        $bot->refresh();

        if ($newerInboundExists || ! $bot->whatsappAiKullanilabilirMi()) {
            Log::info('REAL ESTATE STALE OR DISABLED REPLY SUPPRESSED', [
                'conversation_id' => $conversation->id,
                'message_id' => $messageId,
                'newer_inbound_exists' => $newerInboundExists,
                'ai_enabled' => (bool) $bot->ai_enabled,
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => $newerInboundExists
                    ? 'superseded_by_newer_customer_message'
                    : 'ai_disabled_before_delivery',
            ];
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

    private function deterministicSellerPriceAnswer(
        ConversationControl $conversation,
        string $message,
    ): ?string {
        $normalized = strtolower(strtr(trim($message), [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        $priceIntent = false;
        foreach ([
            'ne kadara satabilirim', 'kaca satabilirim', 'kaca satilir',
            'kaca gider', 'ne kadar eder', 'kac para eder',
            'satis fiyati', 'gercekci satis',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                $priceIntent = true;
                break;
            }
        }

        if (! $priceIntent) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || $profile->profile_type !== 'seller') {
            return null;
        }

        $valuation = app(RealEstateValuationFreshnessService::class)
            ->valuationForDecision($profile);
        $realistic = $this->positivePrice($valuation['quick_sale_min'] ?? null)
            ?? $this->positivePrice($valuation['realistic_sale_min'] ?? null)
            ?? $this->positivePrice($valuation['market_min'] ?? null);

        if ($realistic === null) {
            return null;
        }

        $fastCashMin = round($realistic * 0.75 / 1000) * 1000;
        $fastCashMax = round($realistic * 0.80 / 1000) * 1000;

        return 'Gerçekçi satış fiyatı: yaklaşık '
            .$this->formatTl($realistic)
            .' TL — bu seviyede satış mümkün ama biraz bekleyebilir.'
            ."\n"
            .'Hızlı nakit alım bandı: yaklaşık '
            .$this->formatTl($fastCashMin)
            .'–'.$this->formatTl($fastCashMax)
            .' TL.'
            ."\n"
            .'Acil nakde çevirmek isterseniz yatırımcılardan teklifleri toplayıp size iletebilirim.';
    }

    private function deterministicSellerPriceSteeringAnswer(
        ConversationControl $conversation,
        string $message,
    ): ?string {
        $normalized = strtolower(strtr(trim($message), [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        $raiseIntent = false;
        foreach ([
            'fiyati artir', 'fiyat artir', 'biraz daha artir',
            'daha yuksek', 'yuksekten yaz', 'fiyati yuksek',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                $raiseIntent = true;
                break;
            }
        }

        if (! $raiseIntent) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || $profile->profile_type !== 'seller') {
            return null;
        }

        $packet = app(RealEstateSellerOfferPacketService::class)
            ->summaryForProfile($profile);
        $nextRequest = trim((string) ($packet['recommended_next_request'] ?? ''));

        $answer = 'Fiyatı daha yüksek yazmak mümkün; ancak yatırımcı tarafında yüksek fiyatlar dönüşü zorlaştırabiliyor. '
            .'Hızlı nakit düşünüyorsanız yatırımcılarımızdan teklif toplayalım.';

        if ($nextRequest !== '') {
            $answer .= "\n".$nextRequest;
        } elseif ((bool) ($packet['ready_for_investor_offer'] ?? false)) {
            $answer .= "\nDosya ön teklif toplamak için yeterli görünüyor; hızlı nakit tekliflerini değerlendirmeye geçebiliriz.";
        }

        return $answer;
    }

    private function positivePrice(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    private function formatTl(float $value): string
    {
        return number_format((int) round($value), 0, ',', '.');
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

    private function resolvePhoneNumber(array $payload): ?string
    {
        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        $remoteIsLid = str_ends_with(strtolower($remoteJid), '@lid');

        $candidates = $remoteIsLid
            ? [
                data_get($payload, 'data.key.remoteJidAlt'),
                data_get($payload, 'data.key.senderPn'),
                data_get($payload, 'data.key.participantPn'),
                data_get($payload, 'data.senderPn'),
                data_get($payload, 'data.participantPn'),
            ]
            : [$remoteJid];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate === '' || str_ends_with(strtolower($candidate), '@lid')) {
                continue;
            }

            if (str_contains($candidate, '@')) {
                $suffix = strtolower((string) strrchr($candidate, '@'));

                if (! in_array($suffix, ['@s.whatsapp.net', '@c.us'], true)) {
                    continue;
                }

                $candidate = (string) strstr($candidate, '@', true);
            }

            $digits = preg_replace('/\D+/', '', $candidate);

            if (is_string($digits) && strlen($digits) >= 8 && strlen($digits) <= 15) {
                return $digits;
            }
        }

        return null;
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
