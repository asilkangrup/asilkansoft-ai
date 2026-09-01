<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class RealEstateComparableIntegrityService
{
    private const MIN_DECISION_USABLE = 1;

    private const MIN_MATCH_USABLE = 2;

    private const MAX_MATCH_UNIT_SPREAD_RATIO = 2.75;

    private const MAX_DECISION_UNIT_SPREAD_RATIO = 4.0;

    public function assess(
        RealEstateProfile $profile,
        ?array $valuation = null
    ): array {
        if (! $this->supports($profile)) {
            return $this->payload(
                status: 'out_of_scope',
                reasons: ['out_of_scope'],
                total: 0,
                usable: 0,
                distinctHosts: 0,
                medianUnitPrice: null,
                unitSpreadRatio: null,
                outlierCount: 0,
                sufficientForDecision: false,
                sufficientForMatching: false,
                quality: 'insufficient',
            );
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = $valuation ?? (is_array($profile->valuation) ? $profile->valuation : []);
        $comparables = collect(is_array($valuation['comparables'] ?? null) ? $valuation['comparables'] : [])
            ->filter(fn ($item): bool => is_array($item))
            ->values();

        $normalized = $comparables
            ->map(fn (array $item): array => $this->normalizeComparable($item, $data))
            ->values();
        $usable = $normalized
            ->filter(fn (array $item): bool => $item['usable'])
            ->values();
        $unitPrices = $usable
            ->pluck('unit_price_sqm')
            ->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)
            ->map(fn ($value): float => (float) $value)
            ->sort()
            ->values();

        $median = $this->median($unitPrices->all());
        $min = $unitPrices->isNotEmpty() ? (float) $unitPrices->first() : null;
        $max = $unitPrices->isNotEmpty() ? (float) $unitPrices->last() : null;
        $spread = $min !== null && $min > 0 && $max !== null
            ? round($max / $min, 3)
            : null;
        $outlierCount = $median === null
            ? 0
            : $unitPrices
                ->filter(fn (float $price): bool => $price < ($median * 0.5) || $price > ($median * 2.0))
                ->count();

        $reasons = [];

        if ($normalized->count() === 0) {
            $reasons[] = 'missing_comparables';
        }

        if ($usable->count() < self::MIN_DECISION_USABLE) {
            $reasons[] = 'insufficient_usable_comparables';
        }

        if ($normalized->contains(fn (array $item): bool => ! $item['valid_url'])) {
            $reasons[] = 'invalid_comparable_url';
        }

        if ($normalized->contains(fn (array $item): bool => ! $item['has_price_and_area'])) {
            $reasons[] = 'missing_comparable_price_or_area';
        }

        if ($normalized->contains(fn (array $item): bool => ! $item['location_match'])) {
            $reasons[] = 'location_mismatch';
        }

        if ($normalized->contains(fn (array $item): bool => ! $item['property_type_match'])) {
            $reasons[] = 'property_type_mismatch';
        }

        if ($normalized->contains(fn (array $item): bool => $item['observed_too_old'])) {
            $reasons[] = 'old_comparable_observation';
        }

        if ($spread !== null && $spread > self::MAX_MATCH_UNIT_SPREAD_RATIO) {
            $reasons[] = 'wide_unit_price_spread';
        }

        if ($outlierCount > 0) {
            $reasons[] = 'unit_price_outlier';
        }

        if (! $this->priceRangesConsistent($valuation)) {
            $reasons[] = 'inconsistent_price_ranges';
        }

        if ($this->marketRangeDetachedFromComparables($data, $valuation, $median)) {
            $reasons[] = 'market_range_detached_from_comparables';
        }

        $hardDecisionReasons = [
            'missing_comparables',
            'insufficient_usable_comparables',
            'inconsistent_price_ranges',
            'market_range_detached_from_comparables',
        ];
        $hardMatchingReasons = array_merge($hardDecisionReasons, [
            'wide_unit_price_spread',
            'unit_price_outlier',
        ]);

        if ($spread !== null && $spread > self::MAX_DECISION_UNIT_SPREAD_RATIO) {
            $hardDecisionReasons[] = 'wide_unit_price_spread';
        }

        $reasons = array_values(array_unique($reasons));
        $sufficientForDecision = $usable->count() >= self::MIN_DECISION_USABLE
            && collect($hardDecisionReasons)->intersect($reasons)->isEmpty();
        $sufficientForMatching = $usable->count() >= self::MIN_MATCH_USABLE
            && collect($hardMatchingReasons)->intersect($reasons)->isEmpty();

        $distinctHosts = $usable
            ->pluck('host')
            ->filter()
            ->unique()
            ->count();

        $quality = match (true) {
            ! $sufficientForDecision => 'insufficient',
            $sufficientForMatching && $usable->count() >= 3 && $distinctHosts >= 2 => 'high',
            $sufficientForMatching => 'medium',
            default => 'low',
        };

        $status = match (true) {
            ! $sufficientForDecision => 'blocked',
            ! $sufficientForMatching => 'review',
            default => 'safe',
        };

        return $this->payload(
            status: $status,
            reasons: $reasons,
            total: $normalized->count(),
            usable: $usable->count(),
            distinctHosts: $distinctHosts,
            medianUnitPrice: $median,
            unitSpreadRatio: $spread,
            outlierCount: $outlierCount,
            sufficientForDecision: $sufficientForDecision,
            sufficientForMatching: $sufficientForMatching,
            quality: $quality,
        );
    }

    private function normalizeComparable(array $item, array $profileData): array
    {
        $url = trim((string) ($item['url'] ?? ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST)));
        $validUrl = in_array($scheme, ['http', 'https'], true) && $host !== '';
        $price = is_numeric($item['listing_price'] ?? null)
            ? (float) $item['listing_price']
            : null;
        $area = is_numeric($item['area_sqm'] ?? null)
            ? (float) $item['area_sqm']
            : null;
        $hasPriceAndArea = $price !== null && $price > 0 && $area !== null && $area > 0;
        $unitPrice = $hasPriceAndArea ? round($price / $area, 2) : null;
        $locationMatch = $this->locationMatches(
            (string) ($item['location'] ?? ''),
            (string) ($profileData['city'] ?? ''),
            (string) ($profileData['district'] ?? '')
        );
        $propertyTypeMatch = $this->propertyTypeMatches(
            (string) ($item['property_type'] ?? ''),
            (string) ($profileData['property_type'] ?? '')
        );
        $observedTooOld = $this->observedTooOld($item['observed_at'] ?? null);

        return [
            'host' => $host !== '' ? $host : null,
            'valid_url' => $validUrl,
            'has_price_and_area' => $hasPriceAndArea,
            'unit_price_sqm' => $unitPrice,
            'location_match' => $locationMatch,
            'property_type_match' => $propertyTypeMatch,
            'observed_too_old' => $observedTooOld,
            'usable' => $validUrl
                && $hasPriceAndArea
                && $locationMatch
                && $propertyTypeMatch
                && ! $observedTooOld,
        ];
    }

    private function locationMatches(string $location, string $city, string $district): bool
    {
        $location = $this->normalizeText($location);
        $city = $this->normalizeText($city);
        $district = $this->normalizeText($district);

        if ($location === '' || $city === '') {
            return false;
        }

        if (! str_contains($location, $city)) {
            return false;
        }

        return $district === '' || str_contains($location, $district);
    }

    private function propertyTypeMatches(string $comparableType, string $profileType): bool
    {
        $comparableType = $this->normalizeText($comparableType);
        $profileType = $this->normalizeText($profileType);

        return $comparableType !== ''
            && $profileType !== ''
            && $comparableType === $profileType;
    }

    private function observedTooOld(mixed $value): bool
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse((string) $value)->lessThan(CarbonImmutable::now()->subDays(90));
        } catch (Throwable) {
            return false;
        }
    }

    private function priceRangesConsistent(array $valuation): bool
    {
        foreach ([
            ['market_min', 'market_max'],
            ['quick_sale_min', 'quick_sale_max'],
            ['investor_buy_min', 'investor_buy_max'],
        ] as [$minField, $maxField]) {
            $min = $this->positiveNumber($valuation[$minField] ?? null);
            $max = $this->positiveNumber($valuation[$maxField] ?? null);

            if ($min !== null && $max !== null && $min > $max) {
                return false;
            }
        }

        $marketMax = $this->positiveNumber($valuation['market_max'] ?? null);
        $quickMax = $this->positiveNumber($valuation['quick_sale_max'] ?? null);
        $investorMax = $this->positiveNumber($valuation['investor_buy_max'] ?? null);

        if ($marketMax !== null) {
            if ($quickMax !== null && $quickMax > ($marketMax * 1.05)) {
                return false;
            }

            if ($investorMax !== null && $investorMax > ($marketMax * 1.05)) {
                return false;
            }
        }

        return true;
    }

    private function marketRangeDetachedFromComparables(
        array $profileData,
        array $valuation,
        ?float $medianUnitPrice
    ): bool {
        $area = $this->positiveNumber($profileData['area_sqm'] ?? null);
        $marketMin = $this->positiveNumber($valuation['market_min'] ?? null);
        $marketMax = $this->positiveNumber($valuation['market_max'] ?? null);

        if ($area === null || $marketMin === null || $marketMax === null || $medianUnitPrice === null) {
            return false;
        }

        $marketUnitMid = (($marketMin + $marketMax) / 2) / $area;

        return $marketUnitMid < ($medianUnitPrice * 0.4)
            || $marketUnitMid > ($medianUnitPrice * 2.5);
    }

    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $values[$middle]
            : (($values[$middle - 1] + $values[$middle]) / 2);

        return round((float) $median, 2);
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    private function normalizeText(string $value): string
    {
        $value = Str::lower(strtr(trim($value), [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function payload(
        string $status,
        array $reasons,
        int $total,
        int $usable,
        int $distinctHosts,
        ?float $medianUnitPrice,
        ?float $unitSpreadRatio,
        int $outlierCount,
        bool $sufficientForDecision,
        bool $sufficientForMatching,
        string $quality,
    ): array {
        return [
            'status' => $status,
            'reason_codes' => array_values(array_unique($reasons)),
            'total_comparable_count' => $total,
            'usable_comparable_count' => $usable,
            'distinct_source_host_count' => $distinctHosts,
            'median_unit_price_sqm' => $medianUnitPrice,
            'unit_price_spread_ratio' => $unitSpreadRatio,
            'outlier_count' => $outlierCount,
            'quality' => $quality,
            'sufficient_for_decision' => $sufficientForDecision,
            'sufficient_for_matching' => $sufficientForMatching,
            'official_sale_price_verified' => false,
            'price_basis' => 'asking',
        ];
    }

    private function supports(RealEstateProfile $profile): bool
    {
        if (
            (int) $profile->user_id !== RealEstateIsolationService::USER_ID
            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID
        ) {
            return false;
        }

        $conversation = $profile->conversation()->first();

        return $conversation !== null
            && (int) $conversation->user_id === RealEstateIsolationService::USER_ID
            && (int) $conversation->organization_id === RealEstateIsolationService::ORGANIZATION_ID
            && (int) $conversation->ai_bot_id === RealEstateIsolationService::BOT_ID;
    }
}
