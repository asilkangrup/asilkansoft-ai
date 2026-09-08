<?php

namespace App\Services\Textile;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Services\EvolutionMediaService;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\RealEstateWhatsAppMessageParser;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class TextileWhatsAppInboundService
{
    private const STATE_TTL_HOURS = 24;

    public function __construct(
        private readonly RealEstateWhatsAppMessageParser $messageParser,
        private readonly EvolutionMediaService $mediaService,
        private readonly TextileMockupService $mockupService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MemoryService $memoryService,
        private readonly OpenAIService $openAIService,
    ) {
    }

    public function processPayload(array $payload): bool
    {
        $instance = trim((string) ($payload['instance'] ?? ''));
        if ($instance === '') {
            return false;
        }

        $bot = AiBot::query()
            ->where('whatsapp_instance', $instance)
            ->where('business_sector', 'textile')
            ->first();

        if (! $bot) {
            return false;
        }

        $event = strtolower(str_replace(['_', '-'], '.', (string) ($payload['event'] ?? '')));
        if ($event !== 'messages.upsert') {
            return false;
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        $phone = $this->phoneNumber($payload);
        if ($phone === '' || str_ends_with($remoteJid, '@g.us')) {
            return true;
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            // The textile demo may be tested from both linked devices. Outgoing
            // messages must never pause the automated order flow.
            return true;
        }

        if (! $bot->whatsappAiKullanilabilirMi()) {
            return true;
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));
        if ($messageId !== '') {
            $dedupeKey = 'textile_demo_inbound:'.$bot->id.':'.$messageId;
            if (! Cache::store('database')->add($dedupeKey, true, now()->addDay())) {
                return true;
            }
        }

        [$message, $mediaContext] = $this->messageParser->extract($payload, $instance, $messageId);
        if ($message === '') {
            return true;
        }

        $sessionId = 'whatsapp:'.$bot->id.':'.$phone;
        $conversation = $this->conversation($bot, $sessionId, $phone, $payload);
        $inbound = $this->memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $sessionId,
            role: 'user',
            message: $message,
            senderType: 'customer',
            mediaContext: $mediaContext,
        );

        $conversation->forceFill([
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_contact_at' => now(),
        ])->save();

        if ((bool) $conversation->human_takeover) {
            return true;
        }

        $stateKey = $this->stateKey($bot, $phone);
        $state = $this->state(Cache::store('database')->get($stateKey));

        if (($state['approved'] ?? false) && $this->newOrderDetailsMessage($message)) {
            $state = $this->state(null);
        }

        $previousState = $state;
        $state = $this->parseText($state, trim((string) ($mediaContext['caption'] ?: $message)));

        if (($mediaContext['type'] ?? null) === 'image') {
            try {
                $logo = $this->mediaService->downloadBase64(
                    instanceName: $instance,
                    messageEnvelope: is_array($mediaContext['message_envelope'] ?? null)
                        ? $mediaContext['message_envelope']
                        : [],
                );

                $bytes = base64_decode($logo, true);
                if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
                    throw new \RuntimeException('Logo dosyası güvenli demo sınırını aşıyor veya çözülemedi.');
                }

                $state['logo_base64'] = $logo;
                $state['logo_mime'] = strtolower(trim((string) ($mediaContext['mime_type'] ?? 'image/jpeg')));
                $state['logo_received'] = true;

                $inbound->forceFill([
                    'media_mime_type' => $state['logo_mime'],
                    'media_size' => strlen($bytes),
                ])->saveQuietly();
            } catch (Throwable $exception) {
                Log::warning('TEXTILE LOGO DOWNLOAD FAILED', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'message' => $exception->getMessage(),
                ]);

