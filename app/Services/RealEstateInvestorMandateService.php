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
        $exclusions = app(RealEstateInvestorExclusionMemoryService::class)
            ->summaryForProfile($profile);
        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : $data;
        $summary = $this->build($data, $exclusions);
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

        if ($existing !== []) {
            return $existing;
        }

        $exclusions = app(RealEstateInvestorExclusionMemoryService::class)
            ->summaryForProfile($profile);
        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : $data;

        return $this->build($data, $exclusions);
    }

    private function build(array $data, array $exclusions = []): array
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
        $excludedPropertyTypes = $this->safeStringArray(
            $exclusions['excluded_property_types'] ?? []
        );
        $excludedCities = $this->safeStringArray(
            $exclusions['excluded_cities'] ?? []
        );
        $excludedDistricts = $this->safeStringArray(
            $exclusions['excluded_districts'] ?? []
        );

        return [
            'strength_score' => min(100, $score),
            'status' => match (true) {
                $score >= 85 => 'strong',
                $score >= 65 => 'qualified',
                $score >= 35 => 'building',
                default => 'early',
            },
            'core_ready' => $checks['budget_max']
                && $checks['property_type']
                && ($checks['city'] || ($data['location_flexibility'] ?? null) === 'flexible')
                && $checks['investment_goal'],
            'criteria_presence' => $checks,
            'missing_high_value_criteria' => $missing,
            'recommended_next_question' => $this->recommendedNextQuestion($checks),
            'explicit_exclusions_present' => ($excludedPropertyTypes !== [])
                || ($excludedCities !== [])
                || ($excludedDistricts !== []),
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
                'excluded_property_types' => $excludedPropertyTypes,
                'excluded_cities' => $excludedCities,
                'excluded_districts' => $excludedDistricts,
            ],
            'guardrails' => [
                'unknown_preferences_are_not_hard_rejections' => true,
                'explicit_customer_exclusions_are_hard_rejections' => true,
                're_inclusion_overrides_older_exclusion' => true,
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
            ! $checks['budget_max'] => 'Yaklaşık maksimum yatırım bütçesini netleştir.',
            ! $checks['city'] && ! $checks['location_flexibility'] => 'Belirli bir bölge mi aradığını, yoksa Türkiye genelinde fırsata açık mı olduğunu sor.',
            ! $checks['property_type'] => 'Öncelikli taşınmaz türünü netleştir; birden fazla tür kabul ediyorsa bunu olduğu gibi kaydet.',
            ! $checks['investment_goal'] => 'Yatırım hedefini kısa biçimde netleştir: al-sat, kira getirisi veya değer artışı.',
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

    private function safeStringArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return in_array($profile->profile_type, ['investor', 'buyer'], true)
            && $profile->belongsToIsolatedProductionScope();
    }
}
