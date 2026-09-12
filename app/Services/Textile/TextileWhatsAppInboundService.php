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
use Illuminate\Support\Str;
use Throwable;

class TextileWhatsAppInboundService
{
    private const STATE_TTL_HOURS = 24;

    public function __construct(
        private readonly RealEstateWhatsAppMessageParser $messageParser,
        private readonly EvolutionMediaService $mediaService,
        private readonly TextileMockupService $mockupService,
        private readonly TextileAttachmentService $attachmentService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MemoryService $memoryService,
        private readonly OpenAIService $openAIService,
    ) {
    }

    public function processPayload(array $payload, bool $buffered = false): bool
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

        // Buffer plain text only; keep original attachment envelopes intact.
        $text = trim((string) (data_get($payload, 'data.message.conversation')
            ?? data_get($payload, 'data.message.extendedTextMessage.text') ?? ''));
        if (! $buffered && $text !== '') {
            $key = 'textile_text_burst:'.$bot->id.':'.$phone;
            $cache = Cache::store('database');
            $cache->lock($key.':lock', 15)->block(5, function () use ($cache, $key, $payload, $text) {
                $messageId = trim((string) data_get($payload, 'data.key.id', ''));
                if ($messageId !== '' && ! $cache->add($key.':seen:'.$messageId, true, now()->addDay())) {
                    return;
                }
                $batch = $cache->get($key);
                $new = ! is_array($batch);
                $batch = $new ? ['id' => (string) Str::uuid(), 'texts' => [], 'payload' => $payload] : $batch;
                $batch['texts'][] = $text;
                $batch['updated_at'] = microtime(true);
                $cache->put($key, $batch, now()->addDay());
                if ($new) {
                    \App\Jobs\ProcessTextileTextBurst::dispatch($key)->delay(now()->addSeconds(10));
                }
            });
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

        // Legacy mug sessions belong to the future standalone mug bot and
        // must never continue inside the textile-only assistant.
        if (($state['product_category'] ?? null) === 'mug') {
            $state = $this->state(null);
        }

        // A greeting starts the textile order flow cleanly even if
        // the same phone tested a shirt order earlier.
        if (($mediaContext['type'] ?? 'text') === 'text' && $this->greetingMessage($message)) {
            $state = $this->state(null);
        }

        if (($state['approved'] ?? false) && $this->newOrderDetailsMessage($message)) {
            $state = $this->state(null);
        }

        if ((int) $bot->id === 53 && (int) $bot->user_id === 47) {
            $state['bekir_catalogue'] = true;
        }

        $previousState = $state;
        $state = $this->parseText($state, trim((string) ($mediaContext['caption'] ?: $message)));

        // Bekir Tekstil: müşteriler adet sorusuna çoğu zaman yalnızca "100" gibi
        // yalın bir sayı ile cevap veriyor. Bu cevabı sipariş adedi olarak kaydet.
        if (
            ((int) $bot->id === 53 && (int) $bot->user_id === 47)
            && ($state['product'] ?? null)
            && ! ($state['quantity'] ?? null)
            && preg_match('/^\s*([1-9][0-9]{0,4})\s*$/u', trim($message), $bekirQuantity)
        ) {
            $state['quantity'] = min(50000, (int) $bekirQuantity[1]);
            $state['mockup_sent'] = false;
            $state['approved'] = false;
        }

        $attachmentReply = null;
        $mediaType = (string) ($mediaContext['type'] ?? 'text');
        if (in_array($mediaType, ['image', 'document'], true)) {
            try {
                $encodedFile = $this->mediaService->downloadBase64(
                    instanceName: $instance,
                    messageEnvelope: is_array($mediaContext['message_envelope'] ?? null)
                        ? $mediaContext['message_envelope']
                        : [],
                );

                $bytes = base64_decode($encodedFile, true);
                if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 15 * 1024 * 1024) {
                    throw new \RuntimeException('Dosya güvenli sınırı aşıyor veya çözülemedi.');
                }

                $mime = strtolower(trim((string) ($mediaContext['mime_type'] ?? 'application/octet-stream')));
                $filename = trim((string) ($mediaContext['filename'] ?? 'dosya'));
                $analysis = $this->attachmentService->inspect(
                    base64: $encodedFile,
                    mime: $mime,
                    filename: $filename,
                    bot: $bot,
                );

                $documentText = trim((string) ($analysis['order_text'] ?? ''));
                if ($documentText !== '') {
                    $state = $this->parseText($state, $documentText);
                }

                $documentItems = is_array($analysis['order_items'] ?? null)
                    ? $analysis['order_items']
                    : [];
                if ($documentItems !== []) {
                    $state['order_items'] = array_slice(array_merge(
                        is_array($state['order_items'] ?? null) ? $state['order_items'] : [],
                        $documentItems,
                    ), -20);
                    if (count($documentItems) > 1) {
                        $state['awaiting_order_item_selection'] = true;
                    }
                }

                $hasPrintableArtwork = (bool) ($analysis['contains_printable_artwork'] ?? false);
                if (($analysis['kind'] ?? null) === 'order_document' && ! $hasPrintableArtwork) {
                    $summary = trim((string) ($analysis['summary'] ?? ''));
                    $attachmentReply = "Belgenizi okudum ✓";
                    if ($summary !== '') {
                        $attachmentReply .= "\n\n".$summary;
                    }
                    if ($state['awaiting_order_item_selection'] ?? false) {
                        $attachmentReply .= "\n\nBelgede *".count($documentItems)." ayrı ürün kalemi* buldum. Hepsini aynı sipariş altında tutacağım. Önce hangi ürünün baskı önizlemesini hazırlayalım?";
                    } else {
                        $attachmentReply .= "\n\n".$this->nextQuestion($state);
                    }
                } else {
                    $artwork = trim((string) ($analysis['artwork_base64'] ?? $encodedFile));
                    $pendingPosition = trim((string) ($state['awaiting_additional_image_position'] ?? ''));

                    if ($pendingPosition !== '') {
                        $state['additional_prints'] = is_array($state['additional_prints'] ?? null)
                            ? $state['additional_prints']
                            : [];
                        $state['additional_prints'][] = [
                            'position' => $pendingPosition,
                            'logo_base64' => $artwork,
                            'logo_mime' => 'image/png',
                        ];
                        $state['additional_prints'] = array_slice($state['additional_prints'], -8);
                        $state['awaiting_additional_image_position'] = null;
                        $state['pending_position'] = null;
                        $state['mockup_sent'] = false;
                        $state['approved'] = false;
                    } elseif ($state['logo_received'] ?? false) {
                        $state['pending_uploaded_artwork'] = [
                            'logo_base64' => $artwork,
                            'logo_mime' => $mime,
                        ];
                        $state['awaiting_uploaded_artwork_position'] = true;
                        $state['mockup_sent'] = false;
                        $state['approved'] = false;
                        $attachmentReply = "Yeni baskı görselinizi de aldım ✓\n\nBu görseli hangi alanda kullanalım? Örneğin: *ön orta, ön sol göğüs, arka büyük, sağ kol veya sol kol*.";
                    } else {
                        $state['logo_base64'] = $artwork;
                        $state['logo_mime'] = $mime;
                        $state['logo_received'] = true;
                        $state['mockup_sent'] = false;
                        $state['approved'] = false;

                        // Positions mentioned together before the artwork (for
                        // example “sırt ve ön sol”) use this same first artwork.
                        foreach ((array) ($state['pending_same_artwork_positions'] ?? []) as $pendingPosition) {
                            if ($pendingPosition === ($state['position'] ?? null)) {
                                continue;
                            }
                            $state['additional_prints'][] = [
                                'position' => $pendingPosition,
                                'logo_base64' => $artwork,
                                'logo_mime' => $mime,
                            ];
                        }
                        $state['additional_prints'] = array_slice(
                            array_values($state['additional_prints'] ?? []),
                            -8,
                        );
                        $state['pending_same_artwork_positions'] = [];
                    }
                }

                $inbound->forceFill([
                    'media_mime_type' => $mime,
                    'media_size' => strlen($bytes),
                ])->saveQuietly();
            } catch (Throwable $exception) {
                Log::warning('TEXTILE ATTACHMENT FAILED', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'message' => $exception->getMessage(),
                ]);

                $this->sendText(
                    $bot,
                    $conversation,
                    $instance,
                    $phone,
                    'Dosyanızı aldım ancak güvenli şekilde okuyamadım. JPG, PNG, WEBP, PDF veya Word dosyası olarak tekrar gönderebilir misiniz?'
                );
                return true;
            }
        }

        if ((int) $bot->id === 53 && (int) $bot->user_id === 47) {
            $state['bekir_catalogue'] = true;
            if (($state['product'] ?? null) === 'Regular Fit Tişört') {
                $state['product'] = 'Sıfır Yaka Tişört';
            }
            if (($state['product'] ?? null) && ! in_array($state['product'], ['Sıfır Yaka Tişört', 'Polo Yaka Tişört', 'Premium Oversize Tişört'], true)) {
                $state['product'] = null;
                $state['product_category'] = null;
                $state['mockup_sent'] = false;
                Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
                $this->sendText($bot, $conversation, $instance, $phone,
                    'Bekir Tekstil’de sıfır yaka, polo yaka ve oversize tişörtle ilerliyoruz. Hangisini tercih edersiniz?');
                $this->consumeTrial($bot);
                return true;
            }
        }

        Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));

        if ($attachmentReply !== null) {
            $this->sendText($bot, $conversation, $instance, $phone, $attachmentReply);
            $this->consumeTrial($bot);
            return true;
        }

        $stateChanged = $this->stateProgressed($previousState, $state);
        $isTextMessage = $mediaType === 'text';

        if (
            $isTextMessage
            && ! ($state['bekir_catalogue'] ?? false)
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
            && ! ($state['bekir_catalogue'] ?? false)
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
            && (($state['bekir_catalogue'] ?? false) || ($state['quantity'] ?? null))
            && ($state['color'] ?? null)
            && ! ($state['mockup_sent'] ?? false);

        if (
            $isTextMessage
            && ($previousState['awaiting_uploaded_artwork_position'] ?? false)
            && ! ($state['awaiting_uploaded_artwork_position'] ?? false)
            && count((array) ($state['additional_prints'] ?? [])) > count((array) ($previousState['additional_prints'] ?? []))
        ) {
            $newPrints = array_slice(
                (array) $state['additional_prints'],
                count((array) ($previousState['additional_prints'] ?? [])),
            );
            $labels = array_values(array_unique(array_map(
                fn (array $print): string => $this->positionLabel((string) ($print['position'] ?? 'front_center')),
                $newPrints,
            )));
            $answer = 'Tamam, yeni gönderdiğiniz görseli *'.implode('* ve *', $labels).'* alanlarında kullanacağım. Önizlemeyi buna göre güncelliyorum.';
            $this->sendText($bot, $conversation, $instance, $phone, $answer);
        }

        // Bekir Tekstil: ürün + adet alındıktan sonra gerçekçi mockup için renk
        // mutlaka bilinmeli. Logo geldiyse de renk eksikken genel GPT cevabına
        // düşme; doğrudan tek eksik bilgiyi sor.
        if (
            ((int) $bot->id === 53 && (int) $bot->user_id === 47)
            && ($state['product'] ?? null)
            && ($state['quantity'] ?? null)
            && ! ($state['color'] ?? null)
        ) {
            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                ($state['logo_received'] ?? false)
                    ? "Logonuzu aldım ✓\n\nTişört rengini de yazar mısınız? Önizlemeyi seçtiğiniz renk üzerinde hazırlayayım."
                    : "Kaç adet olacağını aldım. Tişört rengini de yazar mısınız? Örneğin: *siyah, beyaz, lacivert* gibi."
            );
            $this->consumeTrial($bot);
            return true;
        }

        if (! ((int) $bot->id === 53 && (int) $bot->user_id === 47) && $isTextMessage && ($pricingAnswer = $this->pricingReply($message, $state)) !== null) {
            $this->sendText($bot, $conversation, $instance, $phone, $pricingAnswer);
            $this->consumeTrial($bot);
            return true;
        }

        // Let the assistant lead every ordinary text exchange naturally.
        // Parsing still records useful details, but mentioning a product alone must
        // never force the customer into a rigid checkout questionnaire.
        $shouldAnswerNaturally = $isTextMessage
            && ! $readyForMockup
            && ! (($state['bekir_catalogue'] ?? false) && ($state['logo_received'] ?? false) && ! ($state['mockup_sent'] ?? false))
            && ! $this->approvalMessage($message)
            && ! $this->restartMessage($message);

        if ($shouldAnswerNaturally) {
            if ($this->greetingMessage($message)) {
                $answer = ((int) $bot->id === 53 && (int) $bot->user_id === 47)
                    ? "Merhaba 👋 Bekir Tekstil'e hoş geldiniz. Nasıl yardımcı olabilirim?"
                    : $this->welcomeMessage();
            } elseif ($this->generalInformationRequest($message)) {
                $answer = $this->informationTopicQuestion();
            } elseif (! ($state['bekir_catalogue'] ?? false) && $this->productRecommendationRequest($message)) {
                $answer = "Markanız daha rahat ve sokak giyim çizgisindeyse *oversize*, daha klasik ve geniş kullanım içinse *regular/bisiklet yaka* iyi bir başlangıç olur.\n\nTasarım çizginiz daha çok sokak stili mi, yoksa sade ve klasik mi?";
            } else {
                $answer = $this->intelligentReply($bot, $conversation, $state, $message);
            }

            $answer = $this->protectInternalPricing($answer, $message, $state);

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
            && (($state['bekir_catalogue'] ?? false) || ($state['quantity'] ?? null))
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
                if ((int) $bot->id === 53 && (int) $bot->user_id === 47) {
                    $views = $this->mockupService->createViews(
                        logoBase64: (string) $state['logo_base64'],
                        position: (string) $state['position'],
                        shirtColor: (string) $state['color'],
                        product: (string) $state['product'],
                        additionalPrints: (array) ($state['additional_prints'] ?? []),
                    );
                    foreach ($views as $preview) {
                        $viewState = array_replace($state, ['position' => $preview['position']]);
                        $this->sendImage($bot, $conversation, $instance, $phone, $preview['image'], $viewState, $preview['view']);
                    }
                } else {
                $mockup = $this->mockupService->create(
                    logoBase64: (string) $state['logo_base64'],
                    position: (string) $state['position'],
                    shirtColor: (string) ($state['color'] ?? 'black'),
                    product: (string) ($state['product'] ?? 'Premium Oversize Tişört'),
                    additionalPrints: is_array($state['additional_prints'] ?? null)
                        ? $state['additional_prints']
                        : [],
                );

                $this->sendImage($bot, $conversation, $instance, $phone, $mockup, $state);
                }
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

        if ($this->approvalMessage($message)) {
            if (! ($state['mockup_sent'] ?? false)) {
                $this->sendText(
                    $bot,
                    $conversation,
                    $instance,
                    $phone,
                    "Siparişi onaylamadan önce güncel baskı önizlemesini hazırlamamız gerekiyor.\n\n".$this->nextQuestion($state),
                );
                return true;
            }

            $state['approved'] = true;
            $state['order_id'] ??= 'TX-'.now()->format('ymd').'-'.strtoupper(Str::random(4));
            $state['instance'] = $instance;
            $state['phone'] = $phone;
            $state['bot_id'] = $bot->id;
            $state['conversation_id'] = $conversation->id;
            $state['session_id'] = $sessionId;

            Cache::store('database')->put($stateKey, $state, now()->addHours(self::STATE_TTL_HOURS));
            Cache::store('database')->put('textile_demo_order:'.$state['order_id'], $state, now()->addHours(2));

            $quote = $this->quote($state);
            if ($quote === null) {
                $conversation->forceFill(['human_takeover' => true])->save();
                $this->sendText(
                    $bot,
                    $conversation,
                    $instance,
                    $phone,
                    "✅ Tasarım ve sipariş talebiniz alındı.\n\nSipariş No: *#{$state['order_id']}*\n\nÜrün, adet ve baskı alanlarınıza göre net fiyatı satış ekibimiz kontrol edip size iletecek. Tanımlı olmayan bir fiyatı otomatik olarak tahmin etmiyoruz.",
                );
                $this->consumeTrial($bot);
                return true;
            }

            $this->sendText($bot, $conversation, $instance, $phone,
                $this->paymentMessage($state)
            );
            $this->consumeTrial($bot);
            return true;
        }

        if ($this->restartMessage($message)) {
            Cache::store('database')->forget($stateKey);
            $this->sendText($bot, $conversation, $instance, $phone,
                'Yeni tekstil siparişi akışını başlattım. Ürün modelini, rengi ve adedi tek mesajda yazabilirsiniz.'
            );
            return true;
        }

        $this->sendText($bot, $conversation, $instance, $phone, $this->nextQuestion($state));
        $this->consumeTrial($bot);

        return true;
    }

    private function parseText(array $state, string $message): array
    {
        $lower = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], $message));

        if (($state['awaiting_additional_artwork_choice'] ?? false) && $this->sameArtworkMessage($lower)) {
            $pending = trim((string) ($state['pending_position'] ?? ''));
            if ($pending !== '' && ($state['logo_received'] ?? false)) {
                $state['additional_prints'] = is_array($state['additional_prints'] ?? null)
                    ? $state['additional_prints']
                    : [];
                $state['additional_prints'][] = [
                    'position' => $pending,
                    'logo_base64' => (string) $state['logo_base64'],
                    'logo_mime' => (string) ($state['logo_mime'] ?? 'image/png'),
                ];
                $state['additional_prints'] = array_slice($state['additional_prints'], -8);
                $state['pending_position'] = null;
                $state['awaiting_additional_artwork_choice'] = false;
                $state['mockup_sent'] = false;
                $state['approved'] = false;
            }
            return $state;
        }

        if (($state['awaiting_additional_artwork_choice'] ?? false) && $this->differentArtworkMessage($lower)) {
            $state['awaiting_additional_image_position'] = $state['pending_position'] ?? null;
            $state['awaiting_additional_artwork_choice'] = false;
            return $state;
        }

        $products = [
            "sıfır yaka" => ["Sıfır Yaka Tişört", "shirt"],
            "sifir yaka" => ["Sıfır Yaka Tişört", "shirt"],
            "polar" => ["Polar", "other"],
            "mont" => ["Mont", "other"],
            "bez çanta" => ["Bez Çanta", "other"],
            "bez canta" => ["Bez Çanta", "other"],
            "çanta" => ["Çanta", "other"],
            "canta" => ["Çanta", "other"],
            "pantolon" => ["Pantolon", "other"],
            'uzun kollu tişört' => ['Uzun Kollu Tişört', 'shirt'],
            'uzun kollu tisort' => ['Uzun Kollu Tişört', 'shirt'],
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
        ];

        $detectedProduct = null;
        foreach ($products as $needle => [$label, $category]) {
            if (preg_match('/(?<![\\pL\\pN])'.preg_quote($needle, '/').'(?![\\pL\\pN])/u', $lower)) {
                $detectedProduct = [$label, $category];
                break;
            }
        }

        $hasExplicitQuantity = (bool) preg_match('/\\b([1-9][0-9]{0,4})\\s*(?:adet|tane)\\b/u', $lower, $quantityMatch);
        if (! $hasExplicitQuantity && preg_match("/^\s*([1-9][0-9]{0,4})\s*$/u", $lower, $standaloneQuantity)) {
            $hasExplicitQuantity = true;
            $quantityMatch = [null, (int) $standaloneQuantity[1]];
        }
        if (
            ! $hasExplicitQuantity
            && $detectedProduct !== null
            && preg_match('/(?<![\\pL\\pN])(?:tek\\s+bir|tek\\s+adet|bir\\s+adet)(?![\\pL\\pN])/u', $lower)
        ) {
            $hasExplicitQuantity = true;
            $quantityMatch = [null, 1];
        }
        $colors = [
            'siyah' => 'black', 'beyaz' => 'white', 'lacivert' => 'navy',
            'bordo' => 'burgundy', 'bej' => 'beige', 'kırmızı' => 'red',
            'kirmizi' => 'red', 'mavi' => 'blue', 'turkuaz' => 'turquoise',
            'yeşil' => 'green', 'yesil' => 'green', 'sarı' => 'yellow',
            'sari' => 'yellow', 'turuncu' => 'orange', 'pembe' => 'pink',
            'kahverengi' => 'brown', 'gri' => 'gray', 'füme' => 'charcoal',
        ];
        $detectedColor = null;
        foreach ($colors as $needle => $value) {
            if (str_contains($lower, $needle)) {
                $detectedColor = [$value, ucfirst($needle)];
                break;
            }
        }

        if ($detectedProduct !== null && ($state['product'] ?? null) !== $detectedProduct[0]) {
            if (
                ($state['product'] ?? null)
                && (($state['quantity'] ?? null) || ($state['mockup_sent'] ?? false))
            ) {
                $state['order_items'] = is_array($state['order_items'] ?? null)
                    ? $state['order_items']
                    : [];
                $state['order_items'][] = $this->currentOrderItem($state);
                $state['order_items'] = array_slice($state['order_items'], -20);
            }

            $state['product'] = $detectedProduct[0];
            $state['product_category'] = $detectedProduct[1];
            $state['awaiting_order_item_selection'] = false;
            $state['position'] = null;
            $state['logo_base64'] = null;
            $state['logo_mime'] = null;
            $state['logo_received'] = false;
            $state['additional_prints'] = [];
            $state['pending_position'] = null;
            $state['awaiting_additional_artwork_choice'] = false;
            $state['awaiting_additional_image_position'] = null;
            $state['pending_uploaded_artwork'] = null;
            $state['awaiting_uploaded_artwork_position'] = false;
            $state['mockup_sent'] = false;
            $state['approved'] = false;
            if (! $hasExplicitQuantity) {
                $state['quantity'] = null;
            }
            if ($detectedColor === null) {
                $state['color'] = null;
                $state['color_label'] = null;
            }
        }

        if ($hasExplicitQuantity) {
            $quantity = min(50000, (int) $quantityMatch[1]);
            if (($state['quantity'] ?? null) !== $quantity) {
                $state['mockup_sent'] = false;
                $state['approved'] = false;
            }
            $state['quantity'] = $quantity;
        }

        if ($detectedColor !== null) {
            if (($state['color'] ?? null) !== $detectedColor[0]) {
                $state['mockup_sent'] = false;
                $state['approved'] = false;
            }
            $state['color'] = $detectedColor[0];
            $state['color_label'] = $detectedColor[1];
        }

        $positions = [
            'ön sol göğse' => 'left_chest', 'on sol goguse' => 'left_chest',
            'ön sol göğüs' => 'left_chest', 'on sol gogus' => 'left_chest',
            'ön sola' => 'left_chest', 'on sola' => 'left_chest',
            'ön sol' => 'left_chest', 'on sol' => 'left_chest',
            'sol göğse' => 'left_chest', 'sol goguse' => 'left_chest',
            'sol göğüs' => 'left_chest', 'sol gogus' => 'left_chest',
            'ön sağ göğse' => 'right_chest', 'on sag goguse' => 'right_chest',
            'ön sağ göğüs' => 'right_chest', 'on sag gogus' => 'right_chest',
            'ön sağa' => 'right_chest', 'on saga' => 'right_chest',
            'ön sağ' => 'right_chest', 'on sag' => 'right_chest',
            'sağ göğse' => 'right_chest', 'sag goguse' => 'right_chest',
            'sağ göğüs' => 'right_chest', 'sag gogus' => 'right_chest',
            'sol kola' => 'left_sleeve', 'sağ kola' => 'right_sleeve', 'sag kola' => 'right_sleeve',
            'sol kol' => 'left_sleeve', 'sağ kol' => 'right_sleeve', 'sag kol' => 'right_sleeve',
            'ön büyük' => 'front_large', 'on buyuk' => 'front_large',
            'arka büyük' => 'back_large', 'arka buyuk' => 'back_large',
            'sırta' => 'back_large', 'sirta' => 'back_large',
            'arkaya' => 'back_large', 'sırt' => 'back_large', 'sirt' => 'back_large', 'arka' => 'back_large',
            'ön orta' => 'front_center', 'on orta' => 'front_center',
            'göğse' => 'front_center', 'goguse' => 'front_center',
            'göğüs' => 'front_center', 'gogus' => 'front_center',
            'öne' => 'front_center', 'one' => 'front_center',
            'ön' => 'front_center', 'on' => 'front_center',
        ];
        $positionHits = [];
        foreach ($positions as $needle => $value) {
            if (preg_match(
                '/(?<![\\pL\\pN])'.preg_quote($needle, '/').'(?![\\pL\\pN])/u',
                $lower,
                $match,
                PREG_OFFSET_CAPTURE,
            )) {
                $positionHits[] = [
                    'offset' => $match[0][1],
                    'length' => strlen($match[0][0]),
                    'position' => $value,
                ];
            }
        }
        // Keep the most specific phrase: “sağ göğüs” must not also match “göğüs”.
        $positionHits = array_values(array_filter($positionHits, static function (array $hit) use ($positionHits): bool {
            foreach ($positionHits as $other) {
                if ($other['length'] > $hit['length']
                    && $other['offset'] <= $hit['offset']
                    && $other['offset'] + $other['length'] >= $hit['offset'] + $hit['length']) {
                    return false;
                }
            }
            return true;
        }));
        usort($positionHits, fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);
        $detectedPositions = array_values(array_unique(array_column($positionHits, 'position')));
        $detectedPosition = $detectedPositions[0] ?? null;

        if (
            count($detectedPositions) > 1
            && ($state['logo_received'] ?? false)
            && ! ($state['awaiting_uploaded_artwork_position'] ?? false)
            && (! ($state['position'] ?? null) || $this->sameArtworkMessage($lower))
        ) {
            $state['additional_prints'] = is_array($state['additional_prints'] ?? null)
                ? $state['additional_prints']
                : [];

            if (! ($state['position'] ?? null)) {
                $state['position'] = array_shift($detectedPositions);
            }

            foreach ($detectedPositions as $sameArtworkPosition) {
                if ($sameArtworkPosition === ($state['position'] ?? null)) {
                    continue;
                }
                $alreadyAdded = collect($state['additional_prints'])
                    ->contains(fn (mixed $print): bool => is_array($print)
                        && ($print['position'] ?? null) === $sameArtworkPosition);
                if (! $alreadyAdded) {
                    $state['additional_prints'][] = [
                        'position' => $sameArtworkPosition,
                        'logo_base64' => (string) ($state['logo_base64'] ?? ''),
                        'logo_mime' => (string) ($state['logo_mime'] ?? 'image/png'),
                    ];
                }
            }

            $state['additional_prints'] = array_slice($state['additional_prints'], -8);
            $state['mockup_sent'] = false;
            $state['approved'] = false;
            $detectedPosition = null;
        }

        if (count($detectedPositions) > 1 && ! ($state['logo_received'] ?? false)) {
            $state['position'] = $detectedPositions[0];
            $state['pending_same_artwork_positions'] = array_slice($detectedPositions, 1);
            $state['mockup_sent'] = false;
            $state['approved'] = false;
            $detectedPosition = null;
        }

        if ($detectedPosition !== null && ($state['awaiting_uploaded_artwork_position'] ?? false)) {
            $uploaded = is_array($state['pending_uploaded_artwork'] ?? null)
                ? $state['pending_uploaded_artwork']
                : [];
            if (($uploaded['logo_base64'] ?? null)) {
                $state['additional_prints'] = is_array($state['additional_prints'] ?? null)
                    ? $state['additional_prints']
                    : [];
                foreach ($detectedPositions as $uploadedPosition) {
                    $state['additional_prints'][] = [
                        'position' => $uploadedPosition,
                        'logo_base64' => (string) $uploaded['logo_base64'],
                        'logo_mime' => (string) ($uploaded['logo_mime'] ?? 'image/png'),
                    ];
                }
                $state['additional_prints'] = array_slice($state['additional_prints'], -8);
                $state['pending_uploaded_artwork'] = null;
                $state['awaiting_uploaded_artwork_position'] = false;
                $state['mockup_sent'] = false;
                $state['approved'] = false;
                $detectedPosition = null;
            }
        }

        if ($detectedPosition !== null) {
            $isAdditional = $this->additionalPrintMessage($lower)
                && ($state['logo_received'] ?? false)
                && ($state['position'] ?? null)
                && $detectedPosition !== ($state['position'] ?? null);

            if ($isAdditional) {
                $state['pending_position'] = $detectedPosition;
                $state['awaiting_additional_artwork_choice'] = true;
                $state['approved'] = false;
            } else {
                if (($state['position'] ?? null) !== $detectedPosition) {
                    $state['mockup_sent'] = false;
                    $state['approved'] = false;
                }
                $state['position'] = $detectedPosition;
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
        } elseif (str_contains($lower, 'dtg') || str_contains($lower, 'dijital')) {
            $state['print_type'] = 'DTG Baskı';
        } elseif (str_contains($lower, 'dtf') || str_contains($lower, 'transfer')) {
            $state['print_type'] = 'DTF Baskı';
        }

        if (preg_match('/(?:indirim|iskonto|son\\s*fiyat)/u', $lower)) {
            $state['discount_requested'] = true;
        }

        if (preg_match('/\b(s|m|l|xl|xxl|3xl|4xl)(?:\s*[-–\/]\s*(s|m|l|xl|xxl|3xl|4xl))?\b/iu', $message, $sizeMatch)) {
            $state['sizes'] = strtoupper($sizeMatch[0]);
        }

        if (
            str_contains($lower, 'önizlemeyi tekrar')
            || str_contains($lower, 'onizlemeyi tekrar')
            || (trim($lower) === 'hazırla')
            || (trim($lower) === 'hazirla')
        ) {
            $state['mockup_sent'] = false;
            $state['approved'] = false;
        }

        return $state;
    }

    private function sameArtworkMessage(string $message): bool
    {
        return str_contains($message, 'aynı görsel')
            || str_contains($message, 'ayni gorsel')
            || str_contains($message, 'aynı logo')
            || str_contains($message, 'ayni logo')
            || trim($message) === 'aynı'
            || trim($message) === 'ayni';
    }

    private function differentArtworkMessage(string $message): bool
    {
        return str_contains($message, 'farklı görsel')
            || str_contains($message, 'farkli gorsel')
            || str_contains($message, 'başka görsel')
            || str_contains($message, 'baska gorsel')
            || str_contains($message, 'farklı logo')
            || str_contains($message, 'farkli logo');
    }

    private function additionalPrintMessage(string $message): bool
    {
        foreach (['bir de', 'birde', 'ayrıca', 'ayrica', 'ek olarak', 'hem ', 'da basalım', 'de basalım', 'da basalim', 'de basalim'] as $phrase) {
            if (str_contains($message, $phrase)) {
                return true;
            }
        }
        return false;
    }

    private function currentOrderItem(array $state): array
    {
        return [
            'product' => $state['product'] ?? null,
            'quantity' => $state['quantity'] ?? null,
            'color' => $state['color_label'] ?? null,
            'sizes' => $state['sizes'] ?? null,
            'print_positions' => array_values(array_filter(array_merge(
                [($state['position'] ?? null) ? $this->positionLabel((string) $state['position']) : null],
                collect($state['additional_prints'] ?? [])
                    ->map(fn (mixed $print): ?string => is_array($print) && ($print['position'] ?? null)
                        ? $this->positionLabel((string) $print['position'])
                        : null)
                    ->all(),
            ))),
        ];
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
            || str_contains($normalized, 'şapka')
            || str_contains($normalized, 'sapka')
            || str_contains($normalized, 'sweat');

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

    private function productRecommendationRequest(string $message): bool
    {
        $message = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], $message));

        return preg_match(
            '/(?:hangi|nasıl|nasil).{0,35}(?:tişört|tisort|model).{0,25}(?:seç|sec|uygun|öner)|(?:kararsızım|kararsizim).{0,30}(?:tişört|tisort|model)/u',
            $message,
        ) === 1;
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
            'quantity',
            'color',
            'position',
            'print_type',
            'sizes',
            'logo_received',
            'mockup_sent',
            'approved',
            'customer_supplied',
            'discount_requested',
            'additional_prints',
            'pending_position',
            'pending_same_artwork_positions',
            'awaiting_additional_artwork_choice',
            'awaiting_additional_image_position',
            'pending_uploaded_artwork',
            'awaiting_uploaded_artwork_position',
            'order_items',
            'awaiting_order_item_selection',
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
        $liveContext = $this->liveConversationInstructions($state, $bot);

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

    private function liveConversationInstructions(array $state, AiBot $bot): string
    {
        $status = [
            'Ürün' => $state['product'] ?? 'henüz belirtilmedi',
            'Ürün kategorisi' => $state['product_category'] ?? 'henüz belirtilmedi',
            'Renk' => $state['color_label'] ?? 'henüz belirtilmedi',
            'Adet' => $state['quantity'] ?? 'henüz belirtilmedi',
            'Beden' => $state['sizes'] ?? 'henüz belirtilmedi',
            'Baskı türü' => $state['print_type'] ?? 'ürün ve görsele göre belirlenecek',
            'Ana baskı konumu' => ($state['position'] ?? null)
                ? $this->positionLabel((string) $state['position'])
                : 'henüz belirtilmedi',
            'Ek baskı alanları' => implode(', ', array_map(
                fn (array $print): string => $this->positionLabel((string) ($print['position'] ?? '')),
                $state['additional_prints'] ?? [],
            )),
            'Ek baskı sayısı' => count(is_array($state['additional_prints'] ?? null) ? $state['additional_prints'] : []),
            'Aynı siparişte kayıtlı ürün kalemi' => count(is_array($state['order_items'] ?? null) ? $state['order_items'] : []),
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

        $instructions = <<<PROMPT
CANLI TEKSTİL SİPARİŞ BAĞLAMI
{$lines}

Müşterinin son mesajındaki asıl soruya önce doğrudan ve doğal biçimde cevap ver.
Fiyatı veya indirimi tahmin etme; adet aralıklarını, iç fiyat tablosunu ve indirim eşiklerini müşteriye hiçbir zaman açıklama. Sistem net fiyat verdiyse yalnızca o müşterinin birim fiyatını ve toplamını kullan.
Müşteriyi hemen siparişe, fiyat teklifine, ödeme veya onaya götürmeye çalışma. Önce ne istediğini anlamaya ve yardımcı olmaya odaklan.
“Bastırmak istiyorum”, “tişört düşünüyorum” gibi genel niyet cümlelerini kesin sipariş başlangıcı sayma. Böyle durumlarda sıcak bir karşılık verip model, kullanım amacı veya nasıl yardımcı olabileceğin hakkında yalnızca bir kolay soru sor.
Renk, adet, baskı konumu ve görsel gibi bütün eksikleri aynı anda isteme. Müşteri konuşmayı ilerlettikçe gerektiğinde tek tek ve doğal biçimde öğren.
“Siparişinizi netleştirelim”, “lütfen renk ve adet paylaşın” gibi form veya robot hissi veren kalıp cümleleri kullanma.
Müşteri yalnızca bilgi almak, seçenekleri görmek, fikir danışmak veya sohbet etmek isteyebilir; bunu sipariş vermeye zorlamadan karşıla.
Şirket profilindeki bütün bilgileri hiçbir zaman tek mesajda sıralama veya özetleme.
Müşteri genel bilgi isterse önce hangi konuyu merak ettiğini sor ve yalnızca şu kısa seçenekleri sun: ürün çeşitleri, kumaş ve kaliteler, baskı yöntemleri, fiyatlandırma, minimum adet, teslimat.
Müşteri belirli bir konu sorarsa yalnızca o konuya cevap ver; ilgisiz fiyat, ürün, kargo, ödeme veya iade bilgilerini ekleme.
Güncel ürün kataloğu: kapüşonlu sweatshirt, regular/bisiklet yaka tişört, siyah ve beyaz oversize tişört, polo yaka tişört, pamuklu şapka ve polyester şapka.
Şirket profilinde açıkça doğrulanmayan kumaş, kalıp, iç yüzey, gramaj, stok, renk, teknik özellik veya ürün niteliği uydurma. Özellikle kapüşonlunun şardonlu olduğunu, regular ürünün ince kesim olduğunu veya katalogda olmayan bir özelliği söyleme.
Baskı örnekleri arasında beyaz ve siyah tişört üzerine DTG baskı bulunur. Müşteri ürün çeşidi sorarsa yalnızca bu katalogdaki ürünleri kısa ve düzenli biçimde söyle.
Müşterinin seçtiği ürün modelini değiştirme; baskı önizlemesi seçilen gerçek ürünün stüdyo/manken şablonunda hazırlanır.
Bu bot yalnızca tekstil ürünleri içindir. Bardak veya kupa baskısı sunma; müşteri sorarsa bu hattın şu anda yalnız tekstil siparişleri için hizmet verdiğini kısa ve nazik biçimde söyle.
Aynı karşılama veya sipariş metnini tekrar etme. Önceki konuşmadaki bilgileri yeniden isteme.
Yanıt WhatsApp'a uygun, sıcak ama profesyonel ve çoğunlukla 1-3 kısa cümle olsun.
Her mesajı deneyimli bir satış danışmanı gibi bağlama özel yaz: müşterinin kullandığı kelimeleri, asıl sorusunu ve konuşmanın tonunu dikkate al; ezberlenmiş şablon hissi verme.
Önce müşterinin son sorusuna net cevap ver, ardından gerçekten gerekiyorsa konuşmayı ilerleten tek ve kolay bir soru sor.
Müşterinin niyetini tahmin ederken acele etme; bilgi isteyen kişiye bilgi ver, kararsız kişiye uzun seçenek listesi sunmak yerine ihtiyacına göre kısa bir öneri yap ve en fazla bir kolay soru sor, satın almaya hazır kişiye ise güven veren net bir sonraki adım sun.
Gereksiz teknik terim, uzun liste, aynı bilgiyi tekrar etme ve peş peşe soru sorma. “Harika”, “siparişinizi netleştirelim” gibi otomatik kalıpları her cevapta kullanma.
Müşteri itiraz ederse savunmaya geçme; önce itirazı anladığını göster, sonra kısa ve somut çözüm sun.
Yanıtı anlamlı kısa paragraflara ayır ve paragraflar arasında bir boş satır bırak.
Önemli ifadeleri gerektiğinde WhatsApp kalın biçimi olan *metin* ile vurgula; aşırı kullanma.
Gerçek satır sonu kullan; müşteriye \\n, \\r veya benzeri teknik kaçış ifadeleri gösterme.
Bilgi kesin değilse uydurma; neyin ürün veya sipariş detayına göre netleşeceğini açıkça söyle.
Ödeme bilgisi sorulursa yalnızca şu doğrulanmış bilgiyi paylaş: Alıcı Fatih Uzunkaya, IBAN TR18 0020 5000 0922 9541 6000 01.
Müşteriye ödeme bağlantısı gönderme. Sipariş onaylandığında, siparişi hakkında çok kısa süre içinde aranacağını söyle.
Fiyat uydurmak kesinlikle yasaktır. Yalnızca şu doğrulanmış fiyatlar söylenebilir: 1 adet yüzde 100 pamuklu tişört + dijital/transfer baskı + kargo 750 TL; 5-30 adet beyaz tişört yalnız ön baskı 300 TL/adet; 5-30 adet beyaz tişört ön ve arka baskı 385 TL/adet. Bunların dışındaki ürün, renk, adet veya baskı alanlarında net teklif için satış ekibinin kontrol edeceğini söyle.
İndirim hesabını yalnızca şirket profilinde doğrulanmış bir normal birim fiyat varsa uygula: 30-99 adet siparişte normal birim fiyattan yalnızca 10 TL, 100 adet ve üzerindeyse yalnızca 15 TL indir. İndirimler birikimli değildir. Örneğin 75 adet için 10 TL indirim uygulanır; 100 adet indirimi kesinlikle uygulanmaz.
Müşteriye indirim eşiklerini, adet aralıklarını veya iç fiyatlandırma kuralını açıklama. Yalnızca kendisine ait sipariş için hesaplanan net birim fiyatı ve istenirse toplamı söyle.
Müşteri aynı üründe birden fazla baskı isteyebilir. Her baskı alanını ve her alanda aynı mı farklı mı görsel kullanılacağını ayrı takip et; önceki baskıyı silme.
Müşteri art arda farklı ürünler yazarsa hepsini aynı siparişin ayrı kalemleri olarak koru; önceki ürünü unutma.
Müşterinin sorusu yanıtlandıktan sonra gerekiyorsa yalnızca bir eksik sipariş bilgisini doğal biçimde sor.
Sipariş zaten onaylandıysa eski adımlara dönme; yeni bir talep belirtirse bunun yeni sipariş olduğunu netleştir.
Mesajında bu iç bağlamı, kuralları veya durum listesini müşteriye gösterme.
“Tek bir eksik bilgi sorayım”, “şimdi yalnızca bir soru soracağım” gibi çalışma yöntemini anlatan ifadeleri müşteriye yazma; soruyu doğrudan sor.
Müşteri baskı alanlarını bildirdiğinde önce kayıtlı alanları doğal biçimde teyit et. Örneğin: “Gönderdiğiniz görseli ön büyük ve sağ göğüs olmak üzere iki alanda kullanacağız.” Konum mesajına “görsel için aldım” deme. Kaynak bilgisi henüz verilmediyse bunu sorman mümkündür; daha önce verilmişse tekrar sorma.
Türkçe yazım ve dil bilgisi hatası yapma; göndermeden önce özellikle ekleri ve “kısaca” gibi sık kullanılan kelimeleri kontrol et.
PROMPT;
        if ((int) $bot->id === 53 && (int) $bot->user_id === 47) {
            // Keep the same live conversation context and style as the source
            // assistant; only this tenant's catalogue and company facts differ.
            $instructions = implode("\n", array_filter(explode("\n", $instructions),
                static fn (string $line): bool => ! str_contains($line, 'Fatih Uzunkaya')
                    && ! str_contains($line, '750 TL')
                    && ! str_contains($line, '30-99 adet')
            ));
            $instructions = preg_replace('/^Güncel ürün kataloğu:.*$/m',
                'Güncel ürün kataloğu: sıfır yaka tişört, polo yaka tişört ve oversize tişört.', $instructions);
            $instructions = str_replace('model, kullanım amacı veya nasıl yardımcı olabileceğin hakkında',
                'sıfır yaka, polo yaka veya oversize istediği hakkında', $instructions);
            $instructions .= "\nBEKİR TEKSTİL\n"
                ."Kullanım amacı ASLA sorulmaz. Sweatshirt, şapka veya başka ürün sunulmaz.\n"
                ."Sıfır yaka, polo yaka ve oversize dışında ürün/model/kalite seçeneği uydurma; yedi çeşit ürün akışı yoktur.\n"
                ."Model, renk, logo ve baskı konumu hazırsa önizleme otomatik oluşturulur ve gönderilir; ek onay, ölçü, kullanım amacı veya adet bekleyerek geciktirme.\n"
                ."Görsel henüz gönderilmedi olarak kayıtlıysa hazır/gönderdim deme. Görsel oluşturamıyorum deme.\n"
                ."Yalnız Bekir Tekstil'in doğrulanmış firma ve ödeme bilgileri geçerlidir; başka firmanın fiyatını, IBAN'ını, adresini, stok veya minimum adetini kullanma.\n";
        }
        return $instructions;
    }

    private function protectInternalPricing(string $answer, string $message, array $state): string
    {
        $answer = preg_replace(
            '/\\b\\d+\\s*[–—-]\\s*\\d+\\s*adet(?:\\s*aralığı)?\\s*için\\s*/ui',
            '',
            $answer,
        ) ?? $answer;

        $lines = preg_split('/\\R/u', $answer) ?: [$answer];
        $lines = array_values(array_filter($lines, static function (string $line): bool {
            $normalized = Str::lower(trim($line));
            if ($normalized === '') {
                return true;
            }

            return ! preg_match(
                '/(?:\\d+\\s*adet\\s*(?:ve\\s*üzeri|üzeri|e\\s*kadar|a\\s*kadar)|adet\\s*aralığı|indirim\\s*eşiği|birim\\s*fiyat(?:ı|i).*üzerinden)/u',
                $normalized,
            );
        }));
        $answer = trim(preg_replace("/\\n{3,}/", "\\n\\n", implode("\\n", $lines)) ?? implode("\\n", $lines));

        $normalizedMessage = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], $message));
        $isDiscountRequest = preg_match('/(?:indirim|iskonto|son\\s*fiyat)/u', $normalizedMessage) === 1;
        if (! $isDiscountRequest) {
            return $answer;
        }

        $unit = null;
        $total = null;
        if (preg_match('/birim\\s*fiyat(?:ınız|ı|i)?[^\\d]{0,30}\\*?([\\d.]+(?:,\\d+)?)\\s*TL/ui', $answer, $match)) {
            $unit = $match[1];
        }
        if (preg_match('/toplam(?:\\s*\\(\\d+\\s*adet\\))?[^\\d]{0,30}\\*?([\\d.]+(?:,\\d+)?)\\s*TL/ui', $answer, $match)) {
            $total = $match[1];
        }

        if ($unit !== null && $total !== null) {
            $quantity = max(1, (int) ($state['quantity'] ?? 1));

            return "Elbette, siparişinize uygulanabilecek indirimle birim fiyat *{$unit} TL/adet* olur.\\n\\n"
                ."{$quantity} adet toplam: *{$total} TL*.\\n\\n"
                .'Uygunsa bir sonraki detaya geçebiliriz.';
        }

        return $answer !== ''
            ? $answer
            : 'İndirim talebinizi aldım. Siparişinize özel net birim fiyatı paylaşabilmem için ürün ve baskı detaylarını kontrol edelim.';
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

        if (preg_match('/(tişört|tisort).*(bastır|bastir|baskı|baski)|(bastır|bastir|baskı|baski).*(tişört|tisort)/u', $normalized)) {
            return ($state['bekir_catalogue'] ?? false) ? $this->nextQuestion($state) : 'Memnuniyetle yardımcı oluruz. Nasıl bir tişört düşünüyorsunuz; *regular, oversize veya polo yaka* mı?';
        }

        return 'Tabii, sizi dinliyorum. Nasıl yardımcı olabilirim?';
    }

    private function nextQuestion(array $state): string
    {
        if (($state['bekir_catalogue'] ?? false)
            && ! ($state['awaiting_order_item_selection'] ?? false)
            && ! ($state['awaiting_additional_artwork_choice'] ?? false)
            && ! ($state['awaiting_additional_image_position'] ?? false)
            && ! ($state['awaiting_uploaded_artwork_position'] ?? false)) {
            if (! ($state['product'] ?? null)) return 'Sıfır yaka, polo yaka veya oversize mı düşünüyorsunuz?';
            if (! ($state['quantity'] ?? null) && ! ($state['logo_received'] ?? false)) return 'Kaç adet düşünüyorsunuz?';
            if (! ($state['color'] ?? null)) return 'Tişört hangi renk olsun?';
            if (! ($state['logo_received'] ?? false)) return 'Baskıda kullanacağınız logo veya görseli gönderebilir misiniz?';
            if (! ($state['position'] ?? null)) return 'Logoyu nereye basalım? Örneğin sol göğüs, ön orta veya sırt.';
        }

        if ($state['awaiting_order_item_selection'] ?? false) {
            return 'Belgedeki ürünleri aynı sipariş altında kaydettim. Önce hangi ürünün baskı önizlemesini hazırlayalım?';
        }

        if ($state['awaiting_additional_artwork_choice'] ?? false) {
            return 'Ek baskıda mevcut görseli mi kullanalım, yoksa farklı bir görsel mi göndereceksiniz? *Aynı görsel* veya *farklı görsel* yazabilirsiniz.';
        }

        if ($state['awaiting_additional_image_position'] ?? null) {
            return 'Ek baskıda kullanılacak farklı görseli JPG, PNG veya WEBP olarak gönderin.';
        }

        if ($state['awaiting_uploaded_artwork_position'] ?? false) {
            return 'Yeni gönderdiğiniz görsel hangi baskı alanında kullanılacak? Ön orta, ön sol göğüs, arka büyük, sağ kol veya sol kol yazabilirsiniz.';
        }

        if (! ($state['product_category'] ?? null)) {
            return $this->welcomeMessage();
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
        $positions = array_merge(
            [$this->positionLabel((string) $state['position'])],
            collect($state['additional_prints'] ?? [])
                ->map(fn (mixed $print): ?string => is_array($print) && ($print['position'] ?? null)
                    ? $this->positionLabel((string) $print['position'])
                    : null)
                ->filter()
                ->all(),
        );
        $quote = $this->quote($state);
        $pricing = $quote === null
            ? "Fiyat: *Sipariş detaylarına göre satış ekibi tarafından netleştirilecek*\n\n"
            : "Birim fiyat: *{$quote[0]} TL*\nToplam: *".number_format($quote[1], 0, ',', '.')." TL*\nTermin: *{$quote[2]}*\n\n";

        return "Baskı önizlemeniz hazırlandı ✓\n\n"
            .'Ürün: '.($state['product'] ?? 'Premium Oversize Tişört')."\n"
            .'Renk: '.($state['color_label'] ?? 'Siyah')."\n"
            .'Baskı alanları: '.implode(', ', $positions)."\n"
            .'Adet: '.($state['quantity'] ?? (($state['bekir_catalogue'] ?? false) ? 'Henüz belirtilmedi' : 1))."\n\n"
            .$pricing
            .'Görsel ve bilgiler uygunsa *Onaylıyorum* yazabilirsiniz.';
    }

    private function paymentMessage(array $state): string
    {
        $quote = $this->quote($state);
        if ($quote === null) {
            throw new \LogicException('Tanımsız fiyat için sipariş onayı üretilemez.');
        }

        return "✅ Tasarım ve siparişiniz onaylandı.\n\n"
            .'Sipariş No: *#'.$state['order_id']."*\n"
            .'Toplam: *'.number_format($quote[1], 0, ',', '.')." TL*\n\n"
            ."Ödeme bilgileri:\n"
            ."Alıcı: *Fatih Uzunkaya*\n"
            ."IBAN: *TR18 0020 5000 0922 9541 6000 01*\n\n"
            .'Siparişiniz hakkında sizi çok kısa süre içinde arayacağız.';
    }

    private function pricingReply(string $message, array $state): ?string
    {
        $normalizedMessage = Str::lower(str_replace(['İ', 'I'], ['i', 'ı'], $message));
        if (! preg_match('/(?:fiyat|kaç\\s*para|ne\\s*kadar|tutar|indirim|iskonto|son\\s*fiyat)/u', $normalizedMessage)) {
            return null;
        }

        $quote = $this->quote($state);
        if ($quote === null) {
            return null;
        }

        $quantity = max(1, (int) ($state['quantity'] ?? 1));
        $prefix = ($state['discount_requested'] ?? false)
            ? 'Siparişinize uygulanabilecek indirimle'
            : 'Bu sipariş için';

        return "{$prefix} birim fiyat *{$quote[0]} TL/adet* olur.\n\n"
            .$quantity.' adet toplam: *'.number_format($quote[1], 0, ',', '.')." TL*.\n\n"
            .'Baskı ölçüsü ve beden dağılımı netleştiğinde üretim planını da kesinleştirebiliriz.';
    }

    private function quote(array $state): ?array
    {
        if ($state['bekir_catalogue'] ?? false) return null;

        $quantity = max(1, (int) ($state['quantity'] ?? 1));
        $color = (string) ($state['color'] ?? '');
        $product = (string) ($state['product'] ?? '');

        if ($quantity === 1 && str_contains($product, 'Tişört')) {
            return [750, 750, 'Numune — kargo dahil'];
        }

        $method = str_contains((string) ($state['print_type'] ?? ''), 'DTF')
            ? 'transfer'
            : 'digital';

        if (
            $quantity < 5
            || ! str_contains($product, 'Tişört')
            || ($method === 'digital' && $color !== 'white')
        ) {
            return null;
        }

        $positions = array_merge(
            [(string) ($state['position'] ?? '')],
            array_map(
                fn (array $print): string => (string) ($print['position'] ?? ''),
                is_array($state['additional_prints'] ?? null) ? $state['additional_prints'] : [],
            ),
        );
        $hasFront = count(array_intersect($positions, [
            'left_chest', 'right_chest', 'front_center', 'front_large',
        ])) > 0;
        $hasBack = in_array('back_large', $positions, true);

        if (! $hasFront || count(array_filter($positions)) > ($hasBack ? 2 : 1)) {
            return null;
        }

        $layout = $hasBack ? 'front_back' : 'front';

        if ($quantity <= 30) {
            $band = 0;
        } elseif ($quantity < 100) {
            $band = 1;
        } else {
            $band = 2;
        }

        $prices = [
            'digital' => [
                'front' => [300, 285, 275],
                'front_back' => [395, 375, 355],
            ],
            'transfer' => [
                'front' => [250, 255, 230],
                'front_back' => [375, 345, 310],
            ],
        ];

        $unit = $prices[$method][$layout][$band];
        if ($state['discount_requested'] ?? false) {
            if ($quantity >= 100) {
                $unit -= 15;
            } elseif ($quantity >= 30) {
                $unit -= 10;
            }
        }

        return [$unit, $unit * $quantity, 'Sipariş detaylarına göre netleşir'];
    }

    private function sendText(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $answer): void
    {
        $answer = trim(str_replace(
            ['\\r\\n', '\\n', '\\r'],
            ["\n", "\n", "\n"],
            $answer,
        ));
        $answer = preg_replace("/\\n{3,}/", "\n\n", $answer) ?? $answer;
        $answer = str_ireplace('kısaçe', 'kısaca', $answer);
        $answer = preg_replace(
            '/Hangi ürünü ve renk\\/adet bilgisini paylaş(?:ır|ir) mısınız\\?\\s*Tek bir eksik bilgi sorayım:\\s*hangi ürünü görmek istersiniz\\?/ui',
            'Hangi ürünü görmek istersiniz?',
            $answer,
        ) ?? $answer;
        $answer = preg_replace(
            '/(?:Tek bir eksik bilgi sorayım|Şimdi yalnızca bir eksik bilgi soracağım):\\s*/ui',
            '',
            $answer,
        ) ?? $answer;

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

    private function sendImage(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $mockupBase64, array $state, ?string $view = null): void
    {
        $caption = 'Baskı önizlemeniz hazır ✓ Logo orijinal dosyanızdan otomatik yerleştirildi.';
        $filename = 'baski-onizleme.jpg';
        if ($view !== null) {
            $viewLabel = $view === 'back' ? 'Arka görünüm' : 'Ön görünüm';
            $caption = $viewLabel.' — '.$this->positionLabel((string) $state['position']).' baskı önizlemesi ✓';
            $filename = $view === 'back' ? 'baski-arka-gorunum.jpg' : 'baski-on-gorunum.jpg';
        }
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
            'message' => '[Baskı önizlemesi] '.$this->positionLabel((string) $state['position']),
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
        $defaults = [
            'product' => null,
            'product_category' => null,
            'quantity' => null,
            'color' => null,
            'color_label' => null,
            'position' => null,
            'print_type' => null,
            'sizes' => null,
            'logo_received' => false,
            'mockup_sent' => false,
            'approved' => false,
            'customer_supplied' => null,
            'discount_requested' => false,
            'additional_prints' => [],
            'pending_position' => null,
            'pending_same_artwork_positions' => [],
            'awaiting_additional_artwork_choice' => false,
            'awaiting_additional_image_position' => null,
            'pending_uploaded_artwork' => null,
            'awaiting_uploaded_artwork_position' => false,
            'order_items' => [],
            'awaiting_order_item_selection' => false,
        ];

        return array_replace($defaults, is_array($value) ? $value : []);
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

        // Bekir: username-only WhatsApp contacts may have no phone-number JID.
        $jid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if (($payload['instance'] ?? '') === 'bekir-tekstil-53' && preg_match('/^[0-9]+@lid$/', $jid)) {
            return $jid;
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
            'left_chest' => 'Ön sol göğüs',
            'right_chest' => 'Ön sağ göğüs',
            'left_sleeve' => 'Sol kol',
            'right_sleeve' => 'Sağ kol',
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