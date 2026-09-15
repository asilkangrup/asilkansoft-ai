<?php

namespace App\Services\Printing;

final class PrintingConversationService
{
    private const QUESTIONS = [
        'product' => 'Ne bastırmak istiyorsunuz? Örneğin kartvizit, broşür, katalog veya etiket olabilir.',
        'quantity' => 'Kaç adet düşünüyorsunuz?',
        'size' => 'Ölçü nasıl olsun?',
        'sides' => 'Tek yön mü, çift yön mü olacak?',
        'paper' => 'Kağıt türü veya gramaj tercihiniz var mı? Tercihiniz yoksa uygun standart seçeneği önerebiliriz.',
        'page_count' => 'Kaç sayfa olacak?',
        'inner_paper' => 'İç sayfalarda hangi kağıt ve gramajı düşünüyorsunuz?',
        'cover_paper' => 'Kapakta hangi kağıt ve gramajı düşünüyorsunuz?',
        'binding' => 'Ciltleme nasıl olsun? Tel dikiş, Amerikan cilt veya farklı bir tercih olabilir.',
        'material' => 'Malzeme tercihiniz var mı? Tercihiniz yoksa kullanım yerine göre uygun seçeneği önerebiliriz.',
        'cut_shape' => 'Kesim nasıl olsun? Kare/dikdörtgen, yuvarlak, oval veya özel kesim olabilir.',
        'sheet_count' => 'Bir blokta kaç yaprak olsun?',
    ];

    public function __construct(
        private readonly PrintingIntentExtractor $extractor,
        private readonly PrintingProductCatalog $catalog,
        private readonly PrintingDesignBriefService $designBrief,
    ) {
    }

    public function process(string $message, array $state = [], array $attachments = []): array
    {
        $previousPending = is_array($state['pending_fields'] ?? null) ? $state['pending_fields'] : [];
        $state = $this->extractor->extract($message, $state);

        if ($attachments !== []) {
            $state = $this->applyAttachments($state, $attachments, $message);
        }

        $missing = $this->extractor->missing($state);
        $max = max(1, (int) config('matbaa.max_questions_per_turn', 2));
        $questions = array_slice($missing, 0, $max);

        if (($state['product'] ?? null) === null) {
            $prefix = $this->isGreeting($message) ? 'Merhaba, tabii. ' : '';
            return $this->result($state, $missing, $prefix.self::QUESTIONS['product'], 'collecting', ['product']);
        }

        $product = $this->catalog->get($state['product']);
        $label = $product['label'] ?? 'baskı işi';

        if ($missing !== []) {
            $questionText = implode(' ', array_map(
                static fn (string $field) => self::QUESTIONS[$field] ?? ucfirst(str_replace('_', ' ', $field)).' bilgisini de alabilir miyim?',
                $questions
            ));

            $isFirstProductTurn = in_array('product', $previousPending, true);
            $prefix = $isFirstProductTurn
                ? ucfirst($label)." için ilerleyelim. "
                : $this->preferenceAcknowledgement($state);

            return $this->result($state, $missing, trim($prefix.$questionText), 'collecting', $questions);
        }

        $design = $state['slots']['design_status'] ?? null;

        if ($design === 'needs_design') {
            $state['design_brief'] = $this->designBrief->collect(
                message: $message,
                brief: is_array($state['design_brief'] ?? null) ? $state['design_brief'] : [],
                pendingFields: $previousPending,
            );

            $briefMissing = $this->designBrief->missing($state['design_brief']);
            if ($briefMissing !== []) {
                $briefQuestions = $this->designBrief->questions($briefMissing, $max);
                $referenceAck = $this->hasReferenceAssets($state)
                    ? 'Gönderdiğiniz görseli tasarım referansı olarak kullanacağım. '
                    : '';
                $reply = $referenceAck.'Tasarımı biz hazırlayabiliriz. '.implode(' ', $briefQuestions);

                return $this->result(
                    $state,
                    [],
                    $reply,
                    'collecting_design_brief',
                    array_map(static fn (string $field) => 'brief:'.$field, array_slice($briefMissing, 0, $max))
                );
            }

            $state['design_generation_requested'] = ($state['last_intent'] ?? '') === 'generate_design'
                || ($state['design_generation_requested'] ?? false) === true;

            $reply = $this->hasReferenceAssets($state)
                ? 'Brief tamam. Gönderdiğiniz görseli referans alarak ilk taslağı hazırlıyorum.'
                : 'Brief tamam. İlk taslağı hazırlıyorum.';

            return $this->result($state, [], $reply, 'design_brief_ready', []);
        }

        if ($design === null) {
            $reply = $this->preferenceAcknowledgement($state)
                .'Tasarım dosyanız hazır mı? Hazırsa buradan gönderebilirsiniz; yoksa tasarımı sizin için hazırlayabiliriz.';
            return $this->result($state, [], trim($reply), 'awaiting_design_choice', ['design_status']);
        }

        if ($design === 'ready' && ! $this->hasReadyArtwork($state)) {
            $reply = 'Tasarım hazırsa dosyayı PDF, JPG veya PNG olarak buradan gönderebilirsiniz. Dosyayı kontrol edip uygun üründe baskı önizlemesi hazırlayacağım.';
            return $this->result($state, [], $reply, 'awaiting_file', ['design_file']);
        }

        $reply = "{$label} talebiniz teklif hazırlanabilecek seviyede. Bilgileri ekibe aktaracağım; net fiyat kontrol edilmeden kesin rakam paylaşmayacağım.";
        return $this->result($state, [], $reply, 'quote_ready', []);
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Sen Türkiye'deki profesyonel bir matbaa/ofset baskı işletmesinin WhatsApp satış ve tasarım danışmanısın.

Konuşma kuralları:
- Türkçe, doğal, kısa ve insan gibi konuş. Robot, form veya çağrı merkezi dili kullanma.
- Müşterinin yazdığı bilgileri ASLA tekrar sorma. Tek mesajda verdiği tüm ayrıntıları kullan.
- Her turda sipariş özetini baştan sayma; "not aldım", "tamamdır" gibi kalıpları peş peşe tekrarlama.
- Müşteri "yok", "tercihim yok", "fark etmez", "siz seçin", "siz belirleyin", "standart olsun" derse bunu geçerli cevap kabul et; aynı alanı tekrar sorma.
- Ürün belli olduktan sonra müşteri açıkça yeni sipariş başlatmadıkça tekrar ürün sorma.
- Tasarım yoksa bunu problem gibi sunma; kısa bir brief toplayıp tasarım hazırlama akışına geçir.
- Tasarım hazırlanırken gönderilen görsel, müşteri "hazır baskı tasarımı" demediyse referans/logo olabilir; körlemesine baskı dosyası kabul etme.
- Müşteri "örnek", "referans", "buna benzer", "bunun gibi" derse son gönderilen görseli referans olarak kabul et ve aynı soruyu tekrar sorma.
- Müşteri "hazırla", "devam", "tasarla" derse mevcut ürün ve brief bağlamını koruyarak tasarım üretimine devam et.
- Bir turda en fazla 1-2 mantıklı soru sor; soruları mümkünse aynı doğal cümlede grupla.
- Ürüne göre gerekli teknik bilgileri sor. İlgisiz teknik detaylarla müşteriyi yorma.
- Tasarım dosyası geldiyse tekrar 'tasarımınız hazır mı' diye sorma.
- Fiyat tablosu veya doğrulanmış fiyat sonucu yoksa ASLA fiyat uydurma, tahmin verme veya 'yaklaşık' rakam üretme.
- Teslim süresini sistemde doğrulanmış bilgi yoksa kesin vaat etme.
- İş baskıya/teklife hazır hale geldiğinde kısa bir özetle personele aktarılacağını söyle.
- Emoji kullanımı çok sınırlı olsun; profesyonel matbaa görüşmesinde gerekmedikçe emoji kullanma.
PROMPT;
    }

