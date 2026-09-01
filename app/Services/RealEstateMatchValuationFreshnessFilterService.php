<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateMatchValuationFreshnessFilterService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    private const MATCH_TAG_PREFIX = 'real_estate:match:';

    public function process(ConversationControl $conversation): array
    {
        if (
            (int) $conversation->user_id !== self::REAL_ESTATE_USER_ID
            || (int) $conversation->ai_bot_id !== self::REAL_ESTATE_BOT_ID
        ) {
            return [];
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->first();

        if (! $profile || ! is_array($profile->data)) {
            return [];
        }

        $matches = is_array($profile->data['opportunity_matches'] ?? null)
            ? $profile->data['opportunity_matches']
            : [];

        $freshnessService = app(RealEstateValuationFreshnessService::class);

        $safeMatches = collect($matches)
            ->filter(fn ($match): bool => is_array($match))
            ->map(function (array $match) use ($freshnessService): ?array {
                $sellerProfileId = (int) ($match['seller_profile_id'] ?? 0);

                if ($sellerProfileId <= 0) {
                    return null;
                }

                $seller = RealEstateProfile::query()
                    ->whereKey($sellerProfileId)
                    ->where('user_id', self::REAL_ESTATE_USER_ID)
                    ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                    ->where('profile_type', 'seller')
                    ->first();

                if (! $seller) {
                    return null;
                }

                $freshness = $freshnessService->refreshMetadata($seller);

                if (! ($freshness['usable_for_matching'] ?? false)) {
                    return null;
                }

                $match['valuation_freshness'] = [
                    'status' => $freshness['status'] ?? null,
                    'quality' => $freshness['quality'] ?? null,
                    'source_count' => $freshness['source_count'] ?? 0,
                    'comparable_count' => $freshness['comparable_count'] ?? 0,
                    'researched_at' => $freshness['researched_at'] ?? null,
                    'expires_at' => $freshness['expires_at'] ?? null,
                ];

                return $match;
            })
            ->filter()
            ->sortByDesc('match_score')
            ->values()
            ->all();

        $data = $profile->data;
        $data['opportunity_matches'] = $safeMatches;
        $data['opportunity_match_summary'] = [
            'count' => count($safeMatches),
            'strongest_score' => $safeMatches[0]['match_score'] ?? null,
            'strongest_grade' => $safeMatches[0]['grade'] ?? null,
            'valuation_freshness_enforced' => true,
            'updated_at' => now()->toIso8601String(),
        ];

        $profile->update(['data' => $data]);

        $conversation->update([
            'tags' => $this->matchTags(
                currentTags: $conversation->etiketler(),
                matches: $safeMatches,
            ),
        ]);

        return $safeMatches;
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

        $strongest = (int) ($matches[0]['match_score'] ?? 0);

        $tags->push(
            self::MATCH_TAG_PREFIX.match (true) {
                $strongest >= 85 => 'strong',
                $strongest >= 72 => 'good',
                default => 'possible',
            }
        );

        return $tags->unique()->values()->all();
    }
}
