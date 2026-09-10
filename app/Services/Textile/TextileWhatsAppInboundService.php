<?php

namespace App\Services\Textile;

use App\Jobs\GenerateTextileMugMockup;
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

        // A greeting starts the temporary product-choice demo cleanly even if
        // the same phone tested a shirt order earlier.
        if (($mediaContext['type'] ?? 'text') === 'text' && $this->greetingMessage($message)) {
            $state = $this->state(null);
        }

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

                $mime = strtolower(trim((string) ($mediaContext['mime_type'] ?? 'image/jpeg')));

                if (($state['product_category'] ?? null) === 'mug') {
                    $state['artworks'] = array_slice(array_values((array) ($state['artworks'] ?? [])), -5);
                    $state['artworks'][] = $logo;
                    $state['mug_batch_token'] = (string) Str::uuid();
                    $state['mockup_sent'] = false;
                } else {
                    $state['logo_base64'] = $logo;
                    $state['logo_mime'] = $mime;
                    $state['logo_received'] = true;
                }

                $inbound->forceFill([
                    'media_mime_type' => $mime,
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

        if (($mediaContext['type'] ?? null) === 'image' && ($state['product_category'] ?? null) === 'mug') {
            GenerateTextileMugMockup::dispatch(
                $bot->id,
                $conversation->id,
                $instance,
                $phone,
                (string) $state['mug_batch_token'],
            )->delay(now()->addSeconds(8));

            return true;
        }

        $stateChanged = $this->stateProgressed($previousState, $state);
        $isTextMessage = ($mediaContext['type'] ?? 'text') === 'text';

        if (
            $isTextMessage
            && $stateChanged
            && ($state['customer_supplied'] ?? false)
            && ($state['quantity'] ?? null)
            && (int) $state['quantity'] < 30
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                "Baskı yapılacak tişörtleri sizin temin etmeniz durumunda minimum sipariş *30 adettir*.\n\nTişörtü bizden alırsanız minimum adet yok; *1 adet* bile hazırlayabiliriz.",
            );
            $this->consumeTrial($bot);

            return true;
        }

        if (
            $isTextMessage
            && $stateChanged
            && ($state['color'] ?? null)
            && ! in_array($state['color'], ['black', 'white'], true)
            && ($state['quantity'] ?? null)
            && (int) $state['quantity'] < 60
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                "Siyah ve beyaz dışındaki renklerde üretim minimum *60 adettir*.\n\nAdedi 60 veya üzerine çıkarabiliriz; isterseniz mevcut adette siyah ya da beyaz oversize seçeneğiyle devam edebiliriz.",
            );
            $this->consumeTrial($bot);

            return true;
        }

        $readyForMockup = ($state['logo_received'] ?? false)
            && ($state['position'] ?? null)
            && ($state['product'] ?? null)
            && ($state['quantity'] ?? null)
            && ($state['color'] ?? null)
            && ! ($state['mockup_sent'] ?? false);

        $shouldAnswerNaturally = $isTextMessage
            && ! $readyForMockup
            && ! $this->approvalMessage($message)
            && ! $this->restartMessage($message)
            && (
                $this->questionMessage($message)
                || ! $this->stateProgressed($previousState, $state)
            );

        if ($shouldAnswerNaturally) {
            if ($this->greetingMessage($message)) {
                $answer = $this->welcomeMessage();
            } elseif ($this->generalInformationRequest($message)) {
                $answer = $this->informationTopicQuestion();
            } else {
                $answer = $this->intelligentReply($bot, $conversation, $state, $message);
            }

            $this->sendText($bot, $conversation, $instance, $phone, $answer);
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
                    product: (string) ($state['product'] ?? 'Premium Oversize Tişört'),
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

            if (($state['product_category'] ?? null) === 'mug') {
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
                $this->sendText(
                    $bot,
                    $conversation,
                    $instance,
                    $phone,
                    "✅ Bardak tasarımı onaylandı.\n\nNet teklif ve sipariş kaydı için kaç adet istediğinizi yazabilirsiniz.",
                );
                $this->consumeTrial($bot);

                return true;
            }
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
                'Yeni tasarım akışını başlattım. Tişört veya bardak baskısından hangisini istediğinizi yazabilirsiniz.'
            );
            return true;
        }

        $this->sendText($bot, $conversation, $instance, $phone, $this->nextQuestion($state));
        $this->consumeTrial($bot);

        return true;
    }

    public function completeMugBatch(
        int $botId,
        int $conversationId,
        string $instance,
        string $phone,
        string $batchToken,
    ): void {
        $bot = AiBot::query()->find($botId);
        $conversation = ConversationControl::query()->find($conversationId);
        if (! $bot || ! $conversation) {
            return;
        }

        $stateKey = $this->stateKey($bot, $phone);
        $state = $this->state(Cache::store('database')->get($stateKey));
        if (
            ($state['product_category'] ?? null) !== 'mug'
            || ($state['mug_batch_token'] ?? null) !== $batchToken
            || ($state['mockup_sent'] ?? false)
            || empty($state['artworks'])
        ) {
            return;
        }

        try {
            $mockup = $this->mockupService->createMug((array) $state['artworks']);
            $this->sendImage($bot, $conversation, $instance, $phone, $mockup, $state);
            $state['mockup_sent'] = true;
            Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
            $this->sendText($bot, $conversation, $instance, $phone, $this->mockupMessage($state));
            $this->consumeTrial($bot);
        } catch (Throwable $exception) {
            Log::error('TEXTILE MUG MOCKUP FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);

            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                'Bardak görsellerinizi aldım ancak önizleme hazırlanırken geçici bir sorun oluştu. Lütfen “önizlemeyi tekrar hazırla” yazın.',
            );
        }
    }

    private function parseText(array $state, string $message): array
    {
        $lower = Str::lower($message);

        if (preg_match('/\b([1-9][0-9]{0,4})\s*(?:adet|tane)\b/u', $lower, $match)) {
            $state['quantity'] = min(50000, (int) $match[1]);
        }

        $products = [
            'sihirli bardak' => ['Sihirli Siyah Kulplu Kupa Bardak', 'mug'],
            'polyester şapka' => ['Polyester Şapka', 'cap'],
            'polyester sapka' => ['Polyester Şapka', 'cap'],
            'pamuklu şapka' => ['Pamuklu Şapka', 'cap'],
            'pamuklu sapka' => ['Pamuklu Şapka', 'cap'],
            'kapüşonlu sweatshirt' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'kapusonlu sweatshirt' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'kapüşonlu sweat' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'kapusonlu sweat' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'sweatshirt' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'sweatşört' => ['Kapüşonlu Sweatshirt', 'hoodie'],
            'polo yaka' => ['Polo Yaka Tişört', 'shirt'],
            'poloyaka' => ['Polo Yaka Tişört', 'shirt'],
            'polo' => ['Polo Yaka Tişört', 'shirt'],
            'oversize' => ['Premium Oversize Tişört', 'shirt'],
            'regular' => ['Regular Fit Tişört', 'shirt'],
            'bisiklet yaka' => ['Regular Fit Tişört', 'shirt'],
            'heavy' => ['Heavy Cotton Tişört', 'shirt'],
            'şapka' => ['Pamuklu Şapka', 'cap'],
            'sapka' => ['Pamuklu Şapka', 'cap'],
            'tişört' => ['Premium Oversize Tişört', 'shirt'],
            'tisort' => ['Premium Oversize Tişört', 'shirt'],
            'kupa' => ['Sihirli Siyah Kulplu Kupa Bardak', 'mug'],
            'bardak' => ['Sihirli Siyah Kulplu Kupa Bardak', 'mug'],
        ];
        foreach ($products as $needle => [$label, $category]) {
            if (str_contains($lower, $needle)) {
                $state['product'] = $label;
                $state['product_category'] = $category;
                if ($category === 'mug') {
                    $state['color'] = 'black';
                    $state['color_label'] = 'Siyah';
                }
                break;
            }
        }

        $colors = [
            'siyah' => 'black', 'beyaz' => 'white', 'lacivert' => 'navy',
            'bordo' => 'burgundy', 'bej' => 'beige', 'kırmızı' => 'red',
            'kirmizi' => 'red', 'mavi' => 'blue', 'turkuaz' => 'turquoise',
            'yeşil' => 'green', 'yesil' => 'green', 'sarı' => 'yellow',
            'sari' => 'yellow', 'turuncu' => 'orange', 'pembe' => 'pink',
            'kahverengi' => 'brown', 'gri' => 'gray', 'füme' => 'charcoal',
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

        $customerSuppliedPhrases = [
            'ben getireceğim', 'ben getirecegim', 'biz getireceğiz', 'biz getirecegiz',
            'kendim temin', 'kendimiz temin', 'ürünleri biz', 'urunleri biz',
            'tişörtler benden', 'tisortler benden', 'müşteri temin', 'musteri temin',
            'tişörtleri biz getirsek', 'tisortleri biz getirsek', 'biz getirsek',
            'tişörtleri ben getirsem', 'tisortleri ben getirsem', 'ben getirsem',
        ];
        foreach ($customerSuppliedPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $state['customer_supplied'] = true;
                break;
            }
        }

        if (
            str_contains($lower, 'tişört dahil')
            || str_contains($lower, 'tisort dahil')
            || str_contains($lower, 'tişört sizden')
            || str_contains($lower, 'tisort sizden')
        ) {
            $state['customer_supplied'] = false;
        }

        if (str_contains($lower, 'serigraf')) {
            $state['print_type'] = 'Tek Renk Serigrafi';
        } elseif (str_contains($lower, 'dtf')) {
            $state['print_type'] = 'DTF Baskı';
        }

        if (preg_match('/\b(s|m|l|xl|xxl)(?:\s*[-–\/]\s*(s|m|l|xl|xxl))?\b/iu', $message, $sizeMatch)) {
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

        $hasQuantity = (bool) preg_match('/\b[1-9][0-9]{0,4}\s*(?:adet|tane)\b/u', $normalized);
        $hasProduct = str_contains($normalized, 'tişört')
            || str_contains($normalized, 'tisort')
            || str_contains($normalized, 'oversize')
            || str_contains($normalized, 'polo')
            || str_contains($normalized, 'regular')
            || str_contains($normalized, 'heavy')
            || str_contains($normalized, 'bardak')
            || str_contains($normalized, 'kupa');

        return $hasQuantity || $hasProduct;
    }

    private function greetingMessage(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        return (bool) preg_match(
            '/^(merhaba|merhabalar|selam|selamlar|günaydın|gunaydin|iyi günler|iyi aksamlar|iyi akşamlar|iyi geceler)[!. ]*$/u',
            $normalized,
        );
    }

    private function generalInformationRequest(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        foreach ([
            'ürünleriniz hakkında bilgi',
            'urunleriniz hakkinda bilgi',
            'ürünler hakkında bilgi',
            'urunler hakkinda bilgi',
            'firmanız hakkında bilgi',
            'firmaniz hakkinda bilgi',
            'şirketiniz hakkında bilgi',
            'sirketiniz hakkinda bilgi',
            'hizmetleriniz hakkında bilgi',
            'hizmetleriniz hakkinda bilgi',
            'genel bilgi almak',
            'bilgi almak istiyorum',
            'neler yapıyorsunuz',
            'neler yapiyorsunuz',
            'ne hizmet veriyorsunuz',
        ] as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function informationTopicQuestion(): string
    {
        return "Elbette, memnuniyetle yardımcı olayım. Hangi konuda bilgi almak istersiniz?\n\n*Ürün çeşitleri, kumaş ve kaliteler, baskı yöntemleri, fiyatlandırma, minimum adet veya teslimat* konularından birini yazabilirsiniz.";
    }

    private function welcomeMessage(): string
    {
        return "Merhaba 👋\n\n*Baskılı tekstil siparişinizi birlikte hazırlayalım.*\n\nÜrün modelini, rengi ve adedi tek mesajda yazabilirsiniz.\nÖrnek: *250 adet siyah oversize tişört.*";
    }

    private function questionMessage(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        if (str_contains($normalized, '?')) {
            return true;
        }

        return (bool) preg_match(
            '/\b(ne|nedir|neden|nasıl|nasil|hangi|hangisi|kaç|kac|kim|nerede|nereye|ne zaman|olur mu|var mı|var mi|mi|mı|mu|mü|misiniz|mısınız|musunuz|müsünüz)\b/u',
            $normalized,
        );
    }

    private function stateProgressed(array $before, array $after): bool
    {
        foreach ([
            'product',
            'product_category',
            'artworks',
            'quantity',
            'color',
            'position',
            'print_type',
            'sizes',
            'logo_received',
            'mockup_sent',
            'approved',
            'customer_supplied',
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
        $existingInstructions = trim((string) $bot->system_prompt);
        $liveContext = $this->liveConversationInstructions($state);

        $contextBot->setAttribute(
            'system_prompt',
            trim($existingInstructions."\n\n".$liveContext),
        );

        $answer = trim($this->openAIService->cevapVer($history, $contextBot));
        $answer = str_replace(
            ['\\r\\n', '\\n', '\\r'],
            ["\n", "\n", "\n"],
            $answer,
        );

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
            'Ürün kategorisi' => $state['product_category'] ?? 'henüz belirtilmedi',
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
            'Tişört kaynağı' => ($state['customer_supplied'] ?? null) === true
                ? 'müşteri kendi ürününü temin edecek'
                : (($state['customer_supplied'] ?? null) === false ? 'tişört firmadan alınacak' : 'henüz belirtilmedi'),
        ];

        $lines = collect($status)
            ->map(fn (mixed $value, string $label): string => "- {$label}: {$value}")
            ->implode("\n");

        return <<<PROMPT
CANLI TEKSTİL SİPARİŞ BAĞLAMI
{$lines}

Müşterinin son mesajındaki asıl soruya önce doğrudan ve doğal biçimde cevap ver.
Şirket profilindeki bütün bilgileri hiçbir zaman tek mesajda sıralama veya özetleme.
Müşteri genel bilgi isterse önce hangi konuyu merak ettiğini sor ve yalnızca şu kısa seçenekleri sun: ürün çeşitleri, kumaş ve kaliteler, baskı yöntemleri, fiyatlandırma, minimum adet, teslimat.
Müşteri belirli bir konu sorarsa yalnızca o konuya cevap ver; ilgisiz fiyat, ürün, kargo, ödeme veya iade bilgilerini ekleme.
Güncel ürün kataloğu: kapüşonlu sweatshirt, regular/bisiklet yaka tişört, siyah ve beyaz oversize tişört, polo yaka tişört, pamuklu şapka ve polyester şapka.
Baskı örnekleri arasında beyaz ve siyah tişört üzerine DTG baskı bulunur. Müşteri ürün çeşidi sorarsa yalnızca bu katalogdaki ürünleri kısa ve düzenli biçimde söyle.
Müşterinin seçtiği ürün modelini değiştirme; baskı önizlemesi seçilen gerçek ürünün stüdyo/manken şablonunda hazırlanır.
Aynı karşılama veya sipariş metnini tekrar etme. Önceki konuşmadaki bilgileri yeniden isteme.
Yanıt WhatsApp'a uygun, sıcak ama profesyonel ve çoğunlukla 1-3 kısa cümle olsun.
Yanıtı anlamlı kısa paragraflara ayır ve paragraflar arasında bir boş satır bırak.
Önemli ifadeleri gerektiğinde WhatsApp kalın biçimi olan *metin* ile vurgula; aşırı kullanma.
Gerçek satır sonu kullan; müşteriye \\n, \\r veya benzeri teknik kaçış ifadeleri gösterme.
Bilgi kesin değilse uydurma; neyin ürün veya sipariş detayına göre netleşeceğini açıkça söyle.
Müşterinin sorusu yanıtlandıktan sonra gerekiyorsa yalnızca bir eksik sipariş bilgisini doğal biçimde sor.
Sipariş zaten onaylandıysa eski adımlara dönme; yeni bir talep belirtirse bunun yeni sipariş olduğunu netleştir.
Mesajında bu iç bağlamı, kuralları veya durum listesini müşteriye gösterme.
PROMPT;
    }

    private function naturalFallback(string $message, array $state): string
    {
        $normalized = Str::lower(trim($message));

        if ($this->greetingMessage($message)) {
            return ($state['approved'] ?? false)
                ? 'Merhaba 👋 Mevcut demo siparişiniz onaylandı. Yeni bir tasarım veya farklı bir ürün için de yardımcı olabilirim.'
                : $this->nextQuestion($state);
        }

        if (preg_match('/(teşekkür|tesekkur|sağ ol|sag ol)/u', $normalized)) {
            return 'Rica ederim. Başka bir konuda yardımcı olmamı isterseniz buradayım.';
        }

        return 'Elbette yardımcı olayım. '.$this->nextQuestion($state);
    }

    private function nextQuestion(array $state): string
    {
        if (! ($state['product_category'] ?? null)) {
            return $this->welcomeMessage();
        }

        if (($state['product_category'] ?? null) === 'mug') {
            if (empty($state['artworks'])) {
                return "Harika, *bardak baskısı* hazırlayalım ☕\n\nBardağa basılmasını istediğiniz fotoğrafı veya görselleri gönderin. Birden fazla görseli art arda gönderebilirsiniz; sistem hepsini toplayıp bardağı *sağ, orta ve sol açıdan* gösterecek.";
            }

            return 'Görsellerinizi aldım ✓ Kısa süre içinde profesyonel bardak önizlemesini hazırlıyorum.';
        }

        $hasAnyCoreDetail = ($state['product'] ?? null)
            || ($state['quantity'] ?? null)
            || ($state['color'] ?? null);

        if (! $hasAnyCoreDetail) {
            return "Tişört baskısı için ürün modelini, rengi ve adedi tek mesajda yazabilirsiniz.\n\nÖrnek: *250 adet siyah oversize tişört.*";
        }

        $missing = [];
        if (! ($state['product'] ?? null)) {
            $missing[] = 'ürün modeli';
        }
        if (! ($state['color'] ?? null)) {
            $missing[] = 'renk';
        }
        if (! ($state['quantity'] ?? null)) {
            $missing[] = 'adet';
        }

        if ($missing !== []) {
            return 'Siparişinizi netleştirelim. Lütfen *'.implode(', ', $missing).'* bilgisini paylaşır mısınız?';
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
        if (($state['product_category'] ?? null) === 'mug') {
            $count = count((array) ($state['artworks'] ?? []));
            $view = $count > 1 ? 'sağ, orta ve sol açılardan' : 'tek ürün görünümüyle';

            return "Bardak baskı önizlemeniz hazırlandı ✓\n\n"
                ."Gönderdiğiniz ".($count > 1 ? "{$count} görsel" : 'görsel')." bardağın doğal yüzey kıvrımı, ışığı ve parlaklığı korunarak yerleştirildi. Tasarım *{$view}* sunuldu.\n\n"
                .'Görsel uygunsa *Onaylıyorum* yazabilirsiniz. Fiyatlandırma için adet bilgisini ayrıca paylaşmanız yeterli.';
        }

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
        $quantity = max(1, (int) ($state['quantity'] ?? 250));
        $position = (string) ($state['position'] ?? 'front_center');
        $color = (string) ($state['color'] ?? 'black');

        if ($quantity === 1) {
            return [750, 750, 'Numune — kargo dahil'];
        }

        if (
            $quantity >= 5
            && $quantity <= 30
            && $color === 'white'
            && in_array($position, ['left_chest', 'front_center', 'front_large'], true)
        ) {
            return [300, 300 * $quantity, 'Sipariş detaylarına göre netleşir'];
        }

        $product = (string) ($state['product'] ?? 'Premium Oversize Tişört');
        $base = match ($product) {
            'Regular Fit Tişört' => 135,
            'Polo Yaka Tişört' => 185,
            'Heavy Cotton Tişört' => 205,
            default => 155,
        };
        $print = ($state['print_type'] ?? 'DTF Baskı') === 'Tek Renk Serigrafi' ? 26 : 34;
        $discount = $quantity >= 1000 ? .14 : ($quantity >= 500 ? .10 : ($quantity >= 250 ? .06 : ($quantity >= 100 ? .03 : 0)));
        $unit = (int) round(($base + $print) * (1 - $discount));

        return [$unit, $unit * $quantity, '7–9 iş günü'];
    }

    private function sendText(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $answer): void
    {
        $answer = trim(str_replace(
            ['\\r\\n', '\\n', '\\r'],
            ["\n", "\n", "\n"],
            $answer,
        ));
        $answer = preg_replace("/\\n{3,}/", "\n\n", $answer) ?? $answer;

        $this->markOutbound($instance, $phone, $answer);

        $send = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $send = $this->whatsAppService->sendText($instance, $phone, $answer);
                break;
            } catch (Throwable $exception) {
                $lastException = $exception;

                Log::warning('TEXTILE WHATSAPP SEND RETRY', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'attempt' => $attempt,
                    'message' => $exception->getMessage(),
                ]);

                if ($attempt < 3) {
                    usleep(250000 * $attempt);
                }
            }
        }

        if (! is_array($send)) {
            throw $lastException ?? new \RuntimeException('WhatsApp mesajı üç denemede gönderilemedi.');
        }

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
        $isMug = ($state['product_category'] ?? null) === 'mug';
        $caption = $isMug
            ? 'Bardak baskı önizlemeniz hazır ✓ Görselleriniz bardak yüzeyine doğal biçimde uygulandı.'
            : 'Baskı önizlemeniz hazır ✓ Logo orijinal dosyanızdan otomatik yerleştirildi.';
        $filename = $isMug ? 'bardak-baski-onizleme.jpg' : 'baski-onizleme.jpg';
        $this->markOutbound($instance, $phone, $caption);
        $send = $this->whatsAppService->sendImage(
            $instance,
            $phone,
            $mockupBase64,
            $filename,
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
            'message' => $isMug ? '[Bardak baskı önizlemesi]' : '[Baskı önizlemesi] '.$this->positionLabel((string) $state['position']),
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => $filename,
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
            'product_category' => null,
            'artworks' => [],
            'mug_batch_token' => null,
            'quantity' => null,
            'color' => null,
            'color_label' => null,
            'position' => null,
            'print_type' => 'DTF Baskı',
            'sizes' => null,
            'logo_received' => false,
            'mockup_sent' => false,
            'approved' => false,
            'customer_supplied' => null,
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