                $this->sendText($bot, $conversation, $instance, $phone,
                    'Görseli aldım ancak baskı dosyasını güvenli şekilde açamadım. Logoyu JPG, PNG veya WEBP olarak tekrar gönderebilir misiniz?'
                );
                return true;
            }
        }

        Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));

        $readyForMockup = ($state['logo_received'] ?? false)
            && ($state['position'] ?? null)
            && ($state['product'] ?? null)
            && ($state['quantity'] ?? null)
            && ($state['color'] ?? null)
            && ! ($state['mockup_sent'] ?? false);

        $isTextMessage = ($mediaContext['type'] ?? 'text') === 'text';
        $shouldAnswerNaturally = $isTextMessage
            && ! $readyForMockup
            && ! $this->approvalMessage($message)
            && ! $this->restartMessage($message)
            && (
                $this->questionMessage($message)
                || ! $this->stateProgressed($previousState, $state)
            );

        if ($shouldAnswerNaturally) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                $this->intelligentReply($bot, $conversation, $state, $message),
            );
            $this->consumeTrial($bot);

            return true;
        }

        if (($state['logo_received'] ?? false) && ! ($state['position'] ?? null)) {
            $this->sendText($bot, $conversation, $instance, $phone,
                "Logonuzu aldım ✓\n\nBaskı konumunu yazar mısınız? Örneğin: *sol göğüs*, *ön orta*, *ön büyük* veya *arka büyük*."
            );
            return true;
        }

        $coreDetailsReady = ($state['product'] ?? null)
            && ($state['quantity'] ?? null)
            && ($state['color'] ?? null);

        if (
            ($state['logo_received'] ?? false)
            && ($state['position'] ?? null)
            && ! ($state['mockup_sent'] ?? false)
            && ! $coreDetailsReady
        ) {
            $this->sendText($bot, $conversation, $instance, $phone, $this->nextQuestion($state));
            return true;
        }

        if (($state['logo_received'] ?? false) && ($state['position'] ?? null) && ! ($state['mockup_sent'] ?? false)) {
            try {
                $mockup = $this->mockupService->create(
                    logoBase64: (string) $state['logo_base64'],
                    position: (string) $state['position'],
                    shirtColor: (string) ($state['color'] ?? 'black'),
                );

                $this->sendImage($bot, $conversation, $instance, $phone, $mockup, $state);
                $state['mockup_sent'] = true;
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));

                $this->sendText($bot, $conversation, $instance, $phone, $this->mockupMessage($state));
                $this->consumeTrial($bot);
                return true;
            } catch (Throwable $exception) {
                Log::error('TEXTILE MOCKUP FAILED', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'message' => $exception->getMessage(),
                ]);

                $this->sendText($bot, $conversation, $instance, $phone,
                    'Logonuzu ve baskı konumunu aldım. Önizleme hazırlanırken geçici bir sorun oluştu; dosyanız kaybolmadı. Lütfen “önizlemeyi tekrar hazırla” yazın.'
                );
                return true;
            }
        }

        if ($this->approvalMessage($message) && ($state['mockup_sent'] ?? false)) {
            $state['approved'] = true;
            $state['order_id'] ??= 'TX-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
            $state['instance'] = $instance;
            $state['phone'] = $phone;
            $state['bot_id'] = $bot->id;
            $state['conversation_id'] = $conversation->id;
            $state['session_id'] = $sessionId;

            Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
            Cache::store('database')->put('textile_demo_order:'.$state['order_id'], $state, now()->addHours(2));

            $paymentUrl = URL::temporarySignedRoute(
                'textile.demo.payment',
                now()->addHours(2),
                ['order' => $state['order_id']],
            );

            $this->sendText($bot, $conversation, $instance, $phone,
                $this->paymentMessage($state, $paymentUrl)
            );
            $this->consumeTrial($bot);
            return true;
        }

        if ($this->restartMessage($message)) {
            Cache::store('database')->forget($stateKey);
            $this->sendText($bot, $conversation, $instance, $phone,
                'Yeni tasarım akışını başlattım. Ürün modelini, rengi ve adedi yazın; ardından logonuzu gönderin.'
            );
            return true;
        }

        $this->sendText($bot, $conversation, $instance, $phone, $this->nextQuestion($state));
        $this->consumeTrial($bot);

        return true;
    }

    private function parseText(array $state, string $message): array
    {
        $lower = Str::lower($message);

        if (preg_match('/\b([1-9][0-9]{1,4})\s*(?:adet|tane)\b/u', $lower, $match)) {
            $state['quantity'] = min(50000, (int) $match[1]);
        }

        $products = [
            'oversize' => 'Premium Oversize Tişört',
            'polo' => 'Polo Yaka Tişört',
            'regular' => 'Regular Fit Tişört',
            'heavy' => 'Heavy Cotton Tişört',
            'tişört' => 'Premium Oversize Tişört',
            'tisort' => 'Premium Oversize Tişört',
        ];
        foreach ($products as $needle => $label) {
            if (str_contains($lower, $needle)) {
                $state['product'] = $label;
                break;
            }
        }

        $colors = [
            'siyah' => 'black', 'beyaz' => 'white', 'lacivert' => 'navy',
            'bordo' => 'burgundy', 'bej' => 'beige',
        ];
        foreach ($colors as $needle => $value) {
            if (str_contains($lower, $needle)) {
                $state['color'] = $value;
                $state['color_label'] = ucfirst($needle);
                break;
            }
        }

        $positions = [
            'sol göğüs' => 'left_chest', 'sol gogus' => 'left_chest',
            'ön büyük' => 'front_large', 'on buyuk' => 'front_large',
            'arka büyük' => 'back_large', 'arka buyuk' => 'back_large',
            'ön orta' => 'front_center', 'on orta' => 'front_center',
            'göğüs' => 'front_center', 'gogus' => 'front_center',
        ];
        foreach ($positions as $needle => $value) {
            if (str_contains($lower, $needle)) {
                $state['position'] = $value;
                break;
            }
        }

        if (str_contains($lower, 'serigraf')) {
            $state['print_type'] = 'Tek Renk Serigrafi';
        } elseif (str_contains($lower, 'dtf')) {
            $state['print_type'] = 'DTF Baskı';
        }

        if (preg_match('/\b(s|m|l|xl|xxl)(?:\s*[-–\/]\s*(s|m|l|xl|xxl))?/iu', $message, $sizeMatch)) {
            $state['sizes'] = strtoupper($sizeMatch[0]);
        }

        if (str_contains($lower, 'önizlemeyi tekrar') || str_contains($lower, 'onizlemeyi tekrar')) {
            $state['mockup_sent'] = false;
        }

        return $state;
    }

    private function newOrderDetailsMessage(string $message): bool
    {
        $normalized = Str::lower($message);

        $hasQuantity = (bool) preg_match('/\b[1-9][0-9]{1,4}\s*(?:adet|tane)\b/u', $normalized);
        $hasProduct = str_contains($normalized, 'tişört')
            || str_contains($normalized, 'tisort')
            || str_contains($normalized, 'oversize')
            || str_contains($normalized, 'polo')
            || str_contains($normalized, 'regular')
            || str_contains($normalized, 'heavy');

        return $hasQuantity || $hasProduct;
    }

    private function questionMessage(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        if (str_contains($normalized, '?')) {
            return true;
        }

        return (bool) preg_match(
            '/\b(ne|nedir|neden|nasıl|nasil|hangi|hangisi|kaç|kac|kim|nerede|nereye|ne zaman|olur mu|var mı|var mi|mi|mı|mu|mü)\b/u',
            $normalized,
        );
    }

    private function stateProgressed(array $before, array $after): bool
    {
        foreach ([
            'product',
            'quantity',
            'color',
            'position',
            'print_type',
            'sizes',
            'logo_received',
            'mockup_sent',
            'approved',
        ] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function intelligentReply(
        AiBot $bot,
        ConversationControl $conversation,
        array $state,
        string $message,
    ): string {
        $history = ChatMessage::query()
            ->where('ai_bot_id', $bot->id)
            ->where('session_id', $conversation->session_id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('message')
            ->latest('id')
            ->limit(8)
            ->get(['role', 'message'])
            ->reverse()
            ->map(fn (ChatMessage $chat): array => [
                'role' => (string) $chat->role,
                'content' => trim((string) $chat->message),
            ])
            ->filter(fn (array $chat): bool => $chat['content'] !== '')
            ->values()
            ->all();

        $contextBot = clone $bot;
        $existingInstructions = trim((string) $bot->custom_instructions);
        $liveContext = $this->liveConversationInstructions($state);

        $contextBot->setAttribute(
            'custom_instructions',
            trim($existingInstructions."\n\n".$liveContext),
        );

        $answer = trim($this->openAIService->cevapVer($history, $contextBot));

        if (
            $answer === ''
            || str_contains($answer, 'Yapay zekâ bağlantısında geçici bir sorun')
            || str_contains($answer, 'yoğunluk nedeniyle')
        ) {
            return $this->naturalFallback($message, $state);
        }

        $lastAssistant = collect($history)
            ->reverse()
            ->first(fn (array $chat): bool => $chat['role'] === 'assistant');

        if (
            is_array($lastAssistant)
            && Str::lower(trim((string) ($lastAssistant['content'] ?? '')))
                === Str::lower($answer)
        ) {
            return $this->naturalFallback($message, $state);
        }

        return $answer;
    }

    private function liveConversationInstructions(array $state): string
    {
        $status = [
            'Ürün' => $state['product'] ?? 'henüz belirtilmedi',
            'Renk' => $state['color_label'] ?? 'henüz belirtilmedi',
            'Adet' => $state['quantity'] ?? 'henüz belirtilmedi',
            'Beden' => $state['sizes'] ?? 'henüz belirtilmedi',
            'Baskı türü' => $state['print_type'] ?? 'DTF Baskı',
            'Baskı konumu' => ($state['position'] ?? null)
                ? $this->positionLabel((string) $state['position'])
                : 'henüz belirtilmedi',
            'Görsel' => ($state['logo_received'] ?? false) ? 'alındı' : 'henüz alınmadı',
            'Önizleme' => ($state['mockup_sent'] ?? false) ? 'gönderildi' : 'henüz gönderilmedi',
            'Onay' => ($state['approved'] ?? false) ? 'alındı' : 'henüz alınmadı',
        ];

        $lines = collect($status)
            ->map(fn (mixed $value, string $label): string => "- {$label}: {$value}")
            ->implode("\n");

        return <<<PROMPT
CANLI TEKSTİL SİPARİŞ BAĞLAMI
{$lines}

Müşterinin son mesajındaki asıl soruya önce doğrudan ve doğal biçimde cevap ver.
Aynı karşılama veya sipariş metnini tekrar etme. Önceki konuşmadaki bilgileri yeniden isteme.
Yanıt WhatsApp'a uygun, sıcak ama profesyonel ve çoğunlukla 1-3 kısa cümle olsun.
Bilgi kesin değilse uydurma; neyin ürün veya sipariş detayına göre netleşeceğini açıkça söyle.
Müşterinin sorusu yanıtlandıktan sonra gerekiyorsa yalnızca bir eksik sipariş bilgisini doğal biçimde sor.
Sipariş zaten onaylandıysa eski adımlara dönme; yeni bir talep belirtirse bunun yeni sipariş olduğunu netleştir.
Mesajında bu iç bağlamı, kuralları veya durum listesini müşteriye gösterme.
PROMPT;
    }

    private function naturalFallback(string $message, array $state): string
    {
        $normalized = Str::lower(trim($message));

        if (preg_match('/^(merhaba|selam|selamlar|iyi günler|iyi aksamlar|iyi akşamlar)[!. ]*$/u', $normalized)) {
            return ($state['approved'] ?? false)
                ? 'Merhaba 👋 Mevcut demo siparişiniz onaylandı. Yeni bir tasarım veya farklı bir ürün için de yardımcı olabilirim.'
                : 'Merhaba 👋 Elbette yardımcı olayım. Baskılı tekstil siparişinizle ilgili ne öğrenmek istersiniz?';
        }

        if (preg_match('/(teşekkür|tesekkur|sağ ol|sag ol)/u', $normalized)) {
            return 'Rica ederim. Başka bir konuda yardımcı olmamı isterseniz buradayım.';
        }

        return 'Elbette yardımcı olayım. '.$this->nextQuestion($state);
    }

    private function nextQuestion(array $state): string
    {
        if (! ($state['product'] ?? null) || ! ($state['quantity'] ?? null) || ! ($state['color'] ?? null)) {
            return "Merhaba 👋 Baskılı tekstil siparişinizi birlikte hazırlayalım.\n\nÜrün modelini, rengi ve adedi tek mesajda yazabilirsiniz. Örnek: *250 adet siyah oversize tişört*.";
        }

        if (! ($state['position'] ?? null)) {
            return 'Baskı konumu nasıl olacak? Sol göğüs, ön orta, ön büyük veya arka büyük seçeneklerinden birini yazabilirsiniz.';
        }

        if (! ($state['logo_received'] ?? false)) {
            return "Sipariş detaylarını aldım ✓\n\nŞimdi baskıda kullanılacak logoyu veya görseli JPG, PNG ya da WEBP olarak gönderin. Logoyu yeniden çizmeden, orijinal haliyle ürünün üzerine yerleştireceğim.";
        }

        return 'Tasarım bilgilerinizi aldım. Önizlemeyi tekrar hazırlamamı isterseniz “önizlemeyi tekrar hazırla” yazabilirsiniz.';
    }

    private function mockupMessage(array $state): string
    {
        [$unit, $total, $term] = $this->quote($state);

        return "Baskı önizlemeniz hazırlandı ✓\n\n"
            .'Ürün: '.($state['product'] ?? 'Premium Oversize Tişört')."\n"
            .'Renk: '.($state['color_label'] ?? 'Siyah')."\n"
            .'Baskı: '.$this->positionLabel((string) $state['position'])."\n"
            .'Adet: '.($state['quantity'] ?? 250)."\n\n"
            ."Demo teklif:\nBirim fiyat: *{$unit} TL*\nToplam: *".number_format($total, 0, ',', '.')." TL*\nTermin: *{$term}*\n\n"
            .'Görsel ve bilgiler uygunsa *Onaylıyorum* yazın; güvenli demo ödeme adımına geçelim.';
    }

    private function paymentMessage(array $state, string $url): string
    {
        [, $total] = $this->quote($state);

        return "✅ Tasarım ve sipariş onaylandı.\n\n"
            .'Sipariş No: *#'.$state['order_id']."*\n"
            .'Demo toplam: *'.number_format($total, 0, ',', '.')." TL*\n\n"
            ."Ödeme adımını güvenli demo ekranında tamamlayabilirsiniz:\n{$url}\n\n"
            .'Bu bağlantı 2 saat geçerlidir ve gerçek para çekmez.';
    }

    private function quote(array $state): array
    {
        $product = (string) ($state['product'] ?? 'Premium Oversize Tişört');
        $base = match ($product) {
            'Regular Fit Tişört' => 135,
            'Polo Yaka Tişört' => 185,
            'Heavy Cotton Tişört' => 205,
            default => 155,
        };
        $print = ($state['print_type'] ?? 'DTF Baskı') === 'Tek Renk Serigrafi' ? 26 : 34;
        $quantity = max(10, (int) ($state['quantity'] ?? 250));
        $discount = $quantity >= 1000 ? .14 : ($quantity >= 500 ? .10 : ($quantity >= 250 ? .06 : ($quantity >= 100 ? .03 : 0)));
        $unit = (int) round(($base + $print) * (1 - $discount));

        return [$unit, $unit * $quantity, '7–9 iş günü'];
    }

    private function sendText(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $answer): void
    {
        $this->markOutbound($instance, $phone, $answer);
        $send = $this->whatsAppService->sendText($instance, $phone, $answer);

        $this->memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $conversation->session_id,
            role: 'assistant',
            message: $answer,
            mediaContext: [
                'message_id' => data_get($send, 'key.id') ?? data_get($send, 'messageId') ?? data_get($send, 'id'),
            ],
        );
    }

    private function sendImage(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $mockupBase64, array $state): void
    {
        $caption = 'Baskı önizlemeniz hazır ✓ Logo orijinal dosyanızdan otomatik yerleştirildi.';
        $this->markOutbound($instance, $phone, $caption);
        $send = $this->whatsAppService->sendImage(
            $instance,
            $phone,
            $mockupBase64,
            'baski-onizleme.jpg',
            $caption,
            'image/jpeg',
        );

        ChatMessage::create([
            'user_id' => $bot->user_id,
            'organization_id' => $conversation->organization_id,
            'ai_bot_id' => $bot->id,
            'session_id' => $conversation->session_id,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => '[Baskı önizlemesi] '.$this->positionLabel((string) $state['position']),
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => 'baski-onizleme.jpg',
            'media_caption' => $caption,
            'whatsapp_message_id' => data_get($send, 'key.id') ?? data_get($send, 'messageId') ?? data_get($send, 'id'),
            'status' => 'sent',
        ]);
    }

    private function conversation(AiBot $bot, string $sessionId, string $phone, array $payload): ConversationControl
    {
        $organizationId = Organization::query()->where('owner_user_id', $bot->user_id)->value('id');
        $conversation = ConversationControl::firstOrCreate(
            ['ai_bot_id' => $bot->id, 'session_id' => $sessionId],
            [
                'user_id' => $bot->user_id,
                'organization_id' => $organizationId,
                'whatsapp_number' => $phone,
                'customer_name' => null,
                'unread_count' => 0,
                'human_takeover' => false,
            ],
        );

        $pushName = trim((string) data_get($payload, 'data.pushName', ''));
        $conversation->forceFill([
            'organization_id' => $conversation->organization_id ?: $organizationId,
            'customer_name' => trim((string) $conversation->customer_name) !== ''
                ? $conversation->customer_name
                : ($pushName !== '' ? $pushName : null),
            'whatsapp_number' => $phone,
        ])->save();

        return $conversation;
    }

    private function state(mixed $value): array
    {
        return is_array($value) ? $value : [
            'product' => null,
            'quantity' => null,
            'color' => null,
            'color_label' => null,
            'position' => null,
            'print_type' => 'DTF Baskı',
            'sizes' => null,
            'logo_received' => false,
            'mockup_sent' => false,
            'approved' => false,
        ];
    }

    private function phoneNumber(array $payload): string
    {
        foreach ([
            data_get($payload, 'data.key.remoteJidAlt'),
            data_get($payload, 'data.key.remoteJid'),
            data_get($payload, 'data.sender'),
        ] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || str_ends_with($candidate, '@lid')) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', explode('@', $candidate)[0] ?? '') ?? '';
            if (strlen($digits) >= 8 && strlen($digits) <= 15) {
                return $digits;
            }
        }

        return '';
    }

    private function stateKey(AiBot $bot, string $phone): string
    {
        return 'textile_demo_state:'.$bot->id.':'.$phone;
    }

    private function approvalMessage(string $message): bool
    {
        $message = Str::lower(trim($message));
        return in_array($message, ['onaylıyorum', 'onayliyorum', 'onay', 'uygun', 'tamam onaylıyorum', 'tamam onayliyorum'], true);
    }

    private function restartMessage(string $message): bool
    {
        $message = Str::lower($message);
        return str_contains($message, 'yeni tasarım') || str_contains($message, 'baştan başla') || str_contains($message, 'bastan basla');
    }

    private function positionLabel(string $position): string
    {
        return match ($position) {
            'left_chest' => 'Sol göğüs',
            'front_large' => 'Ön büyük baskı',
            'back_large' => 'Arka büyük baskı',
            default => 'Ön orta',
        };
    }

    private function pauseAfterManualReply(AiBot $bot, string $phone): void
    {
        ConversationControl::query()
            ->where('ai_bot_id', $bot->id)
            ->where('whatsapp_number', $phone)
            ->update(['human_takeover' => true, 'updated_at' => now()]);
    }

    private function isApiOutbound(string $instance, string $phone, array $payload): bool
    {
        $message = data_get($payload, 'data.message', []);
        $text = trim((string) (
            data_get($message, 'conversation')
            ?? data_get($message, 'extendedTextMessage.text')
            ?? data_get($message, 'imageMessage.caption')
            ?? data_get($message, 'documentMessage.caption')
            ?? ''
        ));

        if ($text === '') {
            return false;
        }

        return (bool) Cache::store('database')->pull(
            'wai_api_outbound:'.sha1($instance.'|'.$phone.'|'.$text),
            false,
        );
    }

    private function markOutbound(string $instance, string $phone, string $text): void
    {
        Cache::store('database')->put('wai_api_outbound:'.sha1($instance.'|'.$phone.'|'.$text), true, now()->addMinutes(5));
    }

    private function consumeTrial(AiBot $bot): void
    {
        if ($bot->subscription_status === 'trial') {
            AiBot::query()->whereKey($bot->id)->increment('trial_messages_used');
        }
    }
}
