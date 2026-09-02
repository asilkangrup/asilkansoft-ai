<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class RealEstateValuationResearchOutputGuardService
{
    private const ALLOWED_MISSING_FIELDS = [
        'property_type',
        'city',
        'district',
        'neighborhood',
        'area_sqm',
        'asking_price',
        'title_deed_type',
        'zoning_status',
        'block_no',
        'parcel_no',
        'is_shared_title',
        'location_url',
        'road_access',
        'frontage',
        'infrastructure',
    ];

    private const PASSTHROUGH_META_FIELDS = [
        'profile_fingerprint',
        'researched_at',
        'expires_at',
        'freshness_status',
        'profile_fingerprint_current',
        'source_count',
        'comparable_count',
        'comparable_quality',
        'usable_for_decision',
        'usable_for_matching',
    ];

    public function sanitize(RealEstateProfile $profile): ?array
    {
        if (! $this->supports($profile)) {
            return null;
        }

        $valuation = is_array($profile->valuation) ? $profile->valuation : [];

        if ($valuation === []) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $sanitized = $this->buildSanitizedValuation($valuation, $data);

        if ($sanitized !== $valuation) {
            $profile->forceFill(['valuation' => $sanitized])->saveQuietly();
            $profile->refresh();
        }

        return $sanitized;
    }

    public function buildSanitizedValuation(array $valuation, array $profileData): array
    {
        $sanitized = [
            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'realistic_sale_min' => $this->positiveNumber($valuation['realistic_sale_min'] ?? null),
            'realistic_sale_max' => $this->positiveNumber($valuation['realistic_sale_max'] ?? null),
            'research_realistic_sale_min' => $this->positiveNumber($valuation['research_realistic_sale_min'] ?? null),
            'research_realistic_sale_max' => $this->positiveNumber($valuation['research_realistic_sale_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
            'quick_sale_max' => $this->positiveNumber($valuation['quick_sale_max'] ?? null),
            'investor_buy_min' => $this->positiveNumber($valuation['investor_buy_min'] ?? null),
            'investor_buy_max' => $this->positiveNumber($valuation['investor_buy_max'] ?? null),
            'confidence_score' => max(0, min(100, (int) ($valuation['confidence_score'] ?? 0))),
            'market_gap_percent' => $this->boundedNumber(
                $valuation['market_gap_percent'] ?? null,
                -100,
                1000,
            ),
            // Web-derived prose is never promoted into the trusted CRM prompt.
            // Deterministic decision/NBA services own conversational actions.
            'summary' => null,
            'next_best_action' => null,
            'missing_data' => $this->missingFields($valuation['missing_data'] ?? []),
        ];

        $comparables = $this->comparables(
            $valuation['comparables'] ?? [],
            $profileData,
        );
        $sources = $this->sources($valuation['sources'] ?? [], $comparables);

        $sanitized['sources'] = $sources;
        $sanitized['comparables'] = $comparables;
        $sanitized['comparable_stats'] = $this->comparableStats($comparables);
        $sanitized['research_basis'] = $sources === []
            ? 'no_verified_sources'
            : 'web_search';

        foreach (self::PASSTHROUGH_META_FIELDS as $field) {
            if (! array_key_exists($field, $valuation)) {
                continue;
            }

            $sanitized[$field] = $this->safeMetaValue($field, $valuation[$field]);
        }

        $sanitized['freshness_reasons'] = $this->reasonCodes(
            $valuation['freshness_reasons'] ?? []
        );
        $sanitized['research_output_guard'] = [
            'version' => 2,
            'web_prose_removed' => true,
            'source_urls_tokenized' => true,
            'comparable_labels_rebuilt_deterministically' => true,
            'integrity_semantics_preserved' => true,
        ];

        return $sanitized;
    }

    private function comparables(mixed $value, array $profileData): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach (array_slice($value, 0, 8) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->canonicalSourceUrl($item['url'] ?? null);
            $price = $this->positiveNumber($item['listing_price'] ?? null);
            $area = $this->positiveNumber($item['area_sqm'] ?? null);
            $unitPrice = $price !== null && $area !== null
                ? round($price / $area, 2)
                : null;
            $host = $url !== null
                ? strtolower((string) parse_url($url, PHP_URL_HOST))
                : null;

            if ($url === null && $price === null && $area === null) {
                continue;
            }

            $result[] = [
                'source' => $host !== '' ? $host : null,
                'url' => $url,
                'listing_price' => $price,
                'area_sqm' => $area,
                'unit_price_sqm' => $unitPrice,
                // Keep match/mismatch semantics without retaining arbitrary
                // web prose that could become trusted internal instructions.
                'location' => $this->comparableLocation(
                    $item['location'] ?? null,
                    $profileData,
                ),
                'property_type' => $this->comparablePropertyType(
                    $item['property_type'] ?? null,
                    $profileData,
                ),
                'observed_at' => $this->safeDate($item['observed_at'] ?? null),
                'retrieved_at' => $this->safeDateTime($item['retrieved_at'] ?? null),
                'price_basis' => 'asking',
            ];
        }

        return collect($result)
            ->unique(fn (array $item): string =>
                (string) ($item['url'] ?? '')
                .'|'.(string) ($item['listing_price'] ?? '')
                .'|'.(string) ($item['area_sqm'] ?? '')
            )
            ->values()
            ->all();
    }

    private function sources(mixed $value, array $comparables): array
    {
        $sources = [];

        if (is_array($value)) {
            foreach (array_slice($value, 0, 12) as $source) {
                $canonical = $this->canonicalSourceUrl($source);

                if ($canonical !== null) {
                    $sources[] = $canonical;
                }
            }
        }

        foreach ($comparables as $comparable) {
            $url = $comparable['url'] ?? null;

            if (is_string($url) && $url !== '') {
                $sources[] = $url;
            }
        }

        return array_values(array_unique($sources));
    }

    private function canonicalSourceUrl(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $url = trim((string) $value);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST)));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (! preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        // Already-redacted values are stable across subsequent observer runs.
        if (
            preg_match('#^/r/[a-f0-9]{24}$#', $path)
            && ($query === null || $query === '')
            && ($fragment === null || $fragment === '')
        ) {
            return $scheme.'://'.$host.$path;
        }

        // Preserve distinct-listing/source cardinality for freshness and
        // comparable integrity while removing attacker-controlled path/query
        // text from the value promoted into trusted internal context.
        $token = substr(hash('sha256', $url), 0, 24);

        return $scheme.'://'.$host.'/r/'.$token;
    }

    private function comparableLocation(mixed $value, array $profileData): ?string
    {
        $expected = $this->profileLocation($profileData);

        if ($expected === null || ! is_scalar($value)) {
            return null;
        }

        $actual = $this->normalizeText((string) $value);
        $city = $this->normalizeText((string) ($profileData['city'] ?? ''));
        $district = $this->normalizeText((string) ($profileData['district'] ?? ''));

        if ($actual === '' || $city === '' || ! str_contains($actual, $city)) {
            return 'mismatch';
        }

        if ($district !== '' && ! str_contains($actual, $district)) {
            return 'mismatch';
        }

        return $expected;
    }

    private function comparablePropertyType(mixed $value, array $profileData): ?string
    {
        $expected = $this->safeProfileLabel($profileData['property_type'] ?? null, 48);

        if ($expected === null || ! is_scalar($value)) {
            return null;
        }

        return $this->normalizeText((string) $value) === $this->normalizeText($expected)
            ? $expected
            : 'mismatch';
    }

    private function profileLocation(array $profileData): ?string
    {
        $parts = [];

        foreach (['city', 'district', 'neighborhood'] as $field) {
            $part = $this->safeProfileLabel($profileData[$field] ?? null, 64);

            if ($part !== null) {
                $parts[] = $part;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function safeProfileLabel(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
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

    private function missingFields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_scalar($item))
            ->map(fn ($item): string => strtolower(trim((string) $item)))
            ->filter(fn (string $field): bool => in_array($field, self::ALLOWED_MISSING_FIELDS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function reasonCodes(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_scalar($item))
            ->map(fn ($item): string => strtolower(trim((string) $item)))
            ->filter(fn (string $code): bool => (bool) preg_match('/^[a-z0-9_]{1,64}$/', $code))
            ->unique()
            ->take(16)
            ->values()
            ->all();
    }

    private function comparableStats(array $comparables): array
    {
        $unitPrices = collect($comparables)
            ->pluck('unit_price_sqm')
            ->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)
            ->map(fn ($value): float => (float) $value)
            ->sort()
            ->values();

        if ($unitPrices->isEmpty()) {
            return [
                'count' => count($comparables),
                'priced_per_sqm_count' => 0,
                'unit_price_min' => null,
                'unit_price_median' => null,
                'unit_price_max' => null,
                'price_basis' => 'asking',
            ];
        }

        $count = $unitPrices->count();
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $unitPrices[$middle]
            : (($unitPrices[$middle - 1] + $unitPrices[$middle]) / 2);

        return [
            'count' => count($comparables),
            'priced_per_sqm_count' => $count,
            'unit_price_min' => round((float) $unitPrices->first(), 2),
            'unit_price_median' => round((float) $median, 2),
            'unit_price_max' => round((float) $unitPrices->last(), 2),
            'price_basis' => 'asking',
        ];
    }

    private function safeMetaValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'source_count', 'comparable_count' => max(0, (int) $value),
            'usable_for_decision', 'usable_for_matching' => (bool) $value,
            'profile_fingerprint', 'profile_fingerprint_current' => $this->safeHash($value),
            'researched_at', 'expires_at' => $this->safeDateTime($value),
            'freshness_status' => $this->safeCode($value, ['fresh', 'stale', 'missing', 'out_of_scope']),
            'comparable_quality' => $this->safeCode($value, ['high', 'medium', 'low', 'insufficient']),
            default => null,
        };
    }

    private function safeHash(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $hash = strtolower(trim((string) $value));

        return preg_match('/^[a-f0-9]{64}$/', $hash) ? $hash : null;
    }

    private function safeCode(mixed $value, array $allowed): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $code = strtolower(trim((string) $value));

        return in_array($code, $allowed, true) ? $code : null;
    }

    private function safeDate(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $date = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function safeDateTime(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    private function boundedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return null;
        }

        $number = (float) $value;

        return max($min, min($max, $number));
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
