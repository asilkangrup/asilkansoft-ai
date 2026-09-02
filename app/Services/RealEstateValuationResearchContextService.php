<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateValuationResearchContextService
{
    private const PROPERTY_FIELDS = [
        'property_type',
        'city',
        'district',
        'neighborhood',
        'area_sqm',
        'title_deed_type',
        'zoning_status',
        'is_shared_title',
        'asking_price',
        'location_url',
        'listing_url',
    ];

    public function build(
        RealEstateProfile $profile,
        bool $forceRefresh,
        array $freshness
    ): ?array {
        if (! $this->supports($profile)) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $factConsistency = is_array($data['fact_consistency_intelligence'] ?? null)
            ? $data['fact_consistency_intelligence']
            : [];

        if (
            ($factConsistency['status'] ?? null) === 'confirmation_required'
            || (int) ($factConsistency['pending_count'] ?? 0) > 0
        ) {
            return null;
        }

        $property = [];

        foreach (self::PROPERTY_FIELDS as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $property[$field] = $value;
        }

        return [
            'property_profile' => $property,
            'research_request' => [
                'valuation_requested' => true,
                'force_refresh' => $forceRefresh,
                'price_basis_required' => 'asking_listings_not_official_sales',
            ],
            'previous_research' => [
                'status' => $this->safeScalar($freshness['status'] ?? null),
                'quality' => $this->safeScalar($freshness['quality'] ?? null),
                'researched_at' => $this->safeScalar($freshness['researched_at'] ?? null),
                'expires_at' => $this->safeScalar($freshness['expires_at'] ?? null),
                'reason_codes' => $this->safeStrings($freshness['reasons'] ?? []),
            ],
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

        return app(RealEstateIsolationService::class)
            ->supportsConversation($profile->conversation()->first());
    }

    private function safeStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function safeScalar(mixed $value): string|int|float|bool|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
