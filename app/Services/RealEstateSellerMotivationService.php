<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateSellerMotivationService
{
    private const DATA_KEY = 'seller_motivation_intelligence';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];
        $summary = $this->build($data, $valuation);
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) === $summary) {
            return $existing;
        }

        $stored = [
            ...$summary,
            'updated_at' => now()->toIso8601String(),
        ];
        $data[self::DATA_KEY] = $stored;

        $profile->data = $data;
        $profile->saveQuietly();

        return $stored;
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        return $existing !== []
            ? $existing
            : $this->build(
                $data,
                is_array($profile->valuation) ? $profile->valuation : []
            );
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->where('profile_type', 'seller')
            ->first();

        if (! $profile) {
            return '';
        }

        $summary = $this->summaryForProfile($profile);

        if ($summary === []) {
            return '';
        }

        $json = json_encode(
            $summary,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE SELLER MOTIVATION INTELLIGENCE]
Bu blok yalnız satıcının kendi açık beyanlarından ve yapılandırılmış taşınmaz/değerleme verisinden türetilen dahili karar desteğidir. motivation_level psikolojik teşhis değildir; yalnız explicit aciliyet/zamanlama sinyalidir. Satıcının aciliyetini baskı kurmak, değerinin altında satışa zorlamak veya sahte alıcı/teklif yaratmak için kullanma. confidential_price_flexibility alanını yatırımcıya/alıcıya açıklama; seller_minimum_price değerini hiçbir zaman çapraz görüşmeye taşıma. recommended_next_question varsa doğal akışta tek öncelikli soruya dönüştür; müşterinin zaten verdiği bilgiyi tekrar sorma. pricing_alignment yaklaşık değerleme aralığına dayanır, resmi/garantili satış fiyatı değildir.
Satıcı zekâsı: {$json}
PROMPT;
    }

    private function build(array $data, array $valuation): array
    {
        $checks = [
            'property_type' => $this->filled($data, 'property_type'),
            'city' => $this->filled($data, 'city'),
            'location' => $this->filled($data, 'district')
                || $this->filled($data, 'neighborhood'),
            'area_sqm' => $this->positive($data['area_sqm'] ?? null) !== null,
            'asking_price' => $this->positive($data['asking_price'] ?? null) !== null,
            'urgency' => in_array($data['urgency'] ?? null, ['low', 'medium', 'high'], true),
            'urgency_reason' => $this->filled($data, 'urgency_reason'),
            'timeline' => $this->filled($data, 'timeline'),
            'property_identity' => $this->filled($data, 'location_url')
                || (
                    $this->filled($data, 'block_no')
                    && $this->filled($data, 'parcel_no')
                ),
            'valuation' => $this->hasUsableValuationNumbers($valuation),
            'minimum_price_volunteered' => $this->validFloor($data) !== null,
        ];

        $weights = [
            'property_type' => 8,
            'city' => 8,
            'location' => 8,
            'area_sqm' => 8,
            'asking_price' => 12,
            'urgency' => 12,
            'urgency_reason' => 8,
            'timeline' => 8,
            'property_identity' => 10,
            'valuation' => 10,
            'minimum_price_volunteered' => 8,
        ];

        $score = 0;
        foreach ($weights as $criterion => $weight) {
            if ($checks[$criterion] ?? false) {
                $score += $weight;
            }
        }

        $missing = collect($checks)
            ->filter(fn (bool $present): bool => ! $present)
            ->keys()
            ->values()
            ->all();

        $urgency = in_array($data['urgency'] ?? null, ['low', 'medium', 'high'], true)
            ? $data['urgency']
            : null;
        $pricingAlignment = $this->pricingAlignment($data, $valuation);
        $flexibilityBand = $this->priceFlexibilityBand($data);
        $protectionRequired = $urgency === 'high'
            || $pricingAlignment === 'materially_below_market_range';

        return [
            'readiness_score' => min(100, $score),
            'status' => match (true) {
                $score >= 85 => 'strong',
                $score >= 65 => 'qualified',
                $score >= 35 => 'building',
                default => 'early',
            },
            'core_ready' => $checks['property_type']
                && $checks['city']
                && $checks['location']
                && $checks['asking_price'],
            'criteria_presence' => $checks,
            'missing_high_value_criteria' => $missing,
            'motivation_level' => $this->motivationLevel(
                urgency: $urgency,
                hasReason: $checks['urgency_reason'],
                hasTimeline: $checks['timeline'],
            ),
            'pricing_alignment' => $pricingAlignment,
            'confidential_price_flexibility' => [
                'minimum_price_present' => $checks['minimum_price_volunteered'],
                'band' => $flexibilityBand,
                'confidential' => true,
            ],
            'seller_protection' => [
                'required' => $protectionRequired,
                'reasons' => array_values(array_filter([
                    $urgency === 'high' ? 'high_explicit_urgency' : null,
                    $pricingAlignment === 'materially_below_market_range'
                        ? 'asking_materially_below_estimated_market_range'
                        : null,
                ])),
            ],
            'recommended_next_question' => $this->recommendedNextQuestion($checks),
            'recommended_negotiation_posture' => $this->negotiationPosture(
                urgency: $urgency,
                pricingAlignment: $pricingAlignment,
                valuationAvailable: $checks['valuation'],
            ),
            'guardrails' => [
                'motivation_is_not_psychological_diagnosis' => true,
                'urgency_must_not_be_used_for_pressure' => true,
                'seller_private_floor_is_confidential' => true,
                'no_fake_buyer_or_offer' => true,
                'valuation_is_not_official_sale_price' => true,
                'follow_up_scheduling_allowed' => false,
            ],
        ];
    }

    private function recommendedNextQuestion(array $checks): ?string
    {
        return match (true) {
            ! $checks['property_type'] => 'Taşınmaz türünü tek kısa soruyla netleştir.',
            ! $checks['city'] || ! $checks['location'] => 'İl ve ilçe/mahalle bilgisini netleştir.',
            ! $checks['area_sqm'] => 'Net veya yaklaşık m² bilgisini sor.',
            ! $checks['asking_price'] => 'Satıcının mevcut fiyat beklentisini sor.',
            ! $checks['urgency'] => 'Satışın ne kadar acil olduğunu doğal biçimde netleştir.',
            ! $checks['timeline'] => 'Satışı hangi zaman aralığında sonuçlandırmak istediğini sor.',
            ! $checks['property_identity'] => 'Konum linki veya varsa ada/parsel bilgisinden birini iste.',
            ! $checks['valuation'] => 'Yeni soru sormadan önce güncel emsal/değerleme çalışmasını tamamla.',
            ! $checks['minimum_price_volunteered'] => 'Doğrudan taban fiyat istemek yerine, mevcut fiyatta yaklaşık pazarlık esnekliği olup olmadığını sor.',
            default => null,
        };
    }

    private function motivationLevel(
        ?string $urgency,
        bool $hasReason,
        bool $hasTimeline,
    ): string {
        return match (true) {
            $urgency === 'high' => 'high_explicit',
            $urgency === 'medium' => 'medium_explicit',
            $urgency === 'low' => 'low_explicit',
            $hasReason || $hasTimeline => 'context_present_unrated',
            default => 'unknown',
        };
    }

    private function negotiationPosture(
        ?string $urgency,
        string $pricingAlignment,
        bool $valuationAvailable,
    ): string {
        return match (true) {
            $urgency === 'high' && ! $valuationAvailable => 'protect_urgent_seller_before_price_anchor',
            $pricingAlignment === 'materially_below_market_range' => 'protect_value_and_verify_expectation',
            $pricingAlignment === 'materially_above_market_range' => 'reframe_expectation_with_comparables',
            $valuationAvailable => 'compare_speed_vs_price_tradeoff',
            default => 'collect_then_value',
        };
    }

    private function pricingAlignment(array $data, array $valuation): string
    {
        $asking = $this->positive($data['asking_price'] ?? null);
        $marketMin = $this->positive($valuation['market_min'] ?? null);
        $marketMax = $this->positive($valuation['market_max'] ?? null);

        if ($asking === null || $marketMin === null || $marketMax === null) {
            return 'unknown';
        }

        if ($marketMin > $marketMax) {
            return 'unknown';
        }

        if ($asking < ($marketMin * 0.88)) {
            return 'materially_below_market_range';
        }

        if ($asking > ($marketMax * 1.12)) {
            return 'materially_above_market_range';
        }

        return 'within_estimated_market_range';
    }

    private function priceFlexibilityBand(array $data): ?string
    {
        $asking = $this->positive($data['asking_price'] ?? null);
        $floor = $this->validFloor($data);

        if ($asking === null || $floor === null) {
            return null;
        }

        $percent = (($asking - $floor) / $asking) * 100;

        return match (true) {
            $percent <= 2 => 'minimal',
            $percent <= 7 => 'limited',
            $percent <= 15 => 'moderate',
            default => 'high',
        };
    }

    private function validFloor(array $data): ?float
    {
        $asking = $this->positive($data['asking_price'] ?? null);
        $floor = $this->positive($data['minimum_price'] ?? null);

        if ($asking === null || $floor === null || $floor > $asking) {
            return null;
        }

        return $floor;
    }

    private function hasUsableValuationNumbers(array $valuation): bool
    {
        $marketMin = $this->positive($valuation['market_min'] ?? null);
        $marketMax = $this->positive($valuation['market_max'] ?? null);

        return $marketMin !== null
            && $marketMax !== null
            && $marketMin <= $marketMax;
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }

    private function filled(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }

        $value = $data[$key];

        if ($value === null) {
            return false;
        }

        return ! is_string($value) || trim($value) !== '';
    }

    private function positive(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return $profile->profile_type === 'seller'
            && $profile->belongsToIsolatedProductionScope();
    }
}
