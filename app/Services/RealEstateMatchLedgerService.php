<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateMatchEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RealEstateMatchLedgerService
{
    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supportsProfile($profile) || ! Schema::hasTable('real_estate_match_events')) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $summary = is_array($data['opportunity_match_summary'] ?? null)
            ? $data['opportunity_match_summary']
            : [];

        // Matching is intentionally multi-stage. Persist only after the final
        // verification/evidence filter has run, never from raw/intermediate matches.
        if (
            ! ($summary['verification_filtered'] ?? false)
            || ! ($summary['evidence_quality_filtered'] ?? false)
        ) {
            return $this->summaryForProfile($profile);
        }

        $matches = is_array($data['opportunity_matches'] ?? null)
            ? $data['opportunity_matches']
            : [];

        $activePairKeys = [];

        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $payload = $this->safeMatchPayload($profile, $match);

            if ($payload === null) {
                continue;
            }

            $activePairKeys[] = $payload['pair_key'];
            $this->recordState($payload, 'active');
        }

        $activePairKeys = array_values(array_unique($activePairKeys));

        foreach ($this->latestEventsForProfile($profile) as $pairKey => $latest) {
            if (
                $latest->status !== 'active'
                || in_array((string) $pairKey, $activePairKeys, true)
            ) {
                continue;
            }

            $this->recordState([
                'pair_key' => (string) $pairKey,
                'seller_profile_id' => (int) $latest->seller_profile_id,
                'investor_profile_id' => (int) $latest->investor_profile_id,
                'seller_conversation_id' => (int) $latest->seller_conversation_id,
                'investor_conversation_id' => (int) $latest->investor_conversation_id,
                'match_score' => $latest->match_score !== null
                    ? (int) $latest->match_score
                    : null,
                'grade' => $latest->grade,
                'estimated_transaction_price' => $latest->estimated_transaction_price !== null
                    ? (float) $latest->estimated_transaction_price
                    : null,
                'reasons' => is_array($latest->reasons) ? $latest->reasons : [],
                'risks' => is_array($latest->risks) ? $latest->risks : [],
                'safety' => [
                    ...(is_array($latest->safety) ? $latest->safety : []),
                    'removal_reason' => 'no_longer_in_final_safe_matches',
                ],
            ], 'removed');
        }

        return $this->summaryForProfile($profile->fresh() ?? $profile);
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supportsProfile($profile) || ! Schema::hasTable('real_estate_match_events')) {
            return [];
        }

        $latest = $this->latestEventsForProfile($profile);
        $active = $latest
            ->filter(fn (RealEstateMatchEvent $event): bool => $event->status === 'active')
            ->sortByDesc(fn (RealEstateMatchEvent $event): int => (int) ($event->match_score ?? 0))
            ->values();

        return [
            'active_count' => $active->count(),
            'strongest_score' => $active->first()?->match_score,
            'strongest_grade' => $active->first()?->grade,
            'latest_change_at' => $latest
                ->sortByDesc(fn (RealEstateMatchEvent $event): int => (int) $event->id)
                ->first()?->occurred_at?->toIso8601String(),
            'final_safe_matches_only' => true,
            'contact_data_stored' => false,
            'seller_private_floor_stored' => false,
        ];
    }

    public function telemetry24h(): array
    {
        if (! Schema::hasTable('real_estate_match_events')) {
            return [
                'events' => 0,
                'activated' => 0,
                'removed' => 0,
                'strong_activated' => 0,
                'current_active_pairs' => 0,
            ];
        }

        $base = RealEstateMatchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subDay());

        $latestByPair = RealEstateMatchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->orderBy('id')
            ->get()
            ->groupBy('pair_key')
            ->map(fn (Collection $events): RealEstateMatchEvent => $events->last());

        return [
            'events' => (clone $base)->count(),
            'activated' => (clone $base)->where('status', 'active')->count(),
            'removed' => (clone $base)->where('status', 'removed')->count(),
            'strong_activated' => (clone $base)
                ->where('status', 'active')
                ->where('grade', 'strong')
                ->count(),
            'current_active_pairs' => $latestByPair
                ->where('status', 'active')
                ->count(),
        ];
    }

    private function safeMatchPayload(
        RealEstateProfile $sourceProfile,
        array $match
    ): ?array {
        $sellerId = is_numeric($match['seller_profile_id'] ?? null)
            ? (int) $match['seller_profile_id']
            : 0;
        $investorId = is_numeric($match['investor_profile_id'] ?? null)
            ? (int) $match['investor_profile_id']
            : 0;

        if ($sellerId <= 0 || $investorId <= 0 || $sellerId === $investorId) {
            return null;
        }

        if (! in_array((int) $sourceProfile->id, [$sellerId, $investorId], true)) {
            return null;
        }

        $seller = $this->scopedProfile($sellerId, ['seller']);
        $investor = $this->scopedProfile($investorId, ['investor', 'buyer']);

        if (! $seller || ! $investor) {
            return null;
        }

        $sellerConversation = $seller->conversation()->first();
        $investorConversation = $investor->conversation()->first();

        if (
            ! app(RealEstateIsolationService::class)->supportsConversation($sellerConversation)
            || ! app(RealEstateIsolationService::class)->supportsConversation($investorConversation)
        ) {
            return null;
        }

        $score = is_numeric($match['match_score'] ?? null)
            ? max(0, min(100, (int) $match['match_score']))
            : null;
        $grade = trim((string) ($match['grade'] ?? ''));

        if (
            $score === null
            || $score < 55
            || ! in_array($grade, ['possible', 'good', 'strong'], true)
        ) {
            return null;
        }

        $freshness = is_array($match['valuation_freshness'] ?? null)
            ? $match['valuation_freshness']
            : [];
        $integrity = is_array($match['comparable_integrity'] ?? null)
            ? $match['comparable_integrity']
            : [];
        $sellerData = is_array($seller->data) ? $seller->data : [];
        $verification = is_array($sellerData['verification_intelligence'] ?? null)
            ? $sellerData['verification_intelligence']
            : [];

        if (
            $freshness === []
            || $integrity === []
            || ! ($verification['safe_to_match'] ?? false)
            || in_array(
                (string) ($verification['status'] ?? 'unverified'),
                ['blocked', 'high_risk', 'unverified'],
                true
            )
            || (int) ($verification['risk_score'] ?? 100) >= 55
            || (int) ($freshness['comparable_count'] ?? 0) < 2
            || (int) ($integrity['usable_comparable_count'] ?? 0) < 2
        ) {
            return null;
        }

        $price = is_numeric($match['estimated_transaction_price'] ?? null)
            ? (float) $match['estimated_transaction_price']
            : null;

        return [
            'pair_key' => hash('sha256', $sellerId.'|'.$investorId),
            'seller_profile_id' => $sellerId,
            'investor_profile_id' => $investorId,
            'seller_conversation_id' => (int) $sellerConversation->id,
            'investor_conversation_id' => (int) $investorConversation->id,
            'match_score' => $score,
            'grade' => $grade,
            'estimated_transaction_price' => $price !== null && $price > 0 ? $price : null,
            'reasons' => $this->safeStrings($match['reasons'] ?? []),
            'risks' => $this->safeStrings($match['risks'] ?? []),
            'safety' => [
                'final_verification_filter_passed' => true,
                'valuation_status' => $this->safeScalar($freshness['status'] ?? null),
                'valuation_quality' => $this->safeScalar($freshness['quality'] ?? null),
                'comparable_count' => (int) ($freshness['comparable_count'] ?? 0),
                'comparable_integrity_status' => $this->safeScalar($integrity['status'] ?? null),
                'usable_comparable_count' => (int) ($integrity['usable_comparable_count'] ?? 0),
                'verification_status' => $this->safeScalar($verification['status'] ?? null),
                'verification_risk_score' => (int) ($verification['risk_score'] ?? 100),
                'price_basis' => 'asking_and_researched_range',
                'official_sale_price_verified' => false,
                'contact_data_stored' => false,
                'seller_private_floor_stored' => false,
            ],
        ];
    }

    private function recordState(array $payload, string $status): void
    {
        if (! in_array($status, ['active', 'removed'], true)) {
            return;
        }

        $fingerprintPayload = [
            'status' => $status,
            'match_score' => $payload['match_score'] ?? null,
            'grade' => $payload['grade'] ?? null,
            'estimated_transaction_price' => $payload['estimated_transaction_price'] ?? null,
            'reasons' => $payload['reasons'] ?? [],
            'risks' => $payload['risks'] ?? [],
            'safety' => $payload['safety'] ?? [],
        ];
        $fingerprint = hash(
            'sha256',
            json_encode(
                $fingerprintPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ) ?: '{}'
        );
        $eventKey = hash('sha256', ($payload['pair_key'] ?? '').'|'.$fingerprint);

        RealEstateMatchEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'seller_profile_id' => $payload['seller_profile_id'],
                'investor_profile_id' => $payload['investor_profile_id'],
                'seller_conversation_id' => $payload['seller_conversation_id'],
                'investor_conversation_id' => $payload['investor_conversation_id'],
                'pair_key' => $payload['pair_key'],
                'status' => $status,
                'match_score' => $payload['match_score'] ?? null,
                'grade' => $payload['grade'] ?? null,
                'estimated_transaction_price' => $payload['estimated_transaction_price'] ?? null,
                'reasons' => $payload['reasons'] ?? [],
                'risks' => $payload['risks'] ?? [],
                'safety' => $payload['safety'] ?? [],
                'occurred_at' => now(),
            ]
        );
    }

    private function latestEventsForProfile(RealEstateProfile $profile): Collection
    {
        return RealEstateMatchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where(function ($query) use ($profile): void {
                $query
                    ->where('seller_profile_id', $profile->id)
                    ->orWhere('investor_profile_id', $profile->id);
            })
            ->orderBy('id')
            ->get()
            ->groupBy('pair_key')
            ->map(fn (Collection $events): RealEstateMatchEvent => $events->last());
    }

    private function scopedProfile(int $id, array $types): ?RealEstateProfile
    {
        return RealEstateProfile::query()
            ->whereKey($id)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->whereIn('profile_type', $types)
            ->first();
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

    private function safeStrings(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => $this->redact((string) $value))
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

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : Str::limit($this->redact($value), 120, '');
    }

    private function redact(string $value): string
    {
        $value = preg_replace(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu',
            '[redacted-email]',
            $value
        ) ?? $value;
        $value = preg_replace(
            '/(?<!\d)\+?\d[\d\s().-]{7,}\d(?!\d)/u',
            '[redacted-phone]',
            $value
        ) ?? $value;

        return trim(Str::limit($value, 180, ''));
    }
}
