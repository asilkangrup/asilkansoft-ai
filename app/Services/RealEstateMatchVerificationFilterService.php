<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateMatchVerificationFilterService
{
    private const MATCH_TAG_PREFIX = 'real_estate:match:';

    public function process(ConversationControl $conversation): array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile) {
            return [];
        }

        if (in_array($profile->profile_type, ['investor', 'buyer'], true)) {
            app(RealEstateInvestorExclusionMemoryService::class)->sync($profile);
            $profile->refresh();
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $matches = is_array($data['opportunity_matches'] ?? null)
            ? $data['opportunity_matches']
            : [];

        if (! $this->profileFactConsistent($profile)) {
            $matches = [];
        } elseif ($profile->profile_type === 'seller' && ! $this->sellerSafe($profile)) {
            $matches = [];
        } else {
            $matches = collect($matches)
                ->filter(function ($match): bool {
                    if (! is_array($match)) {
                        return false;
                    }

                    $sellerProfileId = $match['seller_profile_id'] ?? null;
                    $investorProfileId = $match['investor_profile_id'] ?? null;

                    if (! is_numeric($sellerProfileId)) {
                        return false;
                    }

                    $seller = RealEstateProfile::query()
                        ->isolatedProduction()
                        ->whereKey((int) $sellerProfileId)
                        ->where('profile_type', 'seller')
                        ->first();

                    if (
                        $seller === null
                        || ! $this->sellerSafe($seller)
                        || ! $this->profileFactConsistent($seller)
                    ) {
                        return false;
                    }

                    // Every scored pair carries the investor profile id. Fail
                    // closed if it is absent or the mandate has an unresolved
                    // customer-side conflict; stale confirmed criteria must not
                    // remain matchable while a new value is awaiting confirmation.
                    if (! is_numeric($investorProfileId)) {
                        return false;
                    }

                    $investor = RealEstateProfile::query()
                        ->isolatedProduction()
                        ->whereKey((int) $investorProfileId)
                        ->whereIn('profile_type', ['investor', 'buyer'])
                        ->first();

                    if (
                        $investor === null
                        || ! $this->profileFactConsistent($investor)
                    ) {
                        return false;
                    }

                    // Explicit negative criteria are derived only from customer
                    // messages in the isolated conversation. They are hard
                    // no-go rules: a broad positive city/type list must never
                    // reintroduce a property the investor explicitly rejected.
                    return ! app(RealEstateInvestorExclusionMemoryService::class)
                        ->blocks($investor, $seller);
                })
                ->values()
                ->all();
        }

        $data = is_array($profile->fresh()?->data) ? $profile->fresh()->data : $data;
        $data['opportunity_matches'] = $matches;
        $data['opportunity_match_summary'] = [
            'count' => count($matches),
            'strongest_score' => $matches[0]['match_score'] ?? null,
            'strongest_grade' => $matches[0]['grade'] ?? null,
            'verification_filtered' => true,
            'evidence_quality_filtered' => true,
            'fact_consistency_filtered' => true,
            'explicit_investor_exclusion_filtered' => true,
            'blocked_by_fact_consistency' => ! $this->profileFactConsistent($profile),
            'unverified_documents_block_candidate_matching' => false,
            'verification_required_before_investor_presentation' => true,
            'updated_at' => now()->toIso8601String(),
        ];

        $profile->update(['data' => $data]);

        $conversation->update([
            'tags' => $this->matchTags(
                currentTags: $conversation->etiketler(),
                matches: $matches,
            ),
        ]);

        return $matches;
    }

    private function profileFactConsistent(RealEstateProfile $profile): bool
    {
        if (! $profile->belongsToIsolatedProductionScope()) {
            return false;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $consistency = app(RealEstateFactConsistencyService::class)
            ->summary($data);

        return ($consistency['status'] ?? 'consistent') !== 'confirmation_required';
    }

    private function sellerSafe(RealEstateProfile $seller): bool
    {
        if (! $seller->belongsToIsolatedProductionScope()) {
            return false;
        }

        $data = is_array($seller->data) ? $seller->data : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $status = (string) ($verification['status'] ?? 'unverified');
        $riskScore = (int) ($verification['risk_score'] ?? 0);

        // Candidate matching is an internal CRM discovery step, not permission
        // to present the property or collect an offer. Missing/unverified
        // documents therefore remain visible as a flagged match. Only an
        // explicit conflict/block removes the pair. Handoff, presentation and
        // closing services still require verification and authorization.
        return ! in_array($status, ['blocked', 'high_risk'], true)
            && $riskScore < 70;
    }

    private function matchTags(array $currentTags, array $matches): array
    {
        $tags = collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::MATCH_TAG_PREFIX)
            )
            ->values();

        if ($matches === []) {
            $tags->push(self::MATCH_TAG_PREFIX.'none');

            return $tags->unique()->values()->all();
        }

        $score = (int) ($matches[0]['match_score'] ?? 0);
        $tags->push(
            self::MATCH_TAG_PREFIX.match (true) {
                $score >= 85 => 'strong',
                $score >= 72 => 'good',
                default => 'possible',
            }
        );

        return $tags->unique()->values()->all();
    }
}
