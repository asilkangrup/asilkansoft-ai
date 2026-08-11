<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class OpenAIService
{
    /*
    |--------------------------------------------------------------------------
    | ANA CEVAP
    |--------------------------------------------------------------------------
    */

    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        $input = $this->inputHazirla($mesajlar);

        if ($input === []) {
            return 'Lütfen bir mesaj yazın.';
        }

        /*
        |--------------------------------------------------------------------------
        | SADECE SELAMLAŞMA
        |--------------------------------------------------------------------------
        |
        | Basit selamlaşmalarda ürün veritabanını ve uzun ürün promptunu
        | hazırlamaya gerek yoktur.
        |
        */

        $sonKullaniciMesaji =
            $this->sonKullaniciMesajiniGetir($input);

        if ($this->sadeceSelamlamaMi($sonKullaniciMesaji)) {
            $firmaAdi =
                trim((string) ($aiBot?->company_name ?? ''));

            if ($firmaAdi !== '') {
                return "Merhaba 👋 {$firmaAdi}'ne hoş geldiniz. Size nasıl yardımcı olabilirim?";
            }

            return 'Merhaba 👋 Hoş geldiniz. Size nasıl yardımcı olabilirim?';
        }

        try {
            $response = OpenAI::responses()->create([
                'model' =>
                    $aiBot?->openai_model ?: 'gpt-5-mini',

                'instructions' =>
                    $this->promptHazirla(
                        aiBot: $aiBot,
                        mesajlar: $input,
                    ),

                'input' => $input,
            ]);

            $cevap =
                trim($response->outputText ?? '');

            return $cevap !== ''
                ? $cevap
                : 'Şu anda uygun bir yanıt oluşturamadım. Mesajınızı biraz daha açık yazar mısınız?';

        } catch (Throwable $exception) {
            report($exception);

            return 'Yapay zekâ bağlantısında geçici bir sorun oluştu. Lütfen kısa bir süre sonra tekrar deneyin.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PROMPT HAZIRLA
    |--------------------------------------------------------------------------
    */

    private function promptHazirla(
        ?AiBot $aiBot,
        array $mesajlar
    ): string {
        $rol = match ($aiBot?->role) {
            'support' =>
                'profesyonel bir müşteri temsilcisi',

            'technical' =>
                'profesyonel bir teknik destek uzmanı',

            'assistant' =>
                'profesyonel bir sekreter ve asistan',

            default =>
                'deneyimli ve profesyonel bir satış uzmanı',
        };

        $prompt = <<<PROMPT
Sen {$rol}sın.

TEMEL DAVRANIŞLAR
- Her zaman doğal ve akıcı Türkçe konuş.
- Gerçek bir insan çalışan gibi konuş.
- Kurumsal fakat sıcak ol.
- Gereksiz uzun cevaplar verme.
- Genellikle 1–4 kısa cümle kullan.
- Müşterinin yalnızca sorduğu konuya cevap ver.
- Müşterinin ihtiyacını anlamaya çalış.
- Uygun olduğunda konuşmayı satışa yönlendir.
- Baskıcı veya ısrarcı davranma.
- Önceki konuşmaları dikkate al.
- Müşterinin daha önce verdiği bilgileri tekrar sorma.
- Bilmediğin hiçbir fiyat, ürün, stok, kampanya veya şirket bilgisini uydurma.
- Sistem sana bilgi vermediyse bunu açıkça belirt.
- Aynı cümleleri sürekli tekrarlama.
- Gerektiğinde en fazla 1–2 emoji kullan.
- Müşteri belirli bir soru sorduysa gereksiz karşılama metni yazma.
- Müşteri sormadıysa ilgisiz kampanya veya ürünlerden bahsetme.

HAFIZA DAVRANIŞI
- Yeni mesajı önceki mesajların devamı olarak değerlendir.
- “5 kilo”, “kart olsun”, “o ürün”, “1 litre olan” gibi kısa mesajların önceki konuşmayla bağlantısını kur.
- Daha önce konuşulan ürün veya tercihi unutma.
- Müşteriye aynı soruyu tekrar tekrar sorma.

SATIŞ DAVRANIŞI
- Önce müşterinin ihtiyacını öğren.
- Aynı anda çok fazla soru sorma.
- Her mesajda mümkünse tek bir sonraki adımı ilerlet.
- Müşteri kararsızsa uygun seçenekler sun.
- Müşteri satın almaya hazırsa gerekli bilgileri adım adım iste.
- Müşteriyi zorlamadan satışın tamamlanmasına yardımcı ol.

SİPARİŞ DAVRANIŞI
Gerekli olduğunda şu bilgileri doğal biçimde tamamla:
1. Ürün veya hizmet
2. Miktar / tercih
3. Ad soyad
4. Telefon
5. Adres / teslimat bilgileri
6. Ödeme tercihi

Eksik bilgiler varken sipariş tamamlandı deme.
PROMPT;

        if (! $aiBot) {
            return $prompt;
        }

        /*
        |--------------------------------------------------------------------------
        | FİRMA BİLGİLERİ
        |--------------------------------------------------------------------------
        */

        $prompt .= "\n\nFİRMA BİLGİLERİ\n";

        $prompt .=
            'Firma Adı: '
            .($aiBot->company_name ?: 'Tanımlanmadı')
            ."\n";

        $prompt .=
            'Yapay Zekâ Adı: '
            .($aiBot->name ?: 'Tanımlanmadı')
            ."\n";

        if ($aiBot->website) {
            $prompt .=
                "Web Sitesi: {$aiBot->website}\n";
        }

        if ($aiBot->instagram) {
            $prompt .=
                "Instagram: {$aiBot->instagram}\n";
        }

        if ($aiBot->company_description) {
            $prompt .=
                "\nFİRMA HAKKINDA\n"
                .$aiBot->company_description
                ."\n";
        }

        if ($aiBot->working_hours) {
            $prompt .=
                "\nÇALIŞMA SAATLERİ\n"
                .$aiBot->working_hours
                ."\n";
        }

        if ($aiBot->cargo_information) {
            $prompt .=
                "\nKARGO VE TESLİMAT\n"
                .$aiBot->cargo_information
                ."\n";
        }

        if ($aiBot->payment_information) {
            $prompt .=
                "\nÖDEME BİLGİLERİ\n"
                .$aiBot->payment_information
                ."\n";
        }

        if ($aiBot->return_policy) {
            $prompt .=
                "\nİADE VE DEĞİŞİM\n"
                .$aiBot->return_policy
                ."\n";
        }

        if ($aiBot->company_rules) {
            $prompt .=
                "\nÖZEL FİRMA KURALLARI\n"
                .$aiBot->company_rules
                ."\n";
        }

        if ($aiBot->system_prompt) {
            $prompt .=
                "\nÖZEL YAPAY ZEKÂ TALİMATLARI\n"
                .$aiBot->system_prompt
                ."\n";
        }

        /*
        |--------------------------------------------------------------------------
        | AKILLI ÜRÜN ARAMA
        |--------------------------------------------------------------------------
        |
        | Eski sistem 30 ürün veya daha az olduğunda bütün ürünleri
        | her mesajda GPT'ye gönderiyordu.
        |
        | Yeni sistem müşterinin konuşmasına göre yalnızca en alakalı
        | ürünleri seçer.
        |
        */

        $urunler =
            $this->ilgiliUrunleriGetir(
                aiBot: $aiBot,
                mesajlar: $mesajlar,
            );

        if ($urunler->isNotEmpty()) {
            $prompt .=
                "\n\nMÜŞTERİNİN KONUŞMASIYLA İLGİLİ ÜRÜNLER\n";

            foreach ($urunler as $product) {
                $prompt .=
                    "\n- Ürün: {$product->name}\n";

                if ($product->category) {
                    $prompt .=
                        "  Kategori: {$product->category}\n";
                }

                if ($product->price !== null) {
                    $fiyat = number_format(
                        (float) $product->price,
                        2,
                        ',',
                        '.'
                    );

                    $prompt .=
                        "  Fiyat: {$fiyat} TL\n";
                }

                $stokDurumu =
                    match ($product->stock_status) {
                        'in_stock' =>
                            'Stokta',

                        'out_of_stock' =>
                            'Stokta Yok',

                        'pre_order' =>
                            'Ön Sipariş',

                        default =>
                            $product->stock_status,
                    };

                $prompt .=
                    "  Stok Durumu: {$stokDurumu}\n";

                /*
                 * Açıklamalar çok uzun olabiliyor.
                 * Promptu gereksiz büyütmemek için sınırlandırıyoruz.
                 */
                if ($product->description) {
                    $aciklama =
                        Str::limit(
                            trim(
                                (string) $product->description
                            ),
                            350,
                            '...'
                        );

                    $prompt .=
                        "  Açıklama: {$aciklama}\n";
                }
            }

            $prompt .= <<<PROMPT


ÜRÜN KURALLARI
- Yukarıdaki ürün bilgilerini güvenilir veri olarak kullan.
- Ürün fiyatı tanımlıysa müşteriye doğru fiyatı söyle.
- Fiyatı tanımlanmamış ürün için fiyat uydurma.
- Stokta olmayan ürünü stokta gibi gösterme.
- Müşteri ürün adını tam yazmasa bile konuşma bağlamından doğru ürünü anlamaya çalış.
- “O ürün”, “1 litre olan”, “mega olan” gibi ifadelerde önceki konuşmayı dikkate al.
- Birden fazla uygun ürün varsa müşterinin ihtiyacına göre seçenek sun.
- Müşteri “en ucuz”, “en pahalı” veya benzeri karşılaştırma yaparsa yalnızca verilen ürünler arasında karşılaştırma yap.
- Bütün ürün listesini gereksiz yere müşteriye gönderme.
- Müşterinin sormadığı başka ürünleri veya kampanyaları gereksiz yere anlatma.
PROMPT;

        } else {
            $prompt .= <<<PROMPT


ÜRÜN ARAMA NOTU
Müşterinin konuşmasıyla doğrudan eşleşen ürün bilgisi bulunamadı.
Bu, ürünün kesinlikle olmadığı anlamına gelmez.
Ürün hakkında bilgi uydurma.
Gerekirse müşteriden ürün adını veya kategorisini biraz daha net belirtmesini iste.
PROMPT;
        }

        /*
        |--------------------------------------------------------------------------
        | SON KURALLAR
        |--------------------------------------------------------------------------
        */

        $prompt .= <<<PROMPT


ÇOK ÖNEMLİ
- Yukarıdaki firma ve ürün bilgilerini gerçek bilgi olarak kullan.
- Tanımlanmamış fiyat, stok, kampanya veya teslimat bilgisi uydurma.
- Müşterinin yalnızca sorduğu konuya cevap ver.
- Firma ve ürün bilgilerini tek mesajda topluca dökme.
- Konuşmayı doğal şekilde adım adım ilerlet.
- Müşterinin önceki mesajlarını unutma.
- Satış fırsatı varsa doğal biçimde bir sonraki adıma yönlendir.
- Müşteri belirli bir ürünün fiyatını soruyorsa önce doğrudan o ürünün fiyatını söyle.
- Müşteri sormadığı sürece ilgisiz kampanya veya ürün tanıtımı yapma.
- Her mesajı yeniden karşılama mesajıyla başlatma.
PROMPT;

        return $prompt;
    }

    /*
    |--------------------------------------------------------------------------
    | İLGİLİ ÜRÜNLERİ BUL
    |--------------------------------------------------------------------------
    */

    private function ilgiliUrunleriGetir(
        AiBot $aiBot,
        array $mesajlar
    ): Collection {
        $aramaMetni =
            $this->aramaMetniHazirla($mesajlar);

        if ($aramaMetni === '') {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | GENEL KATALOG SORUSU MU?
        |--------------------------------------------------------------------------
        |
        | "Ürünleriniz neler?"
        | "Neler satıyorsunuz?"
        | "Fiyat listesi"
        |
        | gibi sorularda daha geniş ürün listesi gerekir.
        |
        */

        if ($this->genelUrunSorusuMu($aramaMetni)) {
            return $aiBot->products()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        $kelimeler =
            $this->aramaKelimeleriHazirla(
                $aramaMetni
            );

        if ($kelimeler === []) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | ÜRÜNLERİ ÇEK
        |--------------------------------------------------------------------------
        |
        | Küçük kataloglarda veritabanından aktif ürünleri çekmek ucuzdur.
        | Fakat GPT'ye tamamını göndermiyoruz.
        |
        | Önce PHP tarafında puanlayıp yalnızca en alakalı ürünleri seçiyoruz.
        |
        */

        $adaylar =
            $aiBot->products()
                ->where('is_active', true)
                ->get();

        if ($adaylar->isEmpty()) {
            return collect();
        }

        $normalizeArama =
            Str::lower(
                $this->turkceNormalize(
                    $aramaMetni
                )
            );

        $puanlanan =
            $adaylar->map(
                function (Product $product) use (
                    $kelimeler,
                    $normalizeArama
                ) {
                    $puan = 0;

                    $urunAdi =
                        Str::lower(
                            $this->turkceNormalize(
                                (string) $product->name
                            )
                        );

                    $kategori =
                        Str::lower(
                            $this->turkceNormalize(
                                (string) $product->category
                            )
                        );

                    $aciklama =
                        Str::lower(
                            $this->turkceNormalize(
                                (string) $product->description
                            )
                        );

                    /*
                     * Ürün adının tamamına yakın eşleşme çok değerlidir.
                     */
                    if (
                        $urunAdi !== ''
                        && str_contains(
                            $normalizeArama,
                            $urunAdi
                        )
                    ) {
                        $puan += 100;
                    }

                    foreach ($kelimeler as $kelime) {
                        if (
                            str_contains(
                                $urunAdi,
                                $kelime
                            )
                        ) {
                            $puan += 15;
                        }

                        if (
                            str_contains(
                                $kategori,
                                $kelime
                            )
                        ) {
                            $puan += 7;
                        }

                        if (
                            str_contains(
                                $aciklama,
                                $kelime
                            )
                        ) {
                            $puan += 2;
                        }
                    }

                    /*
                     * Sayısal ifadeler ürün seçiminde önemlidir.
                     * Örnek: 1 litre, 5 litre, 5 kg.
                     */
                    preg_match_all(
                        '/\d+(?:[.,]\d+)?/u',
                        $normalizeArama,
                        $aramaSayilari
                    );

                    foreach (
                        $aramaSayilari[0] ?? []
                        as $sayi
                    ) {
                        if (
                            str_contains(
                                $urunAdi,
                                $sayi
                            )
                        ) {
                            $puan += 20;
                        }
                    }

                    $product->setAttribute(
                        '_arama_puani',
                        $puan
                    );

                    return $product;
                }
            );

        /*
         * Sıfır puanlı ürünleri GPT'ye göndermiyoruz.
         *
         * En fazla 6 alakalı ürün yeterlidir.
         */

        return $puanlanan
            ->filter(
                fn (Product $product): bool =>
                    (int) $product->getAttribute(
                        '_arama_puani'
                    ) > 0
            )
            ->sortByDesc('_arama_puani')
            ->take(6)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | SON MESAJLARDAN ARAMA BAĞLAMI
    |--------------------------------------------------------------------------
    */

    private function aramaMetniHazirla(
        array $mesajlar
    ): string {
        $kullaniciMesajlari =
            collect($mesajlar)
                ->filter(
                    fn (array $mesaj): bool =>
                        ($mesaj['role'] ?? null)
                        === 'user'
                )
                ->pluck('content')
                ->filter()
                ->take(-3)
                ->implode(' ');

        return trim($kullaniciMesajlari);
    }

    /*
    |--------------------------------------------------------------------------
    | ARAMA KELİMELERİNİ HAZIRLA
    |--------------------------------------------------------------------------
    */

    private function aramaKelimeleriHazirla(
        string $metin
    ): array {
        $metin =
            Str::lower(
                $this->turkceNormalize(
                    $metin
                )
            );

        $metin =
            preg_replace(
                '/[^\pL\pN\s]+/u',
                ' ',
                $metin
            ) ?? '';

        $kelimeler =
            preg_split(
                '/\s+/u',
                $metin,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

        $gereksizKelimeler = [
            'bir',
            'bu',
            'şu',
            'su',
            've',
            'ile',
            'için',
            'icin',
            'mi',
            'mı',
            'mu',
            'mü',
            'ne',
            'nedir',
            'kaç',
            'kac',
            'para',
            'fiyat',
            'fiyatı',
            'fiyati',
            'fiyatini',
            'fiyatını',
            'var',
            'olan',
            'olsun',
            'istiyorum',
            'isterim',
            'almak',
            'peki',
            'tamam',
            'evet',
            'hayır',
            'hayir',
            'bana',
            'ver',
            'nedir',
            'acaba',
            'öğrenebilir',
            'ogrenebilir',
            'musunuz',
            'misiniz',
            'miyim',
            'miyim',
        ];

        return collect($kelimeler)
            ->map(
                fn (string $kelime): string =>
                    trim($kelime)
            )
            ->filter(
                fn (string $kelime): bool =>
                    mb_strlen($kelime) >= 2
            )
            ->reject(
                fn (string $kelime): bool =>
                    in_array(
                        $kelime,
                        $gereksizKelimeler,
                        true
                    )
            )
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | GENEL ÜRÜN SORUSU
    |--------------------------------------------------------------------------
    */

    private function genelUrunSorusuMu(
        string $metin
    ): bool {
        $metin =
            Str::lower(
                $this->turkceNormalize(
                    $metin
                )
            );

        $ifadeler = [
            'urunleriniz neler',
            'urunler neler',
            'neler satiyorsunuz',
            'ne satiyorsunuz',
            'hangi urunler',
            'urun cesitleri',
            'urun listesi',
            'fiyat listesi',
            'tum urunler',
            'butun urunler',
            'urunlerinizi goster',
            'urunleri goster',
        ];

        foreach ($ifadeler as $ifade) {
            if (str_contains($metin, $ifade)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SADECE SELAMLAŞMA MI?
    |--------------------------------------------------------------------------
    */

    private function sadeceSelamlamaMi(
        string $mesaj
    ): bool {
        if (trim($mesaj) === '') {
            return false;
        }

        $mesaj =
            Str::lower(
                $this->turkceNormalize(
                    trim($mesaj)
                )
            );

        $mesaj =
            preg_replace(
                '/[^\pL\pN\s]+/u',
                '',
                $mesaj
            ) ?? $mesaj;

        $mesaj =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $mesaj
                ) ?? $mesaj
            );

        $selamlar = [
            'merhaba',
            'selam',
            'selamlar',
            'slm',
            'sa',
            'gunaydin',
            'iyi gunler',
            'iyi aksamlar',
            'iyi geceler',
            'merhabalar',
        ];

        return in_array(
            $mesaj,
            $selamlar,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SON KULLANICI MESAJINI GETİR
    |--------------------------------------------------------------------------
    */

    private function sonKullaniciMesajiniGetir(
        array $mesajlar
    ): string {
        for (
            $i = count($mesajlar) - 1;
            $i >= 0;
            $i--
        ) {
            if (
                ($mesajlar[$i]['role'] ?? null)
                !== 'user'
            ) {
                continue;
            }

            return trim(
                (string) (
                    $mesajlar[$i]['content']
                    ?? ''
                )
            );
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | TÜRKÇE KARAKTER NORMALİZASYONU
    |--------------------------------------------------------------------------
    */

    private function turkceNormalize(
        string $metin
    ): string {
        return strtr(
            $metin,
            [
                'İ' => 'i',
                'I' => 'i',
                'ı' => 'i',

                'Ş' => 's',
                'ş' => 's',

                'Ğ' => 'g',
                'ğ' => 'g',

                'Ü' => 'u',
                'ü' => 'u',

                'Ö' => 'o',
                'ö' => 'o',

                'Ç' => 'c',
                'ç' => 'c',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OPENAI INPUT
    |--------------------------------------------------------------------------
    */

    private function inputHazirla(
        string|array $mesajlar
    ): array {
        if (is_string($mesajlar)) {
            $mesaj = trim($mesajlar);

            return $mesaj === ''
                ? []
                : [
                    [
                        'role' => 'user',
                        'content' => $mesaj,
                    ],
                ];
        }

        $input = [];

        foreach ($mesajlar as $mesaj) {
            $role =
                $mesaj['role'] ?? null;

            $content =
                trim(
                    (string) (
                        $mesaj['content'] ?? ''
                    )
                );

            if (
                ! in_array(
                    $role,
                    [
                        'user',
                        'assistant',
                    ],
                    true
                )
            ) {
                continue;
            }

            if ($content === '') {
                continue;
            }

            $input[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        /*
         * Çok uzun konuşmalarda bütün geçmişi her istekte
         * tekrar göndermiyoruz.
         *
         * Son 12 mesaj günlük WhatsApp satış konuşmaları için
         * yeterli bağlam sağlar ve token yükünü sınırlar.
         */

        if (count($input) > 12) {
            $input =
                array_slice(
                    $input,
                    -12
                );
        }

        return $input;
    }
}