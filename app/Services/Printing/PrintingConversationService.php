<?php

namespace App\Services\Printing;

final class PrintingConversationService
{
    private const QUESTIONS = [
        'product' => 'Ne bastırmak istiyorsunuz? Örneğin kartvizit, broşür, katalog veya etiket olabilir.',
        'quantity' => 'Kaç adet düşünüyorsunuz?',
        'size' => 'Ölçü nasıl olsun?',
        'sides' => 'Tek yön mü, çift yön mü olacak?',
        'paper' => 'Kağıt türü veya gramaj tercihiniz var mı?',
        'page_count' => 'Kaç sayfa olacak?',
        'inner_paper' => 'İç sayfalarda hangi kağıt ve gramajı düşünüyorsunuz?',
        'cover_paper' => 'Kapakta hangi kağıt ve gramajı düşünüyorsunuz?',
        'binding' => 'Ciltleme nasıl olsun? Tel dikiş, Amerikan cilt veya farklı bir tercih olabilir.',
        'material' => 'Malzeme tercihiniz var mı? Kullanım yerine göre birlikte de netleştirebiliriz.',
        'cut_shape' => 'Kesim nasıl olsun? Kare/dikdörtgen, yuvarlak, oval veya özel kesim olabilir.',
        'sheet_count' => 'Bir blokta kaç yaprak olsun?',
    ];

    public function __construct(
        private readonly PrintingIntentExtractor $extractor,
        private readonly PrintingProductCatalog $catalog,
    ) {
    }

    public function process(string $message, array $state = [], array $attachments = []): array
    {
        $state = $this->extractor->extract($message, $state);

        if ($attachments !== []) {
            $state['attachments'] = array_values(array_merge($state['attachments'] ?? [], $attachments));
            $state['slots']['design_status'] ??= 'ready';
        }

        $missing = $this->extractor->missing($state);
        $max = max(1, (int) config('matbaa.max_questions_per_turn', 2));
        $questions = array_slice($missing, 0, $max);

        if (($state['product'] ?? null) === null) {
            return $this->result($state, $missing, self::QUESTIONS['product'], 'collecting');
        }

        $product = $this->catalog->get($state['product']);
        $label = $product['label'] ?? 'baskı işi';

        if ($missing === []) {
            $design = $state['slots']['design_status'] ?? null;
            if ($design === null) {
                $reply = "Tamamdır, {$label} için baskı bilgilerini aldım. Tasarım dosyanız hazırsa buradan gönderebilirsiniz; hazır değilse tasarım desteği gerektiğini belirtmeniz yeterli.";
                return $this->result($state, [], $reply, 'awaiting_design');
            }

            if ($design === 'ready' && empty($state['attachments'])) {
                $reply = "Harika, {$label} bilgileri tamam. Tasarım dosyanızı PDF, JPG veya PNG olarak buradan gönderebilirsiniz. Dosya geldikten sonra işi teklif için hazır hale getireceğim.";
                return $this->result($state, [], $reply, 'awaiting_file');
            }

            $reply = "Tamamdır, {$label} talebiniz teklif hazırlanabilecek seviyede. Bilgileri ekibe eksiksiz aktaracağım; net fiyatı kontrol edilmeden kesin rakam paylaşmayacağım.";
            return $this->result($state, [], $reply, 'quote_ready');
        }

        $known = $this->humanSummary($state);
        $questionText = implode(' ', array_map(
            static fn (string $field) => self::QUESTIONS[$field] ?? ucfirst(str_replace('_', ' ', $field)).' bilgisini de alabilir miyim?',
            $questions
        ));

        $prefix = $known !== ''
            ? "Tamam, {$known} olarak not aldım."
            : "Tabii, {$label} için yardımcı olayım.";

        return $this->result($state, $missing, trim($prefix.' '.$questionText), 'collecting');
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Sen Türkiye'deki profesyonel bir matbaa/ofset baskı işletmesinin WhatsApp satış danışmanısın.

Konuşma kuralları:
- Türkçe, doğal, kısa ve insan gibi konuş. Robot, form veya çağrı merkezi dili kullanma.
- Müşterinin yazdığı bilgileri ASLA tekrar sorma. Tek mesajda verdiği tüm ayrıntıları kullan.
- Bir turda en fazla 1-2 mantıklı soru sor; soruları mümkünse aynı doğal cümlede grupla.
- Ürüne göre gerekli teknik bilgileri sor. İlgisiz teknik detaylarla müşteriyi yorma.
- Müşteri terimi yanlış kullandıysa üstünlük taslamadan doğru seçeneğe yönlendir.
- Tasarım dosyası geldiyse tekrar 'tasarımınız hazır mı' diye sorma.
- PDF/JPG/PNG dosyalarını tasarım dosyası olarak kabul et.
- Fiyat tablosu veya doğrulanmış fiyat sonucu yoksa ASLA fiyat uydurma, tahmin verme veya 'yaklaşık' rakam üretme.
- Teslim süresini sistemde doğrulanmış bilgi yoksa kesin vaat etme.
- İş baskıya/teklife hazır hale geldiğinde kısa bir özetle personele aktarılacağını söyle.
- Müşteri birden fazla ürün istiyorsa her ürünü ayrı kalem olarak tut; bilgileri birbirine karıştırma.
- 'Nasıl yardımcı olabilirim?' gibi gereksiz tekrarlar yapma; konuşmanın kaldığı yerden devam et.
- Emoji kullanımı çok sınırlı olsun; profesyonel matbaa görüşmesinde gerekmedikçe emoji kullanma.
PROMPT;
    }

    private function result(array $state, array $missing, string $reply, string $status): array
    {
        $state['status'] = $status;
        $state['updated_at'] = now()->toIso8601String();

        return [
            'reply' => $reply,
            'state' => $state,
            'missing' => $missing,
            'status' => $status,
        ];
    }

    private function humanSummary(array $state): string
    {
        $slots = $state['slots'] ?? [];
        $product = $this->catalog->get($state['product'] ?? null);
        $parts = [];

        if (isset($slots['quantity'])) {
            $parts[] = number_format((int) $slots['quantity'], 0, ',', '.').' adet';
        }
        if (isset($slots['size'])) {
            $parts[] = $slots['size'];
        }
        if (isset($slots['paper'])) {
            $parts[] = $slots['paper'];
        }
        if (isset($slots['sides'])) {
            $parts[] = $slots['sides'] === 'double' ? 'çift yön' : 'tek yön';
        }
        if (isset($slots['lamination'])) {
            $parts[] = $slots['lamination'] === 'yok' ? 'selefonsuz' : $slots['lamination'].' selefon';
        }
        if ($product) {
            $parts[] = $product['label'];
        }

        return implode(', ', $parts);
    }
}
