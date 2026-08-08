<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;

class OrderService
{
    public function taslakSiparisiGetir(
        int $aiBotId,
        string $sessionId
    ): ?Order {
        return Order::query()
            ->where('ai_bot_id', $aiBotId)
            ->where('session_id', $sessionId)
            ->where('status', 'draft')
            ->latest('id')
            ->first();
    }

    public function taslakSiparisOlustur(
        int $aiBotId,
        string $sessionId,
        string $whatsappNumber
    ): Order {
        return Order::firstOrCreate(
            [
                'ai_bot_id' => $aiBotId,
                'session_id' => $sessionId,
                'status' => 'draft',
            ],
            [
                'whatsapp_number' => $whatsappNumber,
            ]
        );
    }

    public function mesajdanSiparisiGuncelle(
        Order $order,
        string $message,
        ?AiBot $aiBot = null
    ): Order {
        $message = trim($message);

        if ($message === '') {
            return $order;
        }

        $lower = Str::lower($message);

        /*
        |--------------------------------------------------------------------------
        | ÜRÜN
        |--------------------------------------------------------------------------
        */

        if (empty($order->products) && $aiBot) {
            $product = $this->mesajdanUrunBul(
                aiBot: $aiBot,
                message: $message
            );

            if ($product) {
                $order->products = $product->name;

                if (
                    $product->price !== null
                    && empty($order->total_amount)
                ) {
                    $miktar = $this->mesajdanSayisalMiktarBul($message);

                    if ($miktar !== null) {
                        $order->total_amount =
                            (float) $product->price * $miktar;
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MİKTAR
        |--------------------------------------------------------------------------
        */

        if (
            empty($order->quantity)
            && preg_match(
                '/\b(\d+(?:[.,]\d+)?)\s*(kg|kilo|adet|litre|lt|l)\b/ui',
                $message,
                $matches
            )
        ) {
            $miktar = str_replace(',', '.', $matches[1]);

            $birim = Str::lower($matches[2]);

            $birim = match ($birim) {
                'kilo' => 'kg',
                'lt', 'l' => 'litre',
                default => $birim,
            };

            $order->quantity = $miktar.' '.$birim;
        }

        /*
        |--------------------------------------------------------------------------
        | TOPLAM TUTAR
        |--------------------------------------------------------------------------
        */

        if (
            $aiBot
            && ! empty($order->products)
            && ! empty($order->quantity)
            && empty($order->total_amount)
        ) {
            $product = $aiBot->products()
                ->where('is_active', true)
                ->where('name', $order->products)
                ->first();

            if ($product && $product->price !== null) {
                $miktar = $this->quantityDegeriniBul(
                    $order->quantity
                );

                if ($miktar !== null) {
                    $order->total_amount =
                        (float) $product->price * $miktar;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ÖDEME YÖNTEMİ
        |--------------------------------------------------------------------------
        */

        if (empty($order->payment_method)) {
            if (
                str_contains($lower, 'kapıda kart')
                || str_contains($lower, 'kapida kart')
            ) {
                $order->payment_method = 'card_on_delivery';

            } elseif (
                str_contains($lower, 'kapıda nakit')
                || str_contains($lower, 'kapida nakit')
                || $lower === 'nakit'
            ) {
                $order->payment_method = 'cash_on_delivery';

            } elseif (
                str_contains($lower, 'havale')
                || str_contains($lower, 'eft')
            ) {
                $order->payment_method = 'bank_transfer';

            } elseif (
                str_contains($lower, 'kredi kartı')
                || str_contains($lower, 'kredi karti')
            ) {
                $order->payment_method = 'credit_card';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | AD SOYAD
        |--------------------------------------------------------------------------
        */

        if (empty($order->customer_name)) {
            if (
                preg_match(
                    '/(?:adım|adim|ismim|ad soyadım|ad soyadim)\s*[:\-]?\s*(.+)/ui',
                    $message,
                    $matches
                )
            ) {
                $isim = trim($matches[1]);

                if ($this->gecerliIsimMi($isim)) {
                    $order->customer_name = $isim;
                }

            } elseif (
                ! empty($order->products)
                && ! empty($order->quantity)
                && $this->gecerliIsimMi($message)
                && ! $this->siparisKomutuMu($message)
            ) {
                $order->customer_name = $message;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | ADRES
        |--------------------------------------------------------------------------
        */

        if (empty($order->address)) {
            if (
                preg_match(
                    '/(?:adresim|adres)\s*[:\-]?\s*(.+)/ui',
                    $message,
                    $matches
                )
            ) {
                $adres = trim($matches[1]);

                if ($this->gecerliAdresMi($adres)) {
                    $order->address = $adres;
                }

            } elseif (
                ! empty($order->customer_name)
                && $this->gecerliAdresMi($message)
                && ! $this->siparisKomutuMu($message)
            ) {
                $order->address = $message;
            }
        }

        $order->save();

        return $order->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | EKSİK ALANLAR
    |--------------------------------------------------------------------------
    */

    public function eksikAlanlar(Order $order): array
    {
        $eksikler = [];

        if (empty($order->products)) {
            $eksikler[] = 'ürün';
        }

        if (empty($order->quantity)) {
            $eksikler[] = 'miktar';
        }

        if (empty($order->customer_name)) {
            $eksikler[] = 'ad soyad';
        }

        if (empty($order->address)) {
            $eksikler[] = 'adres';
        }

        if (empty($order->payment_method)) {
            $eksikler[] = 'ödeme yöntemi';
        }

        return $eksikler;
    }

    /*
    |--------------------------------------------------------------------------
    | SIRADAKİ EKSİK BİLGİYİ SOR
    |--------------------------------------------------------------------------
    */

    public function siradakiEksikSoru(Order $order): ?string
    {
        if (empty($order->products)) {
            return 'Hangi ürünü almak istediğinizi yazar mısınız?';
        }

        if (empty($order->quantity)) {
            return 'Kaç kg, litre veya adet istediğinizi yazar mısınız?';
        }

        if (empty($order->customer_name)) {
            return 'Siparişi oluşturabilmem için ad soyad bilginizi yazar mısınız?';
        }

        if (empty($order->address)) {
            return 'Teslimat adresinizi yazar mısınız?';
        }

        if (empty($order->payment_method)) {
            return 'Ödeme tercihiniz kapıda nakit mi, kapıda kart mı?';
        }

        return null;
    }

    public function siparisTamamlanmayaHazirMi(Order $order): bool
    {
        return $this->eksikAlanlar($order) === [];
    }

    /*
    |--------------------------------------------------------------------------
    | SİPARİŞ ONAYI
    |--------------------------------------------------------------------------
    */

    public function onayMesajiMi(string $message): bool
    {
        $message = Str::lower(trim($message));

        $ifadeler = [
            'onaylıyorum',
            'onayliyorum',
            'onay',
            'siparişi onaylıyorum',
            'siparisi onayliyorum',
            'evet onaylıyorum',
            'evet onayliyorum',
            'tamam onay',
            'tamamdır onaylıyorum',
            'tamamdir onayliyorum',
        ];

        return in_array($message, $ifadeler, true);
    }

    public function siparisiOnayla(Order $order): Order
    {
        if (! $this->siparisTamamlanmayaHazirMi($order)) {
            return $order;
        }

        $order->update([
            'status' => 'pending',
        ]);

        return $order->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | SİPARİŞ ÖZETİ
    |--------------------------------------------------------------------------
    */

    public function siparisOzeti(Order $order): string
    {
        $odeme = match ($order->payment_method) {
            'cash_on_delivery' => 'Kapıda Nakit',
            'card_on_delivery' => 'Kapıda Kart',
            'bank_transfer' => 'Havale / EFT',
            'credit_card' => 'Kredi Kartı',
            default => 'Belirtilmedi',
        };

        $tutar = $order->total_amount !== null
            ? number_format(
                (float) $order->total_amount,
                2,
                ',',
                '.'
            ).' TL'
            : 'Henüz hesaplanmadı';

        return
            "📦 Sipariş Özeti\n\n".
            "Ürün: ".($order->products ?: '-')."\n".
            "Miktar: ".($order->quantity ?: '-')."\n".
            "Ad Soyad: ".($order->customer_name ?: '-')."\n".
            "Adres: ".($order->address ?: '-')."\n".
            "Ödeme: {$odeme}\n".
            "Toplam: {$tutar}";
    }

    /*
    |--------------------------------------------------------------------------
    | İSİM KONTROLÜ
    |--------------------------------------------------------------------------
    */

    private function gecerliIsimMi(string $isim): bool
    {
        $isim = trim($isim);

        if (
            mb_strlen($isim) < 2
            || mb_strlen($isim) > 80
        ) {
            return false;
        }

        if (preg_match('/\d/u', $isim)) {
            return false;
        }

        $kelimeler = preg_split(
            '/\s+/u',
            $isim,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (
            count($kelimeler) < 1
            || count($kelimeler) > 4
        ) {
            return false;
        }

        return (bool) preg_match(
            "/^[\p{L}\s'-]+$/u",
            $isim
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADRES KONTROLÜ
    |--------------------------------------------------------------------------
    */

    private function gecerliAdresMi(string $adres): bool
    {
        $adres = trim($adres);

        if (mb_strlen($adres) < 10) {
            return false;
        }

        $adresLower = Str::lower($adres);

        $adresIpuclari = [
            'mahallesi',
            'mahalle',
            'mah.',
            'sokak',
            'sok.',
            'cadde',
            'caddesi',
            'cad.',
            'bulvar',
            'bulvarı',
            'bulvari',
            'no ',
            'no:',
            'apartman',
            'apt',
            'site',
            'kat',
            'daire',
        ];

        foreach ($adresIpuclari as $ipucu) {
            if (str_contains($adresLower, $ipucu)) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SİPARİŞ KOMUTU KONTROLÜ
    |--------------------------------------------------------------------------
    */

    private function siparisKomutuMu(string $message): bool
    {
        $message = Str::lower(trim($message));

        $yasakKelimeler = [
            'onay',
            'onaylıyorum',
            'onayliyorum',
            'nakit',
            'kart',
            'kapıda kart',
            'kapida kart',
            'kapıda nakit',
            'kapida nakit',
            'havale',
            'eft',
            'adres',
            'adresim',
            'evet',
            'hayır',
            'hayir',
            'tamam',
        ];

        foreach ($yasakKelimeler as $kelime) {
            if ($message === $kelime) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | ÜRÜN BULMA
    |--------------------------------------------------------------------------
    */

    private function mesajdanUrunBul(
        AiBot $aiBot,
        string $message
    ): ?Product {
        $messageNormalized = $this->normalize($message);

        $products = $aiBot->products()
            ->where('is_active', true)
            ->get();

        $bestProduct = null;
        $bestScore = 0;

        foreach ($products as $product) {
            $name = $this->normalize($product->name);

            $category = $this->normalize(
                (string) $product->category
            );

            $score = 0;

            $nameWords = preg_split(
                '/\s+/u',
                $name,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            foreach ($nameWords as $word) {
                if (
                    mb_strlen($word) >= 2
                    && str_contains($messageNormalized, $word)
                ) {
                    $score += 10;
                }
            }

            if (
                $category !== ''
                && str_contains($messageNormalized, $category)
            ) {
                $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProduct = $product;
            }
        }

        return $bestScore >= 10
            ? $bestProduct
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | MİKTAR BULMA
    |--------------------------------------------------------------------------
    */

    private function mesajdanSayisalMiktarBul(
        string $message
    ): ?float {
        if (
            preg_match(
                '/\b(\d+(?:[.,]\d+)?)\s*(?:kg|kilo|adet|litre|lt|l)\b/ui',
                $message,
                $matches
            )
        ) {
            return (float) str_replace(
                ',',
                '.',
                $matches[1]
            );
        }

        return null;
    }

    private function quantityDegeriniBul(
        string $quantity
    ): ?float {
        if (
            preg_match(
                '/(\d+(?:[.,]\d+)?)/',
                $quantity,
                $matches
            )
        ) {
            return (float) str_replace(
                ',',
                '.',
                $matches[1]
            );
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | METİN NORMALLEŞTİRME
    |--------------------------------------------------------------------------
    */

    private function normalize(string $text): string
    {
        $text = Str::lower($text);

        return strtr($text, [
            'ı' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
        ]);
    }
}