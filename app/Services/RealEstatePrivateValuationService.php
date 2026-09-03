<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RealEstatePrivateValuationService
{
    public function dataset(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->get()
            ->keyBy('id');

        return $profiles
            ->where('profile_type', 'seller')
            ->flatMap(function (RealEstateProfile $seller) use ($profiles): array {
                $cases = data_get($seller->data, 'transaction_closing_cases', []);

                if (! is_array($cases)) {
                    return [];
                }

                return collect($cases)
                    ->map(function (mixed $case, mixed $investorId) use ($seller, $profiles): ?array {
                        if (! is_array($case) || ! $this->isVerifiedClosing($case, $seller, (int) $investorId)) {
                            return null;
                        }

                        $investor = $profiles->get((int) $investorId);
                        if (! $investor || ! in_array($investor->profile_type, ['investor', 'buyer'], true)) {
                            return null;
                        }

                        $features = $this->features((array) $seller->data);
                        $price = is_numeric($case['agreed_price'] ?? null) ? (int) $case['agreed_price'] : 0;
                        $area = (float) ($features['area_sqm'] ?? 0);

                        if ($price <= 0 || $area <= 0 || blank($features['property_type']) || blank($features['city'])) {
                            return null;
                        }

                        return [
                            'transaction_key' => hash('sha256', implode(':', [
                                RealEstateIsolationService::ORGANIZATION_ID,
                                RealEstateIsolationService::BOT_ID,
                                $seller->id,
                                (int) $investorId,
                            ])),
                            'seller_profile_id' => $seller->id,
                            'investor_profile_id' => (int) $investorId,
                            'property_type' => $features['property_type'],
                            'city' => $features['city'],
                            'district' => $features['district'],
                            'neighborhood' => $features['neighborhood'],
                            'area_sqm' => $area,
                            'zoning_status' => $features['zoning_status'],
                            'title_deed_type' => $features['title_deed_type'],
                            'actual_sale_price' => $price,
                            'actual_unit_price' => round($price / $area, 2),
                            'closed_at' => filled($case['closed_at'] ?? null)
                                ? (string) $case['closed_at']
                                : (string) ($case['updated_at'] ?? $seller->updated_at?->toIso8601String()),
                            'evidence' => [
                                'closing_status' => 'completed',
                                'final_payment_verified' => true,
                                'deed_transfer_completed' => true,
                                'human_verified' => true,
                            ],
                            'automatic_outbound_allowed' => false,
                            'contains_customer_pii' => false,
                            'contains_private_seller_floor' => false,
                            'contains_raw_conversation' => false,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values();
    }

    public function summary(): array
    {
        $records = $this->dataset();
        $unitPrices = $records->pluck('actual_unit_price')->map(fn ($value): float => (float) $value);

        return [
            'verified_transactions' => $records->count(),
            'unique_cities' => $records->pluck('city')->filter()->unique()->count(),
            'unique_districts' => $records
                ->map(fn (array $record): string => ($record['city'] ?? '').'|'.($record['district'] ?? ''))
                ->filter(fn (string $value): bool => ! str_ends_with($value, '|'))
                ->unique()
                ->count(),
            'property_type_count' => $records->pluck('property_type')->filter()->unique()->count(),
            'median_unit_price' => $this->percentile($unitPrices, 0.50),
            'calibration' => $this->calibration($records),
            'minimum_samples_for_price_band' => 3,
            'automatic_outbound_allowed' => false,
            'contains_customer_payload' => false,
            'contains_private_seller_floor' => false,
        ];
    }

    public function estimateFor(RealEstateProfile $profile): array
    {
        if (! $profile->belongsToIsolatedProductionScope() || $profile->profile_type !== 'seller') {
            return [];
        }

        $target = $this->features((array) $profile->data);
        $area = (float) ($target['area_sqm'] ?? 0);

        if ($area <= 0 || blank($target['property_type']) || blank($target['city'])) {
            return $this->emptyEstimate('missing_property_data');
        }

        $comparables = $this->dataset()
            ->reject(fn (array $record): bool => (int) $record['seller_profile_id'] === $profile->id)
            ->filter(fn (array $record): bool => $this->normalize($record['property_type'])
                === $this->normalize($target['property_type']))
            ->map(function (array $record) use ($target, $area): array {
                $score = $this->similarityScore($target, $record, $area);

                return array_merge($record, [
                    'similarity_score' => $score,
                    'location_match' => $this->locationMatch($target, $record),
                ]);
            })
            ->filter(fn (array $record): bool => $record['similarity_score'] >= 45)
            ->sortByDesc('similarity_score')
            ->take(12)
            ->values();

        if ($comparables->isEmpty()) {
            return $this->emptyEstimate('no_similar_verified_sales');
        }

        $clean = $this->removeOutliers($comparables);
        $sampleCount = $clean->count();
        $strongCount = $clean->where('similarity_score', '>=', 70)->count();
        $unitPrices = $clean->pluck('actual_unit_price')->map(fn ($value): float => (float) $value);
        $medianUnit = $this->percentile($unitPrices, 0.50);
        $ready = $sampleCount >= 3;
        $low = $ready ? $this->percentile($unitPrices, 0.25) * $area : null;
        $mid = $ready ? $medianUnit * $area : null;
        $high = $ready ? $this->percentile($unitPrices, 0.75) * $area : null;
        $confidence = $ready
            ? min(92, 38 + ($sampleCount * 6) + ($strongCount * 5))
            : min(35, $sampleCount * 15);

        return [
            'status' => $ready ? 'ready' : 'early_data',
            'status_label' => $ready ? 'Özel veri bandı hazır' : 'Örnek birikiyor',
            'reason_code' => $ready ? null : 'minimum_sample_not_reached',
            'sample_count' => $sampleCount,
            'strong_sample_count' => $strongCount,
            'minimum_sample_count' => 3,
            'median_unit_price' => $medianUnit,
            'suggested_sale_min' => $ready ? (int) round($low) : null,
            'suggested_sale_mid' => $ready ? (int) round($mid) : null,
            'suggested_sale_max' => $ready ? (int) round($high) : null,
            'confidence_score' => $confidence,
            'confidence_label' => match (true) {
                ! $ready => 'Yetersiz örnek',
                $confidence >= 75 => 'Yüksek',
                $confidence >= 55 => 'Orta',
                default => 'Düşük',
            },
            'method' => 'verified_closed_sales_unit_price_percentiles',
            'price_basis' => 'actual_closed_sale',
            'comparables' => $clean->map(fn (array $record): array => [
                'transaction_key' => $record['transaction_key'],
                'property_type' => $record['property_type'],
                'city' => $record['city'],
                'district' => $record['district'],
                'neighborhood' => $record['neighborhood'],
                'area_sqm' => $record['area_sqm'],
                'actual_unit_price' => $record['actual_unit_price'],
                'closed_at' => $record['closed_at'],
                'similarity_score' => $record['similarity_score'],
                'location_match' => $record['location_match'],
                'human_verified' => true,
            ])->all(),
            'guardrails' => [
                'operator_decision_support_only' => true,
                'customer_price_guarantee_allowed' => false,
                'automatic_negotiation_anchor_allowed' => false,
                'automatic_outbound_allowed' => false,
                'minimum_three_samples_for_price_band' => true,
            ],
            'automatic_outbound_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
        ];
    }

    public function candidates(): Collection
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->where('profile_type', 'seller')
            ->latest('id')
            ->get();
    }

    public function safeTransactions(): Collection
    {
        return $this->dataset()->map(fn (array $record): array => collect($record)
            ->except(['seller_profile_id', 'investor_profile_id'])
            ->all());
    }

    private function calibration(Collection $records): array
    {
        $errors = $records->map(function (array $record): ?array {
            $profile = RealEstateProfile::query()
                ->isolatedProduction()
                ->whereKey((int) $record['seller_profile_id'])
                ->first();
            $valuation = is_array($profile?->valuation) ? $profile->valuation : [];
            $min = $valuation['realistic_sale_min'] ?? $valuation['market_min'] ?? null;
            $max = $valuation['realistic_sale_max'] ?? $valuation['market_max'] ?? null;

            if (! is_numeric($min) || ! is_numeric($max) || (float) $min <= 0 || (float) $max <= 0) {
                return null;
            }

            $predicted = (((float) $min) + ((float) $max)) / 2;
            $actual = (float) $record['actual_sale_price'];

            return [
                'absolute_error_percent' => round(abs($predicted - $actual) / $actual * 100, 2),
                'bias_percent' => round(($predicted - $actual) / $actual * 100, 2),
            ];
        })->filter()->values();

        return [
            'sample_count' => $errors->count(),
            'mean_absolute_error_percent' => $errors->isNotEmpty()
                ? round((float) $errors->avg('absolute_error_percent'), 2)
                : null,
            'mean_bias_percent' => $errors->isNotEmpty()
                ? round((float) $errors->avg('bias_percent'), 2)
                : null,
            'contains_transaction_prices' => false,
            'contains_customer_payload' => false,
        ];
    }

    private function isVerifiedClosing(array $case, RealEstateProfile $seller, int $investorId): bool
    {
        return (int) ($case['user_id'] ?? 0) === RealEstateIsolationService::USER_ID
            && (int) ($case['organization_id'] ?? 0) === RealEstateIsolationService::ORGANIZATION_ID
            && (int) ($case['ai_bot_id'] ?? 0) === RealEstateIsolationService::BOT_ID
            && (int) ($case['seller_profile_id'] ?? 0) === $seller->id
            && (int) ($case['investor_profile_id'] ?? 0) === $investorId
            && (string) ($case['status'] ?? '') === 'completed'
            && (bool) ($case['final_payment_verified'] ?? false)
            && (bool) ($case['deed_transfer_completed'] ?? false)
            && is_numeric($case['agreed_price'] ?? null);
    }

    private function features(array $data): array
    {
        return [
            'property_type' => $this->cleanText(
                $data['property_type'] ?? data_get($data, 'property.type')
            ),
            'city' => $this->cleanText($data['city'] ?? null),
            'district' => $this->cleanText($data['district'] ?? null),
            'neighborhood' => $this->cleanText($data['neighborhood'] ?? $data['location'] ?? null),
            'area_sqm' => is_numeric($data['area_sqm'] ?? null) ? (float) $data['area_sqm'] : null,
            'zoning_status' => $this->cleanText($data['zoning_status'] ?? $data['zoning'] ?? null),
            'title_deed_type' => $this->cleanText($data['title_deed_type'] ?? null),
        ];
    }

    private function similarityScore(array $target, array $record, float $targetArea): int
    {
        if ($this->normalize($target['city']) !== $this->normalize($record['city'])) {
            return 0;
        }

        $score = 45;
        if (filled($target['district']) && $this->normalize($target['district']) === $this->normalize($record['district'])) {
            $score += 20;
        }
        if (filled($target['neighborhood']) && $this->normalize($target['neighborhood']) === $this->normalize($record['neighborhood'])) {
            $score += 15;
        }
        if (filled($target['zoning_status']) && $this->normalize($target['zoning_status']) === $this->normalize($record['zoning_status'])) {
            $score += 5;
        }
        if (filled($target['title_deed_type']) && $this->normalize($target['title_deed_type']) === $this->normalize($record['title_deed_type'])) {
            $score += 5;
        }

        $areaRatio = min($targetArea, (float) $record['area_sqm'])
            / max($targetArea, (float) $record['area_sqm']);
        $score += (int) round($areaRatio * 10);

        return min(100, $score);
    }

    private function locationMatch(array $target, array $record): string
    {
        if (filled($target['neighborhood']) && $this->normalize($target['neighborhood']) === $this->normalize($record['neighborhood'])) {
            return 'neighborhood';
        }

        if (filled($target['district']) && $this->normalize($target['district']) === $this->normalize($record['district'])) {
            return 'district';
        }

        return 'city';
    }

    private function removeOutliers(Collection $comparables): Collection
    {
        if ($comparables->count() < 4) {
            return $comparables;
        }

        $median = $this->percentile(
            $comparables->pluck('actual_unit_price')->map(fn ($value): float => (float) $value),
            0.50,
        );

        if (! $median || $median <= 0) {
            return $comparables;
        }

        $filtered = $comparables
            ->filter(fn (array $record): bool => (float) $record['actual_unit_price'] >= $median * 0.50
                && (float) $record['actual_unit_price'] <= $median * 2.00)
            ->values();

        return $filtered->count() >= 3 ? $filtered : $comparables;
    }

    private function percentile(Collection $values, float $percentile): ?float
    {
        $sorted = $values
            ->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)
            ->map(fn ($value): float => (float) $value)
            ->sort()
            ->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $position = ($sorted->count() - 1) * $percentile;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);

        if ($lower === $upper) {
            return round((float) $sorted[$lower], 2);
        }

        $weight = $position - $lower;

        return round(
            ((float) $sorted[$lower] * (1 - $weight)) + ((float) $sorted[$upper] * $weight),
            2,
        );
    }

    private function emptyEstimate(string $reason): array
    {
        return [
            'status' => 'insufficient',
            'status_label' => 'Özel veri henüz yetersiz',
            'reason_code' => $reason,
            'sample_count' => 0,
            'strong_sample_count' => 0,
            'minimum_sample_count' => 3,
            'median_unit_price' => null,
            'suggested_sale_min' => null,
            'suggested_sale_mid' => null,
            'suggested_sale_max' => null,
            'confidence_score' => 0,
            'confidence_label' => 'Yetersiz örnek',
            'method' => 'verified_closed_sales_unit_price_percentiles',
            'price_basis' => 'actual_closed_sale',
            'comparables' => [],
            'guardrails' => [
                'operator_decision_support_only' => true,
                'customer_price_guarantee_allowed' => false,
                'automatic_negotiation_anchor_allowed' => false,
                'automatic_outbound_allowed' => false,
                'minimum_three_samples_for_price_band' => true,
            ],
            'automatic_outbound_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
        ];
    }

    private function cleanText(mixed $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value !== '' ? Str::limit($value, 120, '') : null;
    }

    private function normalize(mixed $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }
}
