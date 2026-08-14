<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadScoringService
{
    /*
    |--------------------------------------------------------------------------
    | CRM AKTİVİTE SERVİSİ
    |--------------------------------------------------------------------------
    */

    protected function activityService(): CrmActivityService
    {
        return app(
            CrmActivityService::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | WAI SMART LEAD SCORING
    |--------------------------------------------------------------------------
    |
    | Bu servis mÃ¼ÅŸteri mesajlarÄ±nÄ± hÄ±zlÄ± ÅŸekilde analiz eder.
    |
    | Ã–NEMLÄ°:
    | Burada ekstra OpenAI isteÄŸi yapÄ±lmaz.
    | Bu nedenle WhatsApp cevap sÃ¼resini yavaÅŸlatmaz.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ã‡OK GÃœÃ‡LÃœ SATIN ALMA SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const VERY_HIGH_INTENT = [
        'satÄ±n almak istiyorum',
        'satin almak istiyorum',

        'sipariÅŸ vermek istiyorum',
        'siparis vermek istiyorum',

        'sipariÅŸ oluÅŸturalÄ±m',
        'siparis olusturalim',

        'baÅŸvuru yapmak istiyorum',
        'basvuru yapmak istiyorum',

        'baÅŸvuru yapalÄ±m',
        'basvuru yapalim',

        'randevu almak istiyorum',

        'randevu oluÅŸturalÄ±m',
        'randevu olusturalim',

        'hemen almak istiyorum',

        'nasÄ±l satÄ±n alabilirim',
        'nasil satin alabilirim',

        'Ã¶deme yapmak istiyorum',
        'odeme yapmak istiyorum',

        'satÄ±n alÄ±yorum',
        'satin aliyorum',

        'sipariÅŸ veriyorum',
        'siparis veriyorum',
    ];

    /*
    |--------------------------------------------------------------------------
    | GÃœÃ‡LÃœ SATIÅ SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const HIGH_INTENT = [
        'fiyat nedir',
        'fiyatÄ± nedir',
        'fiyati nedir',

        'fiyat ne kadar',

        'kaÃ§ tl',
        'kac tl',

        'Ã¼creti nedir',
        'ucreti nedir',

        'Ã¼creti ne kadar',
        'ucreti ne kadar',

        'teklif',
        'fiyat teklifi',

        'kampanya',
        'indirim',

        'Ã¶deme',
        'odeme',

        'taksit',

        'stok',
        'stokta',

        'teslimat',
        'kargo',

        'baÅŸvuru',
        'basvuru',

        'randevu',

        'satÄ±n alma',
        'satin alma',

        'sipariÅŸ',
        'siparis',
    ];

    /*
    |--------------------------------------------------------------------------
    | ORTA SEVÄ°YE Ä°LGÄ° SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const MEDIUM_INTENT = [
        'bilgi almak istiyorum',

        'detaylÄ± bilgi',
        'detayli bilgi',

        'nasÄ±l oluyor',
        'nasil oluyor',

        'nasÄ±l Ã§alÄ±ÅŸÄ±yor',
        'nasil calisiyor',

        'ÅŸartlar',
        'sartlar',

        'koÅŸullar',
        'kosullar',

        'seÃ§enekler',
        'secenekler',

        'hangi Ã¼rÃ¼n',
        'hangi urun',

        'hangi hizmet',

        'Ã¼rÃ¼nler',
        'urunler',

        'hizmetler',

        'yardÄ±mcÄ± olur musunuz',
        'yardimci olur musunuz',

        'ilgileniyorum',
    ];

    /*
    |--------------------------------------------------------------------------
    | ACÄ°LÄ°YET SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const URGENCY_INTENT = [
        'acil',

        'hemen',

        'bugÃ¼n',
        'bugun',

        'ÅŸimdi',
        'simdi',

        'en kÄ±sa sÃ¼rede',
        'en kisa surede',

        'yarÄ±n',
        'yarin',
    ];

    /*
    |--------------------------------------------------------------------------
    | Ä°LETÄ°ÅÄ°M / DÃ–NÃœÅ SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const CONTACT_INTENT = [
        'beni arayÄ±n',
        'beni arayin',

        'arayabilir misiniz',

        'telefonla gÃ¶rÃ¼ÅŸelim',
        'telefonla goruselim',

        'iletiÅŸime geÃ§in',
        'iletisime gecin',

        'numaram',

        'adresim',

        'mail adresim',
        'e posta adresim',
    ];

    /*
    |--------------------------------------------------------------------------
    | OLUMSUZ / SATIÅTAN UZAKLAÅMA SÄ°NYALLERÄ°
    |--------------------------------------------------------------------------
    */

    private const NEGATIVE_INTENT = [
        'istemiyorum',

        'ilgilenmiyorum',

        'vazgeÃ§tim',
        'vazgectim',

        'Ã§ok pahalÄ±',
        'cok pahali',

        'bÃ¼tÃ§emi aÅŸÄ±yor',
        'butcemi asiyor',

        'almayacaÄŸÄ±m',
        'almayacagim',

        'baÅŸvuru yapmayacaÄŸÄ±m',
        'basvuru yapmayacagim',

        'teÅŸekkÃ¼rler istemiyorum',
        'tesekkurler istemiyorum',

        'bir daha yazmayÄ±n',
        'bir daha yazmayin',
    ];

    /*
    |--------------------------------------------------------------------------
    | BASÄ°T SOSYAL MESAJLAR
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
        | BASÄ°T SOSYAL MESAJ
        |--------------------------------------------------------------------------
        |
        | Merhaba yazan bir kiÅŸiyi doÄŸrudan sÄ±cak lead yapmÄ±yoruz.
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
        | Ã‡OK GÃœÃ‡LÃœ SATIN ALMA NÄ°YETÄ°
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
        | GÃœÃ‡LÃœ SATIÅ NÄ°YETÄ°
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
        | ORTA SEVÄ°YE Ä°LGÄ°
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
        | ACÄ°LÄ°YET
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
        | Ä°NSANLA Ä°LETÄ°ÅÄ°M TALEBÄ°
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
        | TELEFON NUMARASI PAYLAÅILDI MI?
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
        | E-POSTA PAYLAÅILDI MI?
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
        | MESAJ UZUNLUÄU / DETAYLI Ä°LGÄ°
        |--------------------------------------------------------------------------
        |
        | Ã‡ok kÄ±sa olmayan gerÃ§ek bir soru mÃ¼ÅŸterinin aktif ilgisini gÃ¶sterir.
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
        | OLUMSUZ NÄ°YET
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
        | TEKRAR MESAJLARDA PUANIN Ã‡ILGINCA ARTMASINI ENGELLE
        |--------------------------------------------------------------------------
        |
        | Tek mesajda maksimum +40 / -40 deÄŸiÅŸim.
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

        $oldTemperature =
            $conversation->lead_temperature
            ?: 'cold';

        $newStatus =
            $this->determineStatus(
                conversation: $conversation,
                score: $newScore,
                signals: $signals,
            );

        /*
        |--------------------------------------------------------------------------
        | KAZANILDI / KAYBEDÄ°LDÄ° DURUMLARINI OTOMATÄ°K BOZMA
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
        | OTOMATÄ°K CRM ETÄ°KETLERÄ°
        |--------------------------------------------------------------------------
        */

        $this->syncAutomaticTags(
            conversation: $conversation,
            score: $newScore,
            signals: $signals,
        );

        /*
        |--------------------------------------------------------------------------
        | CRM AKTİVİTE GEÇMİŞİ
        |--------------------------------------------------------------------------
        |
        | Bu değişiklikler müşteri mesajı analizinden otomatik geldiği için
        | performedBy null bırakılır. Timeline actorName() bunu WAI Yapay Zekâ
        | olarak gösterir.
        |
        */

        $conversation->refresh();

        $activityService =
            $this->activityService();

        $activityService->leadScoreChanged(
            conversation: $conversation,
            oldScore: $oldScore,
            newScore: (int) $conversation->lead_score,
            performedBy: null,
        );

        $activityService->temperatureChanged(
            conversation: $conversation,
            oldTemperature: $oldTemperature,
            newTemperature: $conversation->lead_temperature ?: 'cold',
            performedBy: null,
        );

        $activityService->leadStatusChanged(
            conversation: $conversation,
            oldStatus: $oldStatus,
            newStatus: $conversation->lead_status ?: 'new',
            performedBy: null,
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
    | PIPELINE DURUMUNU BELÄ°RLE
    |--------------------------------------------------------------------------
    |
    | Burada WAI satÄ±ÅŸ pipeline'Ä±nÄ± otomatik ilerletebilir.
    |
    | Ancak kritik nokta:
    | "KazanÄ±ldÄ±" hiÃ§bir zaman otomatik yapÄ±lmaz.
    | GerÃ§ek satÄ±ÅŸ tamamlandÄ±ÄŸÄ±nda kullanÄ±cÄ± veya sipariÅŸ sistemi yapar.
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
        | SATIN ALMA / BAÅVURU / RANDEVU NÄ°YETÄ° Ã‡OK GÃœÃ‡LÃœ
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
        | AKTÄ°F Ä°LGÄ°
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
    | OTOMATÄ°K ETÄ°KETLER
    |--------------------------------------------------------------------------
    */

    private function syncAutomaticTags(
        ConversationControl $conversation,
        int $score,
        array $signals
    ): void {
        /*
        |--------------------------------------------------------------------------
        | SICAKLIK ETÄ°KETLERÄ°NÄ° TEMÄ°ZLE
        |--------------------------------------------------------------------------
        */

        foreach (
            [
                'SÄ±cak Lead',
                'IlÄ±k Lead',
                'SoÄŸuk Lead',
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
        | YENÄ° SICAKLIK ETÄ°KETÄ°
        |--------------------------------------------------------------------------
        */

        if ($score >= 70) {
            $conversation->etiketEkle(
                'SÄ±cak Lead'
            );
        } elseif ($score >= 40) {
            $conversation->etiketEkle(
                'IlÄ±k Lead'
            );
        } else {
            $conversation->etiketEkle(
                'SoÄŸuk Lead'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SATIN ALMA NÄ°YETÄ°
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
                'SatÄ±n Alma Niyeti'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ACÄ°L
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
        | GERÄ° ARAMA
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
    | HERHANGÄ° BÄ°R Ä°FADE VAR MI?
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
    | NORMALÄ°ZE ET
    |--------------------------------------------------------------------------
    */

    private function normalize(
        string $text
    ): string {
        $text = Str::lower(
            strtr(
                $text,
                [
                    'Ä°' => 'i',
                    'I' => 'i',
                    'Ä±' => 'i',

                    'Å' => 's',
                    'ÅŸ' => 's',

                    'Ä' => 'g',
                    'ÄŸ' => 'g',

                    'Ãœ' => 'u',
                    'Ã¼' => 'u',

                    'Ã–' => 'o',
                    'Ã¶' => 'o',

                    'Ã‡' => 'c',
                    'Ã§' => 'c',
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
    | E-POSTA Ã‡IKAR
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
    | DEÄÄ°ÅÄ°KLÄ°K YOKSA SONUÃ‡
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