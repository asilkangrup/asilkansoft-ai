<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadScoringService
{
    /*
    |--------------------------------------------------------------------------
    | WAI SMART LEAD SCORING
    |--------------------------------------------------------------------------
    |
    | Bu servis müşteri mesajlarını hızlı şekilde analiz eder.
    |
    | ÖNEMLİ:
    | Burada ekstra OpenAI isteği yapılmaz.
    | Bu nedenle WhatsApp cevap süresini yavaşlatmaz.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | ÇOK GÜÇLÜ SATIN ALMA SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const VERY_HIGH_INTENT = [
        'satın almak istiyorum',
        'satin almak istiyorum',

        'sipariş vermek istiyorum',
        'siparis vermek istiyorum',

        'sipariş oluşturalım',
        'siparis olusturalim',

        'başvuru yapmak istiyorum',
        'basvuru yapmak istiyorum',

        'başvuru yapalım',
        'basvuru yapalim',

        'randevu almak istiyorum',

        'randevu oluşturalım',
        'randevu olusturalim',

        'hemen almak istiyorum',

        'nasıl satın alabilirim',
        'nasil satin alabilirim',

        'ödeme yapmak istiyorum',
        'odeme yapmak istiyorum',

        'satın alıyorum',
        'satin aliyorum',

        'sipariş veriyorum',
        'siparis veriyorum',
    ];

    /*
    |--------------------------------------------------------------------------
    | GÜÇLÜ SATIŞ SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const HIGH_INTENT = [
        'fiyat nedir',
        'fiyatı nedir',
        'fiyati nedir',

        'fiyat ne kadar',

        'kaç tl',
        'kac tl',

        'ücreti nedir',
        'ucreti nedir',

        'ücreti ne kadar',
        'ucreti ne kadar',

        'teklif',
        'fiyat teklifi',

        'kampanya',
        'indirim',

        'ödeme',
        'odeme',

        'taksit',

        'stok',
        'stokta',

        'teslimat',
        'kargo',

        'başvuru',
        'basvuru',

        'randevu',

        'satın alma',
        'satin alma',

        'sipariş',
        'siparis',
    ];

    /*
    |--------------------------------------------------------------------------
    | ORTA SEVİYE İLGİ SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const MEDIUM_INTENT = [
        'bilgi almak istiyorum',

        'detaylı bilgi',
        'detayli bilgi',

        'nasıl oluyor',
        'nasil oluyor',

        'nasıl çalışıyor',
        'nasil calisiyor',

        'şartlar',
        'sartlar',

        'koşullar',
        'kosullar',

        'seçenekler',
        'secenekler',

        'hangi ürün',
        'hangi urun',

        'hangi hizmet',

        'ürünler',
        'urunler',

        'hizmetler',

        'yardımcı olur musunuz',
        'yardimci olur musunuz',

        'ilgileniyorum',
    ];

    /*
    |--------------------------------------------------------------------------
    | ACİLİYET SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const URGENCY_INTENT = [
        'acil',

        'hemen',

        'bugün',
        'bugun',

        'şimdi',
        'simdi',

        'en kısa sürede',
        'en kisa surede',

        'yarın',
        'yarin',
    ];

    /*
    |--------------------------------------------------------------------------
    | İLETİŞİM / DÖNÜŞ SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const CONTACT_INTENT = [
        'beni arayın',
        'beni arayin',

        'arayabilir misiniz',

        'telefonla görüşelim',
        'telefonla goruselim',

        'iletişime geçin',
        'iletisime gecin',

        'numaram',

        'adresim',

        'mail adresim',
        'e posta adresim',
    ];

    /*
    |--------------------------------------------------------------------------
    | OLUMSUZ / SATIŞTAN UZAKLAŞMA SİNYALLERİ
    |--------------------------------------------------------------------------
    */

    private const NEGATIVE_INTENT = [
        'istemiyorum',

        'ilgilenmiyorum',

        'vazgeçtim',
        'vazgectim',

        'çok pahalı',
        'cok pahali',

        'bütçemi aşıyor',
        'butcemi asiyor',

        'almayacağım',
        'almayacagim',

        'başvuru yapmayacağım',
        'basvuru yapmayacagim',

        'teşekkürler istemiyorum',
        'tesekkurler istemiyorum',

        'bir daha yazmayın',
        'bir daha yazmayin',
    ];

    /*
    |--------------------------------------------------------------------------
    | BASİT SOSYAL MESAJLAR
    |--------------------------------------------------------------------------
    */

    private const SOCIAL_MESSAGES = [
        'merhaba',
        'selam',
        'selamlar',

        'iyi gunler',
        'iyi aksamlar',
        'gunaydin',

        'tesekkurler',
        'tesekkur ederim',

        'sagol',
        'sag ol',

        'tamam',
        'peki',
        'ok',
        'okey',

        'evet',
        'hayir',
    ];

    /*
    |--------------------------------------------------------------------------
    | ANA PUANLAMA
    |--------------------------------------------------------------------------
    */

    public function puanla(
        ConversationControl $conversation,
        string $message
    ): array {
        $message = trim($message);

        if ($message === '') {
            return $this->result(
                conversation: $conversation,
                change: 0,
                reason: 'empty_message'
            );
        }

        $normalized =
            $this->normalize(
                $message
            );

        $oldScore =
            max(
                0,
                min(
                    100,
                    (int) $conversation->lead_score
                )
            );

        $scoreChange = 0;

        $signals = [];

        /*
        |--------------------------------------------------------------------------
        | BASİT SOSYAL MESAJ
        |--------------------------------------------------------------------------
        |
        | Merhaba yazan bir kişiyi doğrudan sıcak lead yapmıyoruz.
        |
        */

        if (
            $this->isSocialMessage(
                $normalized
            )
        ) {
            if ($oldScore === 0) {
                $scoreChange += 5;

                $signals[] =
                    'conversation_started';
            }

            return $this->applyScore(
                conversation: $conversation,
                oldScore: $oldScore,
                scoreChange: $scoreChange,
                signals: $signals,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ÇOK GÜÇLÜ SATIN ALMA NİYETİ
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::VERY_HIGH_INTENT
            )
        ) {
            $scoreChange += 35;

            $signals[] =
                'very_high_purchase_intent';
        }

        /*
        |--------------------------------------------------------------------------
        | GÜÇLÜ SATIŞ NİYETİ
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::HIGH_INTENT
            )
        ) {
            $scoreChange += 18;

            $signals[] =
                'high_purchase_intent';
        }

        /*
        |--------------------------------------------------------------------------
        | ORTA SEVİYE İLGİ
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::MEDIUM_INTENT
            )
        ) {
            $scoreChange += 10;

            $signals[] =
                'information_intent';
        }

        /*
        |--------------------------------------------------------------------------
        | ACİLİYET
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::URGENCY_INTENT
            )
        ) {
            $scoreChange += 12;

            $signals[] =
                'urgency';
        }

        /*
        |--------------------------------------------------------------------------
        | İNSANLA İLETİŞİM TALEBİ
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::CONTACT_INTENT
            )
        ) {
            $scoreChange += 15;

            $signals[] =
                'contact_request';
        }

        /*
        |--------------------------------------------------------------------------
        | TELEFON NUMARASI PAYLAŞILDI MI?
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/(?:\+?90\s*)?0?5\d{2}[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}/u',
                $message
            )
        ) {
            $scoreChange += 12;

            $signals[] =
                'phone_shared';
        }

        /*
        |--------------------------------------------------------------------------
        | E-POSTA PAYLAŞILDI MI?
        |--------------------------------------------------------------------------
        */

        if (
            filter_var(
                $this->extractEmail(
                    $message
                ),
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $scoreChange += 8;

            $signals[] =
                'email_shared';
        }

        /*
        |--------------------------------------------------------------------------
        | MESAJ UZUNLUĞU / DETAYLI İLGİ
        |--------------------------------------------------------------------------
        |
        | Çok kısa olmayan gerçek bir soru müşterinin aktif ilgisini gösterir.
        |
        */

        if (
            mb_strlen(
                $message
            ) >= 35
        ) {
            $scoreChange += 3;

            $signals[] =
                'detailed_message';
        }

        /*
        |--------------------------------------------------------------------------
        | OLUMSUZ NİYET
        |--------------------------------------------------------------------------
        */

        if (
            $this->containsAny(
                $normalized,
                self::NEGATIVE_INTENT
            )
        ) {
            $scoreChange -= 35;

            $signals[] =
                'negative_intent';
        }

        return $this->applyScore(
            conversation: $conversation,
            oldScore: $oldScore,
            scoreChange: $scoreChange,
            signals: $signals,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PUANI UYGULA
    |--------------------------------------------------------------------------
    */

    private function applyScore(
        ConversationControl $conversation,
        int $oldScore,
        int $scoreChange,
        array $signals
    ): array {
        /*
        |--------------------------------------------------------------------------
        | TEKRAR MESAJLARDA PUANIN ÇILGINCA ARTMASINI ENGELLE
        |--------------------------------------------------------------------------
        |
        | Tek mesajda maksimum +40 / -40 değişim.
        |
        */

        $scoreChange =
            max(
                -40,
                min(
                    40,
                    $scoreChange
                )
            );

        $newScore =
            max(
                0,
                min(
                    100,
                    $oldScore
                    + $scoreChange
                )
            );

        $temperature =
            $this->temperatureFromScore(
                $newScore
            );

        $oldStatus =
            $conversation->lead_status
            ?: 'new';

        $newStatus =
            $this->determineStatus(
                conversation: $conversation,
                score: $newScore,
                signals: $signals,
            );

        /*
        |--------------------------------------------------------------------------
        | KAZANILDI / KAYBEDİLDİ DURUMLARINI OTOMATİK BOZMA
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $oldStatus,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            $newStatus =
                $oldStatus;
        }

        $data = [
            'lead_score' =>
                $newScore,

            'lead_temperature' =>
                $temperature,

            'last_contact_at' =>
                now(),
        ];

        if (
            $newStatus !==
            $oldStatus
        ) {
            $data['lead_status'] =
                $newStatus;
        }

        $conversation->update(
            $data
        );

        /*
        |--------------------------------------------------------------------------
        | OTOMATİK CRM ETİKETLERİ
        |--------------------------------------------------------------------------
        */

        $this->syncAutomaticTags(
            conversation: $conversation,
            score: $newScore,
            signals: $signals,
        );

        Log::info(
            'WAI LEAD SCORE UPDATED',
            [
                'conversation_id' =>
                    $conversation->id,

                'ai_bot_id' =>
                    $conversation->ai_bot_id,

                'old_score' =>
                    $oldScore,

                'change' =>
                    $scoreChange,

                'new_score' =>
                    $newScore,

                'temperature' =>
                    $temperature,

                'old_status' =>
                    $oldStatus,

                'new_status' =>
                    $newStatus,

                'signals' =>
                    $signals,
            ]
        );

        return [
            'old_score' =>
                $oldScore,

            'change' =>
                $scoreChange,

            'score' =>
                $newScore,

            'temperature' =>
                $temperature,

            'status' =>
                $newStatus,

            'signals' =>
                $signals,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PIPELINE DURUMUNU BELİRLE
    |--------------------------------------------------------------------------
    |
    | Burada WAI satış pipeline'ını otomatik ilerletebilir.
    |
    | Ancak kritik nokta:
    | "Kazanıldı" hiçbir zaman otomatik yapılmaz.
    | Gerçek satış tamamlandığında kullanıcı veya sipariş sistemi yapar.
    |
    */

    private function determineStatus(
        ConversationControl $conversation,
        int $score,
        array $signals
    ): string {
        $current =
            $conversation->lead_status
            ?: 'new';

        /*
        |--------------------------------------------------------------------------
        | SATIN ALMA / BAŞVURU / RANDEVU NİYETİ ÇOK GÜÇLÜ
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'very_high_purchase_intent',
                $signals,
                true
            )
        ) {
            if (
                $this->statusRank(
                    $current
                )
                <
                $this->statusRank(
                    'proposal'
                )
            ) {
                return 'proposal';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SICAK LEAD
        |--------------------------------------------------------------------------
        */

        if ($score >= 70) {
            if (
                $this->statusRank(
                    $current
                )
                <
                $this->statusRank(
                    'qualified'
                )
            ) {
                return 'qualified';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | AKTİF İLGİ
        |--------------------------------------------------------------------------
        */

        if ($score >= 30) {
            if ($current === 'new') {
                return 'contacted';
            }
        }

        return $current;
    }

    /*
    |--------------------------------------------------------------------------
    | PIPELINE SIRASI
    |--------------------------------------------------------------------------
    */

    private function statusRank(
        string $status
    ): int {
        return match ($status) {
            'new' =>
                1,

            'contacted' =>
                2,

            'qualified' =>
                3,

            'proposal' =>
                4,

            'won' =>
                5,

            'lost' =>
                5,

            default =>
                1,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | SICAKLIK
    |--------------------------------------------------------------------------
    */

    private function temperatureFromScore(
        int $score
    ): string {
        return match (true) {
            $score >= 70 =>
                'hot',

            $score >= 40 =>
                'warm',

            default =>
                'cold',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | OTOMATİK ETİKETLER
    |--------------------------------------------------------------------------
    */

    private function syncAutomaticTags(
        ConversationControl $conversation,
        int $score,
        array $signals
    ): void {
        /*
        |--------------------------------------------------------------------------
        | SICAKLIK ETİKETLERİNİ TEMİZLE
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'Sıcak Lead',
                'Ilık Lead',
                'Soğuk Lead',
            ]
            as $tag
        ) {
            if (
                $conversation->etiketiVarMi(
                    $tag
                )
            ) {
                $conversation->etiketSil(
                    $tag
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | YENİ SICAKLIK ETİKETİ
        |--------------------------------------------------------------------------
        */

        if ($score >= 70) {
            $conversation->etiketEkle(
                'Sıcak Lead'
            );
        } elseif ($score >= 40) {
            $conversation->etiketEkle(
                'Ilık Lead'
            );
        } else {
            $conversation->etiketEkle(
                'Soğuk Lead'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SATIN ALMA NİYETİ
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'very_high_purchase_intent',
                $signals,
                true
            )
            ||
            in_array(
                'high_purchase_intent',
                $signals,
                true
            )
        ) {
            $conversation->etiketEkle(
                'Satın Alma Niyeti'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ACİL
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'urgency',
                $signals,
                true
            )
        ) {
            $conversation->etiketEkle(
                'Acil'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | GERİ ARAMA
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'contact_request',
                $signals,
                true
            )
        ) {
            $conversation->etiketEkle(
                'Geri Arama'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HERHANGİ BİR İFADE VAR MI?
    |--------------------------------------------------------------------------
    */

    private function containsAny(
        string $message,
        array $keywords
    ): bool {
        foreach (
            $keywords
            as $keyword
        ) {
            $keyword =
                $this->normalize(
                    $keyword
                );

            if (
                str_contains(
                    $message,
                    $keyword
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SOSYAL MESAJ MI?
    |--------------------------------------------------------------------------
    */

    private function isSocialMessage(
        string $message
    ): bool {
        return in_array(
            $message,
            self::SOCIAL_MESSAGES,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALİZE ET
    |--------------------------------------------------------------------------
    */

    private function normalize(
        string $text
    ): string {
        $text = Str::lower(
            strtr(
                $text,
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
            )
        );

        $text =
            preg_replace(
                '/[^\pL\pN@.+\s]+/u',
                ' ',
                $text
            )
            ?? '';

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $text
            )
            ?? ''
        );
    }

    /*
    |--------------------------------------------------------------------------
    | E-POSTA ÇIKAR
    |--------------------------------------------------------------------------
    */

    private function extractEmail(
        string $message
    ): string {
        preg_match(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            $message,
            $matches
        );

        return $matches[0]
            ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | DEĞİŞİKLİK YOKSA SONUÇ
    |--------------------------------------------------------------------------
    */

    private function result(
        ConversationControl $conversation,
        int $change,
        string $reason
    ): array {
        return [
            'old_score' =>
                (int) $conversation->lead_score,

            'change' =>
                $change,

            'score' =>
                (int) $conversation->lead_score,

            'temperature' =>
                $conversation->lead_temperature
                ?: 'cold',

            'status' =>
                $conversation->lead_status
                ?: 'new',

            'signals' => [
                $reason,
            ],
        ];
    }
}