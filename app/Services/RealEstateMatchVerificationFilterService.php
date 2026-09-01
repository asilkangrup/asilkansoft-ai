<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateMatchVerificationFilterService
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

        if (! $profile) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $matches = is_array($data['opportunity_matches'] ?? null)
            ? $data['opportunity_matches']
            : [];

        if ($profile->profile_type === 'seller' && ! $this->sellerSafe($profile)) {
            $matches = [];
        } else {
            $matches = collect($matches)
                ->filter(function ($match): bool {
                    if (! is_array($match)) {
                        return false;
                    }

                    $sellerProfileId = $match['seller_profile_id'] ?? null;

                    if (! is_numeric($sellerProfileId)) {
                        return false;
                    }

                    $seller = RealEstateProfile::query()
                        ->whereKey((int) $sellerProfileId)
                        ->where('user_id', self::REAL_ESTATE_USER_ID)
                        ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
                        ->where('profile_type', 'seller')
                        ->first();

                    return $seller !== null && $this->sellerSafe($seller);
                })
                ->values()
                ->all();
        }

        $data['opportunity_matches'] = $matches;
        $data['opportunity_match_summary'] = [
            'count' => count($matches),
            'strongest_score' => $matches[0]['match_score'] ?? null,
            'strongest_grade' => $matches[0]['grade'] ?? null,
            'verification_filtered' => true,
            'evidence_quality_filtered' => true,
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

    private function sellerSafe(RealEstateProfile $seller): bool
    {
        $data = is_array($seller->data) ? $seller->data : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $evidenceQuality = app(RealEstateEvidenceQualityService::class)
            ->assess($seller, persist: false);

        return (bool) ($verification['safe_to_match'] ?? false)
            && ! in_array(
                (string) ($verification['status'] ?? 'unverified'),
                ['blocked', 'high_risk', 'unverified'],
                true
            )
            && (int) ($verification['risk_score'] ?? 100) < 55
            && (bool) ($evidenceQuality['sufficient_for_matching'] ?? false);
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
