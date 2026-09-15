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
            $prefix = $this->isGreeting($message) ? 'Merhaba, tabii. ' : '';
            return $this->result($state, $missing, $prefix.self::QUESTIONS['product'], 'collecting', ['product']);
        }

        $product = $this->catalog->get($state['product']);
        $label = $product['label'] ?? 'baskı işi';

        if ($missing === []) {
            $design = $state['slots']['design_status'] ?? null;
            if ($design === null) {
                $reply = $this->paperPreferenceAcknowledgement($state)
                    ."Tasarım dosyanız hazırsa buradan gönderebilirsiniz. Hazır değilse tasarım desteği gerektiğini söylemeniz yeterli.";
                return $this->result($state, [], trim($reply), 'awaiting_design', ['design_status']);
            }

            if ($design === 'ready' && empty($state['attachments'])) {
                $reply = "Tasarım hazırsa dosyayı PDF, JPG veya PNG olarak buradan gönderebilirsiniz. Dosya gelince işi teklif için hazır hale getireceğim.";
                return $this->result($state, [], $reply, 'awaiting_file', ['design_file']);
            }

            $reply = "{$label} talebiniz teklif hazırlanabilecek seviyede. Bilgileri ekibe aktaracağım; net fiyat kontrol edilmeden kesin rakam paylaşmayacağım.";
            return $this->result($state, [], $reply, 'quote_ready', []);
        }

        $questionText = implode(' ', array_map(
            static fn (string $field) => self::QUESTIONS[$field] ?? ucfirst(str_replace('_', ' ', $field)).' bilgisini de alabilir miyim?',
            $questions
        ));

        // Her turda siparişin tamamını tekrar okuyup "not aldım" deme. Yalnızca
        // ilk ürün seçiminde kısa teyit ver, devamında doğrudan eksik bilgiye geç.
        $previousPending = $state['pending_fields'] ?? [];
        $isFirstProductTurn = in_array('product', $previousPending, true);
        $prefix = $isFirstProductTurn
            ? ucfirst($label)." için ilerleyelim. "
            : $this->paperPreferenceAcknowledgement($state);

        return $this->result($state, $missing, trim($prefix.$questionText), 'collecting', $questions);
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
Sen Türkiye'deki profesyonel bir matbaa/ofset baskı işletmesinin WhatsApp satış danışmanısın.

Konuşma kuralları:
- Türkçe, doğal, kısa ve insan gibi konuş. Robot, form veya çağrı merkezi dili kullanma.
- Müşterinin yazdığı bilgileri ASLA tekrar sorma. Tek mesajda verdiği tüm ayrıntıları kullan.
- Her turda sipariş özetini baştan sayma; "not aldım", "tamamdır" gibi kalıpları peş peşe tekrarlama.
- Müşteri "yok", "tercihim yok", "fark etmez" derse son sorulan alan için bunu geçerli cevap kabul et; aynı soruyu tekrar sorma.
- Tercihi olmayan müşteriye kesin teknik özellik uydurma. Uygun standart seçeneğin ekipçe önerilebileceğini söyle ve akışı ilerlet.
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

    private function paperPreferenceAcknowledgement(array $state): string
    {
        return (($state['slots']['paper'] ?? null) === 'no_preference')
            ? 'Sorun değil, kağıt/gramaj için uygun standart seçeneği önerebiliriz. '
            : '';
    }

    private function isGreeting(string $message): bool
    {
        $text = mb_strtolower(trim($message), 'UTF-8');

        return preg_match('/^(?:merhaba|selam|selamlar|iyi günler|iyi gunler|günaydın|gunaydin)[!. ]*$/u', $text) === 1;
    }
}
