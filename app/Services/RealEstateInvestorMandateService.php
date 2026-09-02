<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateInvestorMandateService
{
    private const DATA_KEY = 'investor_mandate_intelligence';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $summary = $this->build($data);
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

        return $existing !== [] ? $existing : $this->build($data);
    }

    private function build(array $data): array
    {
        $checks = [
            'budget_max' => $this->filled($data, 'budget_max'),
            'city' => $this->filled($data, 'city'),
            'property_type' => $this->filled($data, 'property_type'),
            'location' => $this->filled($data, 'district')
                || $this->filled($data, 'neighborhood'),
            'investment_goal' => $this->filled($data, 'investment_goal'),
            'timeline' => $this->filled($data, 'timeline'),
            'financing' => $this->filled($data, 'financing'),
            'risk_preference' => $this->filled($data, 'risk_preference'),
            'area_range' => $this->positive($data['area_min_sqm'] ?? null) !== null
                || $this->positive($data['area_max_sqm'] ?? null) !== null,
            'location_flexibility' => in_array(
                $data['location_flexibility'] ?? null,
                ['strict_district', 'same_city', 'flexible'],
                true
            ),
            'shared_title_preference' => is_bool($data['accepts_shared_title'] ?? null),
            'target_discount' => $this->boundedPercent(
                $data['target_discount_percent'] ?? null
            ) !== null,
        ];

        $weights = [
            'budget_max' => 15,
            'city' => 10,
            'property_type' => 10,
            'location' => 8,
            'investment_goal' => 12,
            'timeline' => 10,
            'financing' => 8,
            'risk_preference' => 6,
            'area_range' => 8,
            'location_flexibility' => 5,
            'shared_title_preference' => 4,
            'target_discount' => 4,
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

        return [
            'strength_score' => min(100, $score),
            'status' => match (true) {
                $score >= 85 => 'strong',
                $score >= 65 => 'qualified',
                $score >= 35 => 'building',
                default => 'early',
            },
            'core_ready' => $checks['budget_max']
                && $checks['city']
                && $checks['property_type'],
            'criteria_presence' => $checks,
            'missing_high_value_criteria' => $missing,
            'recommended_next_question' => $this->recommendedNextQuestion($checks),
            'explicit_match_constraints' => [
                'area_min_sqm' => $this->positive($data['area_min_sqm'] ?? null),
                'area_max_sqm' => $this->positive($data['area_max_sqm'] ?? null),
                'location_flexibility' => $checks['location_flexibility']
                    ? $data['location_flexibility']
                    : null,
                'accepts_shared_title' => $checks['shared_title_preference']
                    ? (bool) $data['accepts_shared_title']
                    : null,
                'target_discount_percent' => $this->boundedPercent(
                    $data['target_discount_percent'] ?? null
                ),
            ],
            'guardrails' => [
                'unknown_preferences_are_not_hard_rejections' => true,
                'criteria_must_come_from_customer_memory' => true,
                'no_binding_offer_inferred' => true,
                'seller_private_floor_exposed' => false,
                'follow_up_scheduling_allowed' => false,
            ],
        ];
    }

    private function recommendedNextQuestion(array $checks): ?string
    {
        return match (true) {
            ! $checks['budget_max'] => 'Gerçekçi maksimum bütçeyi tek kısa soruyla netleştir.',
            ! $checks['city'] => 'Hedef ili netleştir.',
            ! $checks['property_type'] => 'Aradığı taşınmaz türünü netleştir.',
            ! $checks['investment_goal'] => 'Yatırım hedefini: al-sat, kira getirisi veya değer artışı olarak netleştir.',
            ! $checks['timeline'] => 'Alım zamanlamasını netleştir.',
            ! $checks['location'] => 'Hedef ilçe veya mahalleyi netleştir.',
            ! $checks['area_range'] => 'Aradığı yaklaşık m² aralığını tek kısa soruyla netleştir.',
            ! $checks['location_flexibility'] => 'Hedef ilçenin kesin şart mı yoksa aynı il içinde esnek mi olduğunu sor.',
            ! $checks['financing'] => 'Nakit, kredi veya karma finansman durumunu netleştir.',
            ! $checks['risk_preference'] => 'Risk tercihini kısa biçimde netleştir; resmi durumu belirsiz fırsatı varsayma.',
            ! $checks['shared_title_preference'] => 'Hisseli tapulu taşınmazları değerlendirip değerlendirmediğini sor.',
            ! $checks['target_discount'] => 'Varsa fırsat saydığı minimum iskonto eşiğini yüzde olarak netleştir.',
            default => null,
        };
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

    private function boundedPercent(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value >= 0 && $value <= 60 ? $value : null;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return in_array($profile->profile_type, ['investor', 'buyer'], true)
            && $profile->belongsToIsolatedProductionScope();
    }
}
