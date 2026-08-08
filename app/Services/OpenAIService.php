<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class OpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        $input = $this->inputHazirla($mesajlar);

        if ($input === []) {
            return 'Lütfen bir mesaj yazın.';
        }

        try {
            $response = OpenAI::responses()->create([
                'model' => $aiBot?->openai_model ?: 'gpt-5-mini',

                'instructions' => $this->promptHazirla(
                    aiBot: $aiBot,
                    mesajlar: $input,
                ),

                'input' => $input,
            ]);

            $cevap = trim($response->outputText ?? '');

            return $cevap !== ''
                ? $cevap
                : 'Şu anda uygun bir yanıt oluşturamadım. Mesajınızı biraz daha açık yazar mısınız?';

        } catch (Throwable $exception) {
            report($exception);

            return 'Yapay zekâ bağlantısında geçici bir sorun oluştu. Lütfen kısa bir süre sonra tekrar deneyin.';
        }
    }

    private function promptHazirla(
        ?AiBot $aiBot,
        array $mesajlar
    ): string {
        $rol = match ($aiBot?->role) {
            'support' => 'profesyonel bir müşteri temsilcisi',
            'technical' => 'profesyonel bir teknik destek uzmanı',
            'assistant' => 'profesyonel bir sekreter ve asistan',
            default => 'deneyimli ve profesyonel bir satış uzmanı',
        };

        $prompt = <<<PROMPT
Sen {$rol}sın.

TEMEL DAVRANIŞLAR
- Her zaman doğal ve akıcı Türkçe konuş.
- Gerçek bir insan çalışan gibi konuş.
- Kurumsal fakat sıcak ol.
- Gereksiz uzun cevaplar verme.
- Genellikle 2–5 kısa cümle kullan.
- Müşterinin ihtiyacını anlamaya çalış.
- Uygun olduğunda konuşmayı satışa yönlendir.
- Baskıcı veya ısrarcı davranma.
- Önceki konuşmaları mutlaka dikkate al.
- Müşterinin daha önce verdiği bilgileri tekrar sorma.
- Bilmediğin hiçbir fiyat, ürün, stok, kampanya veya şirket bilgisini uydurma.
- Sistem sana bilgi vermediyse bunu açıkça belirt.
- Aynı cümleleri sürekli tekrarlama.
- Gerektiğinde en fazla 1–2 emoji kullan.

SELAMLAŞMA
Müşteri yalnızca merhaba, selam veya iyi günler gibi bir mesaj yazarsa kısa ve sıcak karşılık ver.

Örnek:
“Merhaba 👋 Hoş geldiniz. Size nasıl yardımcı olabilirim?”

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

        $prompt .= 'Firma Adı: '
            .($aiBot->company_name ?: 'Tanımlanmadı')
            ."\n";

        $prompt .= 'Yapay Zekâ Adı: '
            .($aiBot->name ?: 'Tanımlanmadı')
            ."\n";

        if ($aiBot->website) {
            $prompt .= "Web Sitesi: {$aiBot->website}\n";
        }

        if ($aiBot->instagram) {
            $prompt .= "Instagram: {$aiBot->instagram}\n";
        }

        if ($aiBot->company_description) {
            $prompt .= "\nFİRMA HAKKINDA\n{$aiBot->company_description}\n";
        }

        if ($aiBot->working_hours) {
            $prompt .= "\nÇALIŞMA SAATLERİ\n{$aiBot->working_hours}\n";
        }

        if ($aiBot->cargo_information) {
            $prompt .= "\nKARGO VE TESLİMAT\n{$aiBot->cargo_information}\n";
        }

        if ($aiBot->payment_information) {
            $prompt .= "\nÖDEME BİLGİLERİ\n{$aiBot->payment_information}\n";
        }

        if ($aiBot->return_policy) {
            $prompt .= "\nİADE VE DEĞİŞİM\n{$aiBot->return_policy}\n";
        }

        if ($aiBot->company_rules) {
            $prompt .= "\nÖZEL FİRMA KURALLARI\n{$aiBot->company_rules}\n";
        }

        if ($aiBot->system_prompt) {
            $prompt .= "\nÖZEL YAPAY ZEKÂ TALİMATLARI\n{$aiBot->system_prompt}\n";
        }

        /*
        |--------------------------------------------------------------------------
        | AKILLI ÜRÜN ARAMA
        |--------------------------------------------------------------------------
        */

        $urunler = $this->ilgiliUrunleriGetir(
            aiBot: $aiBot,
            mesajlar: $mesajlar,
        );

        if ($urunler->isNotEmpty()) {
            $prompt .= "\n\nMÜŞTERİNİN KONUŞMASIYLA İLGİLİ ÜRÜNLER\n";

            foreach ($urunler as $product) {
                $prompt .= "\n- Ürün: {$product->name}\n";

                if ($product->category) {
                    $prompt .= "  Kategori: {$product->category}\n";
                }

                if ($product->price !== null) {
                    $fiyat = number_format(
                        (float) $product->price,
                        2,
                        ',',
                        '.'
                    );

                    $prompt .= "  Fiyat: {$fiyat} TL\n";
                }

                $stokDurumu = match ($product->stock_status) {
                    'in_stock' => 'Stokta',
                    'out_of_stock' => 'Stokta Yok',
                    'pre_order' => 'Ön Sipariş',
                    default => $product->stock_status,
                };

                $prompt .= "  Stok Durumu: {$stokDurumu}\n";

                if ($product->description) {
                    $prompt .= "  Açıklama: {$product->description}\n";
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
- Müşteri “en ucuz”, “en pahalı” veya benzeri karşılaştırma yaparsa verilen ürünler arasında doğru karşılaştırmayı yap.
- Bütün ürün listesini gereksiz yere müşteriye gönderme.
PROMPT;
        } else {
            $prompt .= <<<PROMPT


ÜRÜN ARAMA NOTU
Müşterinin mesajıyla doğrudan eşleşen ürün bilgisi bulunamadı.
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
        /*
         * Öncelikle toplam aktif ürün sayısını öğreniyoruz.
         */
        $aktifUrunSayisi = $aiBot->products()
            ->where('is_active', true)
            ->count();

        if ($aktifUrunSayisi === 0) {
            return collect();
        }

        /*
         * Az ürün varsa arama yapmaya gerek yok.
         *
         * Örneğin 20 ürünlü bir işletmede hepsini GPT'ye vermek
         * hem daha güvenli hem de karşılaştırma sorularında daha başarılı.
         */
        if ($aktifUrunSayisi <= 30) {
            return $aiBot->products()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();
        }

        /*
         * Büyük kataloglarda müşterinin son birkaç mesajından
         * arama bağlamı oluşturuyoruz.
         */
        $aramaMetni = $this->aramaMetniHazirla($mesajlar);

        if ($aramaMetni === '') {
            return collect();
        }

        $kelimeler = $this->aramaKelimeleriHazirla($aramaMetni);

        if ($kelimeler === []) {
            return collect();
        }

        /*
         * Veritabanında sadece ilgili ürünleri arıyoruz.
         */
        $query = $aiBot->products()
            ->where('is_active', true);

        $query->where(function (Builder $query) use ($kelimeler): void {
            foreach ($kelimeler as $kelime) {
                $query->orWhere('name', 'like', "%{$kelime}%")
                    ->orWhere('category', 'like', "%{$kelime}%")
                    ->orWhere('description', 'like', "%{$kelime}%");
            }
        });

        $adaylar = $query
            ->limit(30)
            ->get();

        if ($adaylar->isEmpty()) {
            return collect();
        }

        /*
         * Bulunan ürünlere basit bir uygunluk puanı veriyoruz.
         * Ürün adındaki eşleşmeler kategori/açıklamadan daha değerlidir.
         */
        return $adaylar
            ->map(function (Product $product) use ($kelimeler) {
                $puan = 0;

                $urunAdi = Str::lower(
                    $this->turkceNormalize($product->name)
                );

                $kategori = Str::lower(
                    $this->turkceNormalize(
                        (string) $product->category
                    )
                );

                $aciklama = Str::lower(
                    $this->turkceNormalize(
                        (string) $product->description
                    )
                );

                foreach ($kelimeler as $kelime) {
                    if (str_contains($urunAdi, $kelime)) {
                        $puan += 10;
                    }

                    if (str_contains($kategori, $kelime)) {
                        $puan += 5;
                    }

                    if (str_contains($aciklama, $kelime)) {
                        $puan += 2;
                    }
                }

                $product->setAttribute('_arama_puani', $puan);

                return $product;
            })
            ->sortByDesc('_arama_puani')
            ->take(12)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | SON MESAJLARDAN ARAMA BAĞLAMI
    |--------------------------------------------------------------------------
    */

    private function aramaMetniHazirla(array $mesajlar): string
    {
        $kullaniciMesajlari = collect($mesajlar)
            ->filter(
                fn (array $mesaj): bool =>
                    ($mesaj['role'] ?? null) === 'user'
            )
            ->pluck('content')
            ->filter()
            ->take(-4)
            ->implode(' ');

        return trim($kullaniciMesajlari);
    }

    /*
    |--------------------------------------------------------------------------
    | ARAMA KELİMELERİNİ HAZIRLA
    |--------------------------------------------------------------------------
    */

    private function aramaKelimeleriHazirla(string $metin): array
    {
        $metin = Str::lower(
            $this->turkceNormalize($metin)
        );

        $metin = preg_replace(
            '/[^\pL\pN\s]+/u',
            ' ',
            $metin
        ) ?? '';

        $kelimeler = preg_split(
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
            'var',
            'mı',
            'mi',
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
        ];

        return collect($kelimeler)
            ->map(fn (string $kelime): string => trim($kelime))
            ->filter(fn (string $kelime): bool => mb_strlen($kelime) >= 2)
            ->reject(
                fn (string $kelime): bool =>
                    in_array($kelime, $gereksizKelimeler, true)
            )
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | TÜRKÇE KARAKTER NORMALİZASYONU
    |--------------------------------------------------------------------------
    */

    private function turkceNormalize(string $metin): string
    {
        return strtr($metin, [
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
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OPENAI INPUT
    |--------------------------------------------------------------------------
    */

    private function inputHazirla(string|array $mesajlar): array
    {
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
            $role = $mesaj['role'] ?? null;
            $content = trim(
                (string) ($mesaj['content'] ?? '')
            );

            if (! in_array(
                $role,
                ['user', 'assistant'],
                true
            )) {
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

        return $input;
    }
}