    private function applyAttachments(array $state, array $attachments, string $message): array
    {
        $intent = (string) ($state['last_intent'] ?? 'order');
        $designStatus = $state['slots']['design_status'] ?? null;
        $explicitReady = $this->explicitReadyArtwork($message);

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $role = 'unknown';
            if ($explicitReady || $designStatus === 'ready') {
                $role = 'ready_artwork';
            } elseif ($designStatus === 'needs_design' || $intent === 'reference') {
                $role = 'reference';
            }

            $attachment['role'] = $role;
            $state['attachments'][] = $attachment;
            $state['design_assets'][] = [
                'role' => $role,
                'name' => $attachment['name'] ?? 'tasarim',
                'mime' => $attachment['mime'] ?? null,
                'received_at' => now()->toIso8601String(),
            ];
        }

        if ($explicitReady) {
            $state['slots']['design_status'] = 'ready';
        } elseif ($designStatus === 'needs_design' || $intent === 'reference') {
            $state['slots']['design_status'] = 'needs_design';
        } elseif ($designStatus === null) {
            // A random image must not silently switch the order to ready-artwork
            // mode. Ask/route later instead of making a commercial assumption.
            $state['slots']['design_status'] = null;
        }

        return $state;
    }

    private function hasReferenceAssets(array $state): bool
    {
        foreach ($state['design_assets'] ?? [] as $asset) {
            if (is_array($asset) && ($asset['role'] ?? null) === 'reference') {
                return true;
            }
        }

        return false;
    }

    private function hasReadyArtwork(array $state): bool
    {
        foreach ($state['design_assets'] ?? [] as $asset) {
            if (is_array($asset) && ($asset['role'] ?? null) === 'ready_artwork') {
                return true;
            }
        }

        return false;
    }

    private function explicitReadyArtwork(string $message): bool
    {
        $text = mb_strtolower($message, 'UTF-8');
        return str_contains($text, 'hazır tasarım')
            || str_contains($text, 'hazir tasarim')
            || str_contains($text, 'baskıya hazır')
            || str_contains($text, 'baskiya hazir')
            || str_contains($text, 'mevcut tasarım')
            || str_contains($text, 'mevcut tasarim')
            || str_contains($text, 'bunu basın')
            || str_contains($text, 'bunu basin');
    }

    private function result(array $state, array $missing, string $reply, string $status, array $pendingFields): array
    {
        $state['status'] = $status;
        $state['pending_fields'] = array_values($pendingFields);
        $state['updated_at'] = now()->toIso8601String();

        return [
            'reply' => $reply,
            'state' => $state,
            'missing' => $missing,
            'status' => $status,
        ];
    }

    private function preferenceAcknowledgement(array $state): string
    {
        $parts = [];

        if (($state['slots']['paper'] ?? null) === 'no_preference') {
            $parts[] = 'Kağıt/gramaj için uygun standart seçeneği önerebiliriz.';
        }
        if (($state['slots']['material'] ?? null) === 'no_preference') {
            $parts[] = 'Malzeme için kullanımınıza uygun standart seçeneği önerebiliriz.';
        }

        return $parts === [] ? '' : implode(' ', $parts).' ';
    }

    private function isGreeting(string $message): bool
    {
        $text = mb_strtolower(trim($message), 'UTF-8');

        return preg_match('/^(?:merhaba|selam|selamlar|iyi günler|iyi gunler|günaydın|gunaydin)[!. ]*$/u', $text) === 1;
    }
}
