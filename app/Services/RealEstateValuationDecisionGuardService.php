<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateValuationDecisionGuardService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    private const VALUATION_TAG_PREFIX = 'real_estate:valuation:';

    public function process(ConversationControl $conversation): ?array
    {
        if (
            (int) $conversation->user_id !== self::REAL_ESTATE_USER_ID
            || (int) $conversation->ai_bot_id !== self::REAL_ESTATE_BOT_ID
        ) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->first();

        if (! $profile) {
            return null;
        }

        $freshness = app(RealEstateValuationFreshnessService::class)
            ->refreshMetadata($profile);

        $data = is_array($profile->fresh()->data) ? $profile->fresh()->data : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : null;

        if (! is_array($decision)) {
            $this->updateValuationTags($conversation, $freshness);

            return null;
        }

        $decision['valuation_freshness_status'] = $freshness['status'] ?? 'missing';
        $decision['valuation_quality'] = $freshness['quality'] ?? 'insufficient';
        $decision['valuation_source_count'] = (int) ($freshness['source_count'] ?? 0);
        $decision['valuation_comparable_count'] = (int) ($freshness['comparable_count'] ?? 0);
        $decision['valuation_research_needed'] = ! ($freshness['usable_for_decision'] ?? false);
        $decision['valuation_expires_at'] = $freshness['expires_at'] ?? null;

        if (
            $profile->profile_type === 'seller'
            && ! ($freshness['usable_for_decision'] ?? false)
        ) {
            $decision['ready_for_match'] = false;
            $decision['negotiation_posture'] = 'refresh_valuation';
            $decision['negotiation_strategy'] = 'Eski veya kaynaksız fiyat verisini pazarlık ankrajı yapma. Güncel emsal araştırmasını yenile, ardından piyasa / hızlı satış / yatırımcı alım aralıklarını yeniden karşılaştır.';
            $decision['next_best_action'] = 'Güncel emsal araştırmasını yenile; yeni araştırma doğrulanmadan eski değerlemeyi yatırımcı eşleştirmesinde veya fiyat pazarlığında kullanma.';

            $conversation->update([
                'next_best_action' => $decision['next_best_action'],
            ]);
        }

        $data['decision_intelligence'] = $decision;
        $profile->update(['data' => $data]);

        $this->updateValuationTags($conversation, $freshness, $decision);

        return $decision;
    }

    private function updateValuationTags(
        ConversationControl $conversation,
        array $freshness,
        ?array $decision = null
    ): void {
        $tags = collect($conversation->etiketler())
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::VALUATION_TAG_PREFIX)
            )
            ->filter(function ($tag) use ($decision): bool {
                if (! is_string($tag)) {
                    return false;
                }

                if (
                    $decision !== null
                    && ! ($decision['ready_for_match'] ?? false)
                    && $tag === 'real_estate:state:ready_for_match'
                ) {
                    return false;
                }

                return true;
            })
            ->values();

        $status = (string) ($freshness['status'] ?? 'missing');
        $quality = (string) ($freshness['quality'] ?? 'insufficient');

        $tags->push(self::VALUATION_TAG_PREFIX.$status);
        $tags->push(self::VALUATION_TAG_PREFIX.'quality_'.$quality);

        if ($freshness['usable_for_matching'] ?? false) {
            $tags->push(self::VALUATION_TAG_PREFIX.'match_safe');
        }

        $conversation->update([
            'tags' => $tags->unique()->values()->all(),
        ]);
    }
}
