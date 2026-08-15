<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadScoringService
{
    private const MAX_MESSAGE_CHANGE = 40;

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

    private const VERY_HIGH_INTENT = [
        'satin almak istiyorum',
        'siparis vermek istiyorum',
        'siparis olusturalim',
        'siparis veriyorum',
        'basvuru yapmak istiyorum',
        'basvuru yapalim',
        'randevu almak istiyorum',
        'randevu olusturalim',
        'odeme yapmak istiyorum',
        'hemen almak istiyorum',
        'nasil satin alabilirim',
        'baslayalim',
        'islemi baslatalim',
    ];

    private const HIGH_INTENT = [
        'fiyat nedir',
        'fiyat ne kadar',
        'kac tl',
        'ucreti nedir',
        'ucreti ne kadar',
        'fiyat teklifi',
        'teklif',
        'kampanya',
        'indirim',
        'odeme',
        'taksit',
        'stok',
        'stokta',
        'teslimat',
        'kargo',
        'basvuru',
        'randevu',
        'satin alma',
        'siparis',
    ];

    private const MEDIUM_INTENT = [
        'bilgi almak istiyorum',
        'detayli bilgi',
        'nasil oluyor',
        'nasil calisiyor',
        'sartlar',
        'kosullar',
        'secenekler',
        'hangi urun',
        'hangi hizmet',
        'urunler',
        'hizmetler',
        'yardimci olur musunuz',
        'ilgileniyorum',
    ];

    private const URGENCY_INTENT = [
        'acil',
        'hemen',
        'bugun',
        'simdi',
        'en kisa surede',
        'yarin',
    ];

    private const CONTACT_INTENT = [
        'beni arayin',
        'arayabilir misiniz',
        'telefonla goruselim',
        'iletisime gecin',
        'numaram',
        'adresim',
        'mail adresim',
        'e posta adresim',
        'whatsappdan yazin',
    ];

    private const NEGATIVE_INTENT = [
        'istemiyorum',
        'ilgilenmiyorum',
        'vazgectim',
        'cok pahali',
        'butcemi asiyor',
        'almayacagim',
        'basvuru yapmayacagim',
        'tesekkurler istemiyorum',
        'bir daha yazmayin',
        'iptal etmek istiyorum',
    ];

    private const PROFILE_LABELS = [
        'general' => 'Genel Satış',
        'ecommerce' => 'E-Ticaret / Ürün Satışı',
        'finance' => 'Finans / Kredi',
        'appointment' => 'Randevu / Klinik',
        'service' => 'Ajans / Profesyonel Hizmet',
        'real_estate' => 'Emlak',
        'automotive' => 'Otomotiv',
        'emergency' => 'Acil Hizmet',
    ];

    protected function activityService(): CrmActivityService
    {
        return app(CrmActivityService::class);
    }

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

        $normalized = $this->normalize($message);
        $profile = $this->profile($conversation);

        $oldScore = max(
            0,
            min(100, (int) $conversation->lead_score)
        );

        $scoreChange = 0;
        $signals = [];

        if ($this->isSocialMessage($normalized)) {
            if ($oldScore === 0) {
                $scoreChange += 5;
                $signals[] = 'conversation_started';
            }

            return $this->applyScore(
                conversation: $conversation,
                oldScore: $oldScore,
                scoreChange: $scoreChange,
                signals: $signals,
                profile: $profile,
                normalizedMessage: $normalized,
            );
        }

        if ($this->containsAny($normalized, self::VERY_HIGH_INTENT)) {
            $scoreChange += 30;
            $signals[] = 'very_high_purchase_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, self::HIGH_INTENT)) {
            $scoreChange += 12;
            $signals[] = 'high_purchase_intent';
        }

        if ($this->containsAny($normalized, self::MEDIUM_INTENT)) {
            $scoreChange += 6;
            $signals[] = 'information_intent';
        }

        if ($this->containsAny($normalized, self::URGENCY_INTENT)) {
            $scoreChange += 8;
            $signals[] = 'urgency';
        }

        if ($this->containsAny($normalized, self::CONTACT_INTENT)) {
            $scoreChange += 12;
            $signals[] = 'contact_request';
        }

        if ($this->hasPhoneNumber($message)) {
            $scoreChange += 10;
            $signals[] = 'phone_shared';
        }

        if ($this->hasEmail($message)) {
            $scoreChange += 6;
            $signals[] = 'email_shared';
        }

        if (mb_strlen($message) >= 35) {
            $scoreChange += 2;
            $signals[] = 'detailed_message';
        }

        [$sectorScore, $sectorSignals] = $this->sectorScore(
            profile: $profile,
            message: $message,
            normalized: $normalized,
        );

        $scoreChange += $sectorScore;
        $signals = array_values(array_unique([
            ...$signals,
            ...$sectorSignals,
        ]));

        if ($this->containsAny($normalized, self::NEGATIVE_INTENT)) {
            $scoreChange -= 35;
            $signals[] = 'negative_intent';
        }

        return $this->applyScore(
            conversation: $conversation,
            oldScore: $oldScore,
            scoreChange: $scoreChange,
            signals: array_values(array_unique($signals)),
            profile: $profile,
            normalizedMessage: $normalized,
        );
    }

    private function sectorScore(
        string $profile,
        string $message,
        string $normalized
    ): array {
        return match ($profile) {
            'ecommerce' => $this->ecommerceScore($message, $normalized),
            'finance' => $this->financeScore($message, $normalized),
            'appointment' => $this->appointmentScore($message, $normalized),
            'service' => $this->serviceScore($message, $normalized),
            'real_estate' => $this->realEstateScore($message, $normalized),
            'automotive' => $this->automotiveScore($message, $normalized),
            'emergency' => $this->emergencyScore($message, $normalized),
            default => [0, []],
        };
    }

    private function ecommerceScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'bundan istiyorum',
            'bunu istiyorum',
            'bunu alayim',
            'almak istiyorum',
            'siparis verelim',
            'siparis gecelim',
            'gonderin',
        ])) {
            $score += 18;
            $signals[] = 'ecommerce_order_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'kapida odeme',
            'kapida kart',
            'havale',
            'kredi karti',
            'odeme nasil',
            'taksit var mi',
        ])) {
            $score += 8;
            $signals[] = 'ecommerce_payment_intent';
        }

        if ($this->containsAny($normalized, [
            'kargo ucretsiz',
            'kargo ne kadar',
            'ne zaman gelir',
            'teslimat kac gun',
            'kargo hangi firma',
        ])) {
            $score += 6;
            $signals[] = 'ecommerce_delivery_intent';
        }

        if ($this->hasQuantity($normalized)) {
            $score += 10;
            $signals[] = 'ecommerce_quantity_selected';
        }

        if ($this->containsAny($normalized, [
            'adresim',
            'adres vereyim',
            'teslimat adresi',
        ])) {
            $score += 12;
            $signals[] = 'ecommerce_address_shared';
            $signals[] = 'ready_to_convert';
        }

        return [$score, $signals];
    }

    private function financeScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'kredi istiyorum',
            'finansman istiyorum',
            'basvuru yapmak istiyorum',
            'basvuru yapalim',
            'elden taksit istiyorum',
        ])) {
            $score += 18;
            $signals[] = 'finance_application_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'findeks',
            'kkb',
            'kredi notum',
            'kredi puanim',
            'limit puanim',
            'pasaj limitim',
            'limitim',
        ])) {
            $score += 10;
            $signals[] = 'finance_qualification_data';
        }

        if ($this->containsAny($normalized, [
            'hat benim adima',
            'hat sahibi benim',
            'hat benim',
            'kendi adima',
        ])) {
            $score += 10;
            $signals[] = 'finance_line_owner_confirmed';
        }

        if ($this->containsAny($normalized, [
            'vodafone',
            'turkcell',
            'turk telekom',
            'elden taksit',
            'fair finans',
            'banka kredisi',
        ])) {
            $score += 7;
            $signals[] = 'finance_product_selected';
        }

        if ($this->hasTcLikeNumber($message)) {
            $score += 12;
            $signals[] = 'finance_identity_data_shared';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'dogum tarihim',
            'dogum tarihi',
            'annemin kizlilik soyadi',
            'anne kizlilik soyadi',
            'sehir',
            'istanbuldayim',
            'ankaradayim',
            'izmirdeyim',
        ])) {
            $score += 7;
            $signals[] = 'finance_application_data_shared';
        }

        return [$score, $signals];
    }

    private function appointmentScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'randevu almak istiyorum',
            'randevu olusturalim',
            'randevu verin',
            'randevu ayarlayalim',
            'gelmek istiyorum',
        ])) {
            $score += 18;
            $signals[] = 'appointment_booking_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'musait misiniz',
            'bosluk var mi',
            'hangi gun musait',
            'hangi saat musait',
            'bugun musait',
            'yarin musait',
        ])) {
            $score += 10;
            $signals[] = 'appointment_availability_intent';
        }

        if ($this->hasDateOrTime($message, $normalized)) {
            $score += 10;
            $signals[] = 'appointment_time_selected';
        }

        if ($this->containsAny($normalized, [
            'hangi islem',
            'bu islemi istiyorum',
            'bu hizmeti istiyorum',
            'uygulama yaptirmak istiyorum',
            'kontrol icin gelmek istiyorum',
        ])) {
            $score += 7;
            $signals[] = 'appointment_service_selected';
        }

        return [$score, $signals];
    }

    private function serviceScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'teklif almak istiyorum',
            'teklif hazirlar misiniz',
            'fiyat teklifi',
            'proje icin teklif',
        ])) {
            $score += 14;
            $signals[] = 'service_quote_intent';
        }

        if ($this->containsAny($normalized, [
            'demo istiyorum',
            'demo yapalim',
            'toplanti yapalim',
            'gorusme ayarlayalim',
            'sunum yapalim',
        ])) {
            $score += 16;
            $signals[] = 'service_meeting_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'butcem',
            'butce',
            'aylik butce',
            'reklam butcesi',
            'proje butcesi',
        ])) {
            $score += 9;
            $signals[] = 'service_budget_shared';
        }

        if ($this->containsAny($normalized, [
            'baslayalim',
            'anlasalim',
            'calismak istiyorum',
            'hizmeti almak istiyorum',
        ])) {
            $score += 18;
            $signals[] = 'service_start_intent';
            $signals[] = 'ready_to_convert';
        }

        return [$score, $signals];
    }

    private function realEstateScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'ev almak istiyorum',
            'daire almak istiyorum',
            'kiralamak istiyorum',
            'satilik ev',
            'kiralik ev',
            'yatirim icin',
        ])) {
            $score += 12;
            $signals[] = 'real_estate_need_defined';
        }

        if ($this->containsAny($normalized, [
            'butcem',
            'maksimum butcem',
            'milyon tl',
            'pesinat',
            'kredi kullanacagim',
        ])) {
            $score += 10;
            $signals[] = 'real_estate_budget_shared';
        }

        if ($this->containsAny($normalized, [
            'hangi bolge',
            'bu bolgede',
            'mahalle',
            'ilce',
            'lokasyon',
            'konum',
        ])) {
            $score += 6;
            $signals[] = 'real_estate_location_defined';
        }

        if ($this->containsAny($normalized, [
            'evi gormek istiyorum',
            'daireyi gormek istiyorum',
            'yerinde gorelim',
            'randevu ayarlayalim',
            'ne zaman gorebilirim',
        ])) {
            $score += 18;
            $signals[] = 'real_estate_viewing_intent';
            $signals[] = 'ready_to_convert';
        }

        return [$score, $signals];
    }

    private function automotiveScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'araci almak istiyorum',
            'arabayi almak istiyorum',
            'bu araci istiyorum',
            'satin almak istiyorum',
        ])) {
            $score += 18;
            $signals[] = 'automotive_purchase_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'marka',
            'model',
            'model yili',
            'kilometre',
            'km',
            'paket',
            'motor',
        ])) {
            $score += 5;
            $signals[] = 'automotive_vehicle_interest';
        }

        if ($this->containsAny($normalized, [
            'test surusu',
            'araci gorebilir miyim',
            'araci gormek istiyorum',
            'ekspertiz',
            'randevu',
        ])) {
            $score += 14;
            $signals[] = 'automotive_inspection_intent';
        }

        if ($this->containsAny($normalized, [
            'takas',
            'kredi',
            'pesinat',
            'finansman',
            'son fiyat',
        ])) {
            $score += 8;
            $signals[] = 'automotive_commercial_intent';
        }

        return [$score, $signals];
    }

    private function emergencyScore(string $message, string $normalized): array
    {
        $score = 0;
        $signals = [];

        if ($this->containsAny($normalized, [
            'yolda kaldim',
            'arac calismiyor',
            'lastik patladi',
            'kaza yaptim',
            'kapida kaldim',
            'anahtar icerde kaldi',
            'su basmasi',
            'elektrik yok',
            'acil usta',
            'cekici lazim',
        ])) {
            $score += 20;
            $signals[] = 'emergency_active_problem';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'hemen gelin',
            'acil gelin',
            'simdi gelebilir misiniz',
            'ne kadar surede gelirsiniz',
        ])) {
            $score += 16;
            $signals[] = 'emergency_dispatch_intent';
            $signals[] = 'ready_to_convert';
        }

        if ($this->containsAny($normalized, [
            'konum atayim',
            'konumum',
            'adresim',
            'buradayim',
            'bulundugum yer',
        ])) {
            $score += 12;
            $signals[] = 'emergency_location_shared';
        }

        if ($this->hasPhoneNumber($message)) {
            $score += 5;
            $signals[] = 'emergency_contact_ready';
        }

        return [$score, $signals];
    }

    private function applyScore(
        ConversationControl $conversation,
        int $oldScore,
        int $scoreChange,
        array $signals,
        string $profile,
        string $normalizedMessage
    ): array {
        $scoreChange = max(
            -self::MAX_MESSAGE_CHANGE,
            min(self::MAX_MESSAGE_CHANGE, $scoreChange)
        );

        if (
            $scoreChange > 0
            && $this->isRepeatedCustomerMessage(
                conversation: $conversation,
                normalizedMessage: $normalizedMessage
            )
        ) {
            $scoreChange = (int) max(
                1,
                round($scoreChange * 0.25)
            );

            $signals[] = 'repeat_message_dampened';
        }

        $newScore = max(
            0,
            min(100, $oldScore + $scoreChange)
        );

        $temperature = $this->temperatureFromScore($newScore);

        $oldStatus = $conversation->lead_status ?: 'new';
        $oldTemperature = $conversation->lead_temperature ?: 'cold';

        $newStatus = $this->determineStatus(
            conversation: $conversation,
            score: $newScore,
            signals: $signals,
        );

        if (in_array($oldStatus, ['won', 'lost'], true)) {
            $newStatus = $oldStatus;
        }

        $data = [
            'lead_score' => $newScore,
            'lead_temperature' => $temperature,
            'last_contact_at' => now(),
        ];

        if ($newStatus !== $oldStatus) {
            $data['lead_status'] = $newStatus;
        }

        $conversation->update($data);

        $this->syncAutomaticTags(
            conversation: $conversation,
            score: $newScore,
            signals: $signals,
            profile: $profile,
        );

        $conversation->refresh();

        $activityService = $this->activityService();

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
                'conversation_id' => $conversation->id,
                'ai_bot_id' => $conversation->ai_bot_id,
                'profile' => $profile,
                'old_score' => $oldScore,
                'change' => $scoreChange,
                'new_score' => $newScore,
                'temperature' => $temperature,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'signals' => array_values(array_unique($signals)),
            ]
        );

        return [
            'profile' => $profile,
            'old_score' => $oldScore,
            'change' => $scoreChange,
            'score' => $newScore,
            'temperature' => $temperature,
            'status' => $newStatus,
            'signals' => array_values(array_unique($signals)),
        ];
    }

    private function determineStatus(
        ConversationControl $conversation,
        int $score,
        array $signals
    ): string {
        $current = $conversation->lead_status ?: 'new';

        if (
            in_array('ready_to_convert', $signals, true)
            || in_array('very_high_purchase_intent', $signals, true)
        ) {
            if ($this->statusRank($current) < $this->statusRank('proposal')) {
                return 'proposal';
            }
        }

        if ($score >= 70) {
            if ($this->statusRank($current) < $this->statusRank('qualified')) {
                return 'qualified';
            }
        }

        if ($score >= 30 && $current === 'new') {
            return 'contacted';
        }

        return $current;
    }

    private function statusRank(string $status): int
    {
        return match ($status) {
            'new' => 1,
            'contacted' => 2,
            'qualified' => 3,
            'proposal' => 4,
            'won', 'lost' => 5,
            default => 1,
        };
    }

    private function temperatureFromScore(int $score): string
    {
        return match (true) {
            $score >= 70 => 'hot',
            $score >= 40 => 'warm',
            default => 'cold',
        };
    }

    private function syncAutomaticTags(
        ConversationControl $conversation,
        int $score,
        array $signals,
        string $profile
    ): void {
        foreach (
            [
                'Sıcak Lead',
                'Ilık Lead',
                'Soğuk Lead',
                'SÄ±cak Lead',
                'IlÄ±k Lead',
                'SoÄŸuk Lead',
            ] as $tag
        ) {
            if ($conversation->etiketiVarMi($tag)) {
                $conversation->etiketSil($tag);
            }
        }

        if ($score >= 70) {
            $conversation->etiketEkle('Sıcak Lead');
        } elseif ($score >= 40) {
            $conversation->etiketEkle('Ilık Lead');
        } else {
            $conversation->etiketEkle('Soğuk Lead');
        }

        if (
            in_array('very_high_purchase_intent', $signals, true)
            || in_array('high_purchase_intent', $signals, true)
            || in_array('ready_to_convert', $signals, true)
        ) {
            $conversation->etiketEkle('Satın Alma Niyeti');
        }

        if (in_array('urgency', $signals, true)) {
            $conversation->etiketEkle('Acil');
        }

        if (in_array('contact_request', $signals, true)) {
            $conversation->etiketEkle('Geri Arama');
        }

        if ($profile !== 'general') {
            $conversation->etiketEkle(
                'Profil: '.(self::PROFILE_LABELS[$profile] ?? $profile)
            );
        }
    }

    private function profile(ConversationControl $conversation): string
    {
        $profile = trim(
            (string) (
                $conversation->aiBot?->lead_scoring_profile
                ?: 'general'
            )
        );

        return array_key_exists($profile, self::PROFILE_LABELS)
            ? $profile
            : 'general';
    }

    private function isRepeatedCustomerMessage(
        ConversationControl $conversation,
        string $normalizedMessage
    ): bool {
        if ($normalizedMessage === '') {
            return false;
        }

        $recentMessages = ChatMessage::query()
            ->where('user_id', $conversation->user_id)
            ->where('session_id', $conversation->session_id)
            ->where('sender_type', 'customer')
            ->latest('id')
            ->limit(6)
            ->pluck('message');

        $sameCount = $recentMessages
            ->map(fn ($item): string => $this->normalize((string) $item))
            ->filter(fn (string $item): bool => $item === $normalizedMessage)
            ->count();

        return $sameCount >= 2;
    }

    private function hasPhoneNumber(string $message): bool
    {
        return (bool) preg_match(
            '/(?:\+?90\s*)?0?5\d{2}[\s\-.]?\d{3}[\s\-.]?\d{2}[\s\-.]?\d{2}/u',
            $message
        );
    }

    private function hasEmail(string $message): bool
    {
        return filter_var(
            $this->extractEmail($message),
            FILTER_VALIDATE_EMAIL
        ) !== false;
    }

    private function hasTcLikeNumber(string $message): bool
    {
        return (bool) preg_match('/(?<!\d)\d{11}(?!\d)/u', $message);
    }

    private function hasQuantity(string $normalized): bool
    {
        return (bool) preg_match(
            '/\b\d+(?:[.,]\d+)?\s*(?:kg|kilo|gr|gram|lt|litre|l|adet|tane|paket|kutu)\b/u',
            $normalized
        );
    }

    private function hasDateOrTime(string $message, string $normalized): bool
    {
        if ($this->containsAny($normalized, [
            'bugun',
            'yarin',
            'pazartesi',
            'sali',
            'carsamba',
            'persembe',
            'cuma',
            'cumartesi',
            'pazar',
            'sabah',
            'ogle',
            'aksam',
        ])) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:[01]?\d|2[0-3])[:.]\d{2}\b|\b(?:0?[1-9]|[12]\d|3[01])[\/.\-](?:0?[1-9]|1[0-2])(?:[\/.\-]\d{2,4})?\b/u',
            $message
        );
    }

    private function containsAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = $this->normalize($keyword);

            if ($keyword !== '' && str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isSocialMessage(string $message): bool
    {
        return in_array($message, self::SOCIAL_MESSAGES, true);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        $text = strtr(
            $text,
            [
                'ı' => 'i',
                'ş' => 's',
                'ğ' => 'g',
                'ü' => 'u',
                'ö' => 'o',
                'ç' => 'c',
                'İ' => 'i',
                'I' => 'i',
                'Ş' => 's',
                'Ğ' => 'g',
                'Ü' => 'u',
                'Ö' => 'o',
                'Ç' => 'c',
            ]
        );

        $text = preg_replace(
            '/[^\pL\pN@.+\s]+/u',
            ' ',
            $text
        ) ?? '';

        return trim(
            preg_replace('/\s+/u', ' ', $text) ?? ''
        );
    }

    private function extractEmail(string $message): string
    {
        preg_match(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            $message,
            $matches
        );

        return $matches[0] ?? '';
    }

    private function result(
        ConversationControl $conversation,
        int $change,
        string $reason
    ): array {
        return [
            'profile' => $this->profile($conversation),
            'old_score' => (int) $conversation->lead_score,
            'change' => $change,
            'score' => (int) $conversation->lead_score,
            'temperature' => $conversation->lead_temperature ?: 'cold',
            'status' => $conversation->lead_status ?: 'new',
            'signals' => [$reason],
        ];
    }
}