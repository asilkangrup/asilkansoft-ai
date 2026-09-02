<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

class RealEstateValuationFreshnessService
{
    private const TTL_DAYS = 7;

    private const MIN_DECISION_CONFIDENCE = 45;

    private const MIN_MATCH_CONFIDENCE = 55;

    private const MIN_DECISION_COMPARABLES = 1;

    private const MIN_MATCH_COMPARABLES = 2;

    private const FINGERPRINT_FIELDS = [
        'property_type',
        'city',
        'district',
        'neighborhood',
        'area_sqm',
        'block_no',
        'parcel_no',
        'title_deed_type',
        'zoning_status',
        'is_shared_title',
        'asking_price',
        'location_url',
    ];

    public function fingerprint(array $data): string
    {
        $material = [];

        foreach (self::FINGERPRINT_FIELDS as $field) {
            $material[$field] = $this->canonicalValue($data[$field] ?? null);
        }

        return hash(
            'sha256',
            json_encode(
                $material,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '{}'
        );
    }

    public function stamp(
        RealEstateProfile $profile,
        array $valuation,
        ?CarbonInterface $researchedAt = null
    ): array {
        if (! $this->supports($profile)) {
            return $valuation;
        }

        $researchedAt = $researchedAt
            ? CarbonImmutable::instance($researchedAt)
            : CarbonImmutable::now();

        $valuation['profile_fingerprint'] = $this->fingerprint(
            is_array($profile->data) ? $profile->data : []
        );
        $valuation['researched_at'] = $researchedAt->toIso8601String();
        $valuation['expires_at'] = $researchedAt
            ->addDays(self::TTL_DAYS)
            ->toIso8601String();

        return $this->withAssessmentMetadata(
            valuation: $valuation,
            assessment: $this->assessValuation(
                profile: $profile,
                valuation: $valuation,
                now: $researchedAt,
            ),
        );
    }

    public function refreshMetadata(RealEstateProfile $profile): array
    {
        $assessment = $this->assess($profile);

        if (! $this->supports($profile)) {
            return $assessment;
        }

        $valuation = is_array($profile->valuation) ? $profile->valuation : [];

        if ($valuation === []) {
            return $assessment;
        }

        $updated = $this->withAssessmentMetadata(
            valuation: $valuation,
            assessment: $assessment,
        );

        if ($updated !== $valuation) {
            $profile->update(['valuation' => $updated]);
            $profile->refresh();
        }

        return $assessment;
    }

    public function assess(RealEstateProfile $profile): array
    {
        return $this->assessValuation(
            profile: $profile,
            valuation: is_array($profile->valuation) ? $profile->valuation : [],
            now: CarbonImmutable::now(),
        );
    }

    public function valuationForDecision(RealEstateProfile $profile): array
    {
        $assessment = $this->assess($profile);

        return $assessment['usable_for_decision']
            ? (is_array($profile->valuation) ? $profile->valuation : [])
            : [];
    }

    public function valuationForMatching(RealEstateProfile $profile): array
    {
        $assessment = $this->assess($profile);

        return $assessment['usable_for_matching']
            ? (is_array($profile->valuation) ? $profile->valuation : [])
            : [];
    }

    public function isFreshForDecision(RealEstateProfile $profile): bool
    {
        return (bool) $this->assess($profile)['usable_for_decision'];
    }

    public function isFreshForMatching(RealEstateProfile $profile): bool
    {
        return (bool) $this->assess($profile)['usable_for_matching'];
    }

    private function assessValuation(
        RealEstateProfile $profile,
        array $valuation,
        CarbonInterface $now
    ): array {
        if (! $this->supports($profile)) {
            return $this->assessmentPayload(
                status: 'out_of_scope',
                reasons: ['out_of_scope'],
                currentFingerprint: null,
                researchedAt: null,
                expiresAt: null,
                confidence: 0,
                sourceCount: 0,
                comparableCount: 0,
                usableForDecision: false,
                usableForMatching: false,
                quality: 'insufficient',
            );
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $currentFingerprint = $this->fingerprint($data);
        $hasPricing = $this->hasPricing($valuation);

        if (! $hasPricing) {
            return $this->assessmentPayload(
                status: 'missing',
                reasons: ['missing_price_ranges'],
                currentFingerprint: $currentFingerprint,
                researchedAt: null,
                expiresAt: null,
                confidence: 0,
                sourceCount: 0,
                comparableCount: 0,
                usableForDecision: false,
                usableForMatching: false,
                quality: 'insufficient',
            );
        }

        $reasons = [];
        $storedFingerprint = trim((string) ($valuation['profile_fingerprint'] ?? ''));

        if ($storedFingerprint === '') {
            $reasons[] = 'missing_profile_fingerprint';
        } elseif (! hash_equals($storedFingerprint, $currentFingerprint)) {
            $reasons[] = 'profile_changed';
        }

        $researchedAt = $this->parseDate($valuation['researched_at'] ?? null);
        $expiresAt = $researchedAt?->addDays(self::TTL_DAYS);

        if (! $researchedAt) {
            $reasons[] = 'missing_researched_at';
        } elseif ($expiresAt && $expiresAt->lessThanOrEqualTo($now)) {
            $reasons[] = 'expired';
        }

        $sources = $this->sources($valuation);
        $comparables = $this->comparables($valuation);
        $sourceCount = count($sources);
        $comparableCount = count($comparables);
        $confidence = max(0, min(100, (int) ($valuation['confidence_score'] ?? 0)));

        if ($sourceCount === 0) {
            $reasons[] = 'missing_sources';
        }

        if ($comparableCount < self::MIN_DECISION_COMPARABLES) {
            $reasons[] = 'missing_comparables';
        }

        if ($confidence < self::MIN_DECISION_CONFIDENCE) {
            $reasons[] = 'low_confidence';
        }

        $reasons = array_values(array_unique($reasons));
        $status = $reasons === [] ? 'fresh' : 'stale';
        $usableForDecision = $status === 'fresh';
        $usableForMatching = $usableForDecision
            && $confidence >= self::MIN_MATCH_CONFIDENCE
            && $sourceCount >= 1
            && $comparableCount >= self::MIN_MATCH_COMPARABLES;

        $quality = match (true) {
            ! $usableForDecision => 'insufficient',
            $sourceCount >= 3 && $comparableCount >= 3 && $confidence >= 75 => 'high',
            $sourceCount >= 2 && $comparableCount >= 2 && $confidence >= 60 => 'medium',
            default => 'low',
        };

        return $this->assessmentPayload(
            status: $status,
            reasons: $reasons,
            currentFingerprint: $currentFingerprint,
            researchedAt: $researchedAt,
            expiresAt: $expiresAt,
            confidence: $confidence,
            sourceCount: $sourceCount,
            comparableCount: $comparableCount,
            usableForDecision: $usableForDecision,
            usableForMatching: $usableForMatching,
            quality: $quality,
        );
    }

    private function assessmentPayload(
        string $status,
        array $reasons,
        ?string $currentFingerprint,
        ?CarbonInterface $researchedAt,
        ?CarbonInterface $expiresAt,
        int $confidence,
        int $sourceCount,
        int $comparableCount,
        bool $usableForDecision,
        bool $usableForMatching,
        string $quality,
    ): array {
        return [
            'status' => $status,
            'reasons' => array_values($reasons),
            'profile_fingerprint_current' => $currentFingerprint,
            'researched_at' => $researchedAt?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'confidence_score' => $confidence,
            'source_count' => $sourceCount,
            'comparable_count' => $comparableCount,
            'quality' => $quality,
            'usable_for_decision' => $usableForDecision,
            'usable_for_matching' => $usableForMatching,
            'ttl_days' => self::TTL_DAYS,
        ];
    }

    private function withAssessmentMetadata(
        array $valuation,
        array $assessment
    ): array {
        $valuation['freshness_status'] = $assessment['status'];
        $valuation['freshness_reasons'] = $assessment['reasons'];
        $valuation['profile_fingerprint_current'] = $assessment['profile_fingerprint_current'];
        $valuation['expires_at'] = $assessment['expires_at'];
        $valuation['source_count'] = $assessment['source_count'];
        $valuation['comparable_count'] = $assessment['comparable_count'];
        $valuation['comparable_quality'] = $assessment['quality'];
        $valuation['usable_for_decision'] = $assessment['usable_for_decision'];
        $valuation['usable_for_matching'] = $assessment['usable_for_matching'];

        return $valuation;
    }

    private function sources(array $valuation): array
    {
        $sources = collect(is_array($valuation['sources'] ?? null) ? $valuation['sources'] : [])
            ->filter(fn ($source): bool => is_scalar($source))
            ->map(fn ($source): string => trim((string) $source))
            ->filter();

        foreach ($this->comparables($valuation) as $comparable) {
            $url = trim((string) ($comparable['url'] ?? ''));

            if ($url !== '') {
                $sources->push($url);
            }
        }

        return $sources->unique()->values()->all();
    }

    private function comparables(array $valuation): array
    {
        return collect(is_array($valuation['comparables'] ?? null) ? $valuation['comparables'] : [])
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();
    }

    private function hasPricing(array $valuation): bool
    {
        foreach ([
            'market_min',
            'market_max',
            'realistic_sale_min',
            'realistic_sale_max',
            'quick_sale_min',
            'quick_sale_max',
            'investor_buy_min',
            'investor_buy_max',
        ] as $field) {
            if (is_numeric($valuation[$field] ?? null) && (float) $valuation[$field] > 0) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function canonicalValue(mixed $value): mixed
    {
        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return round((float) $value, 4);
        }

        if (is_scalar($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }

        return null;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return $profile->belongsToIsolatedProductionScope();
    }
}
