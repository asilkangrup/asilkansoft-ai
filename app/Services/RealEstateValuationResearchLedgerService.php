<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use App\Models\RealEstateValuationResearchEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RealEstateValuationResearchLedgerService
{
    public function record(
        RealEstateProfile $profile,
        array $valuation,
        ?string $model,
        bool $forcedRefresh
    ): ?RealEstateValuationResearchEvent {
        if (
            ! $this->supportsProfile($profile)
            || ! Schema::hasTable('real_estate_valuation_research_events')
        ) {
            return null;
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profileFingerprint = trim((string) ($valuation['profile_fingerprint'] ?? ''));

        if ($profileFingerprint === '') {
            return null;
        }

        $integrity = app(RealEstateComparableIntegrityService::class)
            ->assess($profile, $valuation);
        $comparables = is_array($valuation['comparables'] ?? null)
            ? $valuation['comparables']
            : [];
        $sourceHostHashes = $this->sourceHostHashes($valuation, $comparables);
        $comparableFingerprints = $this->comparableFingerprints($comparables);
        $researchedAt = $this->researchedAt($valuation);

        $fingerprintPayload = [
            'profile_fingerprint' => $profileFingerprint,
            'researched_at' => $researchedAt->toIso8601String(),
            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'realistic_sale_min' => $this->positiveNumber($valuation['realistic_sale_min'] ?? null),
            'realistic_sale_max' => $this->positiveNumber($valuation['realistic_sale_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
            'quick_sale_max' => $this->positiveNumber($valuation['quick_sale_max'] ?? null),
            'investor_buy_min' => $this->positiveNumber($valuation['investor_buy_min'] ?? null),
            'investor_buy_max' => $this->positiveNumber($valuation['investor_buy_max'] ?? null),
            'confidence_score' => max(0, min(100, (int) ($valuation['confidence_score'] ?? 0))),
            'source_host_hashes' => $sourceHostHashes,
            'comparable_fingerprints' => $comparableFingerprints,
        ];
        $researchKey = hash(
            'sha256',
            json_encode(
                $fingerprintPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '{}'
        );

        return RealEstateValuationResearchEvent::query()->firstOrCreate(
            ['research_key' => $researchKey],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile->id,
                'profile_fingerprint' => $profileFingerprint,
                'model' => $this->safeModel($model),
                'forced_refresh' => $forcedRefresh,
                'confidence_score' => $fingerprintPayload['confidence_score'],
                'source_count' => count(is_array($valuation['sources'] ?? null) ? $valuation['sources'] : []),
                'comparable_count' => count($comparables),
                'usable_comparable_count' => (int) ($integrity['usable_comparable_count'] ?? 0),
                'distinct_source_host_count' => (int) ($integrity['distinct_source_host_count'] ?? 0),
                'integrity_status' => $this->safeLabel($integrity['status'] ?? null),
                'integrity_quality' => $this->safeLabel($integrity['quality'] ?? null),
                'market_min' => $fingerprintPayload['market_min'],
                'market_max' => $fingerprintPayload['market_max'],
                'realistic_sale_min' => $fingerprintPayload['realistic_sale_min'],
                'realistic_sale_max' => $fingerprintPayload['realistic_sale_max'],
                'quick_sale_min' => $fingerprintPayload['quick_sale_min'],
                'quick_sale_max' => $fingerprintPayload['quick_sale_max'],
                'investor_buy_min' => $fingerprintPayload['investor_buy_min'],
                'investor_buy_max' => $fingerprintPayload['investor_buy_max'],
                'source_host_hashes' => $sourceHostHashes,
                'comparable_fingerprints' => $comparableFingerprints,
                'researched_at' => $researchedAt,
            ]
        );
    }

    public function telemetry24h(): array
    {
        if (! Schema::hasTable('real_estate_valuation_research_events')) {
            return $this->emptyTelemetry();
        }

        $events = RealEstateValuationResearchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('researched_at', '>=', now()->subDay())
            ->get();

        return [
            'events' => $events->count(),
            'forced_refreshes' => $events->where('forced_refresh', true)->count(),
            'match_safe_integrity' => $events
                ->where('integrity_status', 'safe')
                ->filter(fn (RealEstateValuationResearchEvent $event): bool =>
                    $event->usable_comparable_count >= 2
                )->count(),
            'blocked_integrity' => $events->where('integrity_status', 'blocked')->count(),
            'unique_profiles' => $events
                ->pluck('real_estate_profile_id')
                ->filter()
                ->unique()
                ->count(),
            'avg_confidence_score' => $events->isEmpty()
                ? 0
                : (int) round((float) $events->avg('confidence_score')),
        ];
    }

    public function emptyTelemetry(): array
    {
        return [
            'events' => 0,
            'forced_refreshes' => 0,
            'match_safe_integrity' => 0,
            'blocked_integrity' => 0,
            'unique_profiles' => 0,
            'avg_confidence_score' => 0,
        ];
    }

    private function supportsProfile(RealEstateProfile $profile): bool
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

    private function sourceHostHashes(array $valuation, array $comparables): array
    {
        $urls = [];

        foreach (is_array($valuation['sources'] ?? null) ? $valuation['sources'] : [] as $source) {
            if (is_scalar($source)) {
                $urls[] = trim((string) $source);
            }
        }

        foreach ($comparables as $comparable) {
            if (is_array($comparable) && is_scalar($comparable['url'] ?? null)) {
                $urls[] = trim((string) $comparable['url']);
            }
        }

        return collect($urls)
            ->map(function (string $url): ?string {
                $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST)));

                return $host === '' ? null : hash('sha256', $host);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function comparableFingerprints(array $comparables): array
    {
        return collect($comparables)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): string {
                $material = [
                    'url' => is_scalar($item['url'] ?? null)
                        ? trim((string) $item['url'])
                        : null,
                    'listing_price' => $this->positiveNumber($item['listing_price'] ?? null),
                    'area_sqm' => $this->positiveNumber($item['area_sqm'] ?? null),
                    'location' => is_scalar($item['location'] ?? null)
                        ? trim((string) $item['location'])
                        : null,
                    'property_type' => is_scalar($item['property_type'] ?? null)
                        ? trim((string) $item['property_type'])
                        : null,
                    'observed_at' => is_scalar($item['observed_at'] ?? null)
                        ? trim((string) $item['observed_at'])
                        : null,
                ];

                return hash(
                    'sha256',
                    json_encode(
                        $material,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
                    ) ?: '{}'
                );
            })
            ->unique()
            ->sort()
            ->values()
            ->take(12)
            ->all();
    }

    private function researchedAt(array $valuation): CarbonImmutable
    {
        try {
            $value = trim((string) ($valuation['researched_at'] ?? ''));

            return $value === '' ? CarbonImmutable::now() : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function positiveNumber(mixed $value): ?float
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return (float) $value;
    }

    private function safeModel(?string $model): ?string
    {
        $model = trim((string) $model);

        return $model === '' ? null : mb_substr($model, 0, 80);
    }

    private function safeLabel(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 32);
    }
}
