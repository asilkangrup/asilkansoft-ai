<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateValuationDecisionGuardService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    private const VALUATION_TAG_PREFIX = 'real_estate:valuation:';

    private const INTEGRITY_TAG_PREFIX = 'real_estate:valuation_integrity:';

    public function process(ConversationControl $conversation): ?array
    {
        if (
            (int) $conversation->user_id !== self::REAL_ESTATE_USER_ID
            || (int) $conversation->organization_id !== RealEstateIsolationService::ORGANIZATION_ID
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
        $integrity = app(RealEstateComparableIntegrityService::class)
            ->assess($profile->fresh());

        $data = is_array($profile->fresh()->data) ? $profile->fresh()->data : [];
        $data['valuation_integrity'] = $integrity;
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : null;

        if (! is_array($decision)) {
            $profile->update(['data' => $data]);
            $this->updateValuationTags($conversation, $freshness, $integrity);

            return null;
        }

        $decision['valuation_freshness_status'] = $freshness['status'] ?? 'missing';
        $decision['valuation_quality'] = $freshness['quality'] ?? 'insufficient';
        $decision['valuation_source_count'] = (int) ($freshness['source_count'] ?? 0);
        $decision['valuation_comparable_count'] = (int) ($freshness['comparable_count'] ?? 0);
        $decision['valuation_research_needed'] = ! ($freshness['usable_for_decision'] ?? false);
        $decision['valuation_expires_at'] = $freshness['expires_at'] ?? null;
        $decision['valuation_integrity_status'] = $integrity['status'] ?? 'blocked';
        $decision['valuation_integrity_quality'] = $integrity['quality'] ?? 'insufficient';
        $decision['valuation_usable_comparable_count'] = (int) ($integrity['usable_comparable_count'] ?? 0);
        $decision['valuation_distinct_source_host_count'] = (int) ($integrity['distinct_source_host_count'] ?? 0);
        $decision['valuation_integrity_reasons'] = array_values($integrity['reason_codes'] ?? []);
        $decision['valuation_integrity_needed'] = ! ($integrity['sufficient_for_decision'] ?? false);

        $freshEnough = (bool) ($freshness['usable_for_decision'] ?? false);
        $integrityEnough = (bool) ($integrity['sufficient_for_decision'] ?? false);

        if (
            $profile->profile_type === 'seller'
            && (! $freshEnough || ! $integrityEnough)
        ) {
            $decision['ready_for_match'] = false;

            if (! $freshEnough) {
                $decision['negotiation_posture'] = 'refresh_valuation';
                $decision['negotiation_strategy'] = 'Eski veya kaynaksız fiyat verisini pazarlık ankrajı yapma. Güncel emsal araştırmasını yenile, ardından gerçekçi satış ile yatırımcı/hızlı nakit alım seviyesini yeniden karşılaştır.';
                $decision['next_best_action'] = 'Güncel emsal araştırmasını yenile; yeni araştırma doğrulanmadan eski değerlemeyi yatırımcı eşleştirmesinde veya fiyat pazarlığında kullanma.';
            } else {
                $decision['negotiation_posture'] = 'repair_comparable_integrity';
                $decision['negotiation_strategy'] = 'Fiyat aralıklarını yalnızca lokasyon, taşınmaz türü, m² ve kaynak URL bütünlüğü yeterli emsallerle destekle. Uç değerleri ve ilgisiz emsalleri pazarlık ankrajı olarak kullanma.';
                $decision['next_best_action'] = 'Emsal setini temizle ve yeniden araştır: aynı lokasyon/tür için fiyat+m²+URL içeren yeterli emsal sağlanmadan yatırımcı eşleştirmesi yapma.';
            }

            $conversation->update([
                'next_best_action' => $decision['next_best_action'],
            ]);
        }

        $data['decision_intelligence'] = $decision;
        $profile->update(['data' => $data]);

        $this->updateValuationTags($conversation, $freshness, $integrity, $decision);

        return $decision;
    }

    private function updateValuationTags(
        ConversationControl $conversation,
        array $freshness,
        array $integrity,
        ?array $decision = null
    ): void {
        $tags = collect($conversation->etiketler())
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::VALUATION_TAG_PREFIX)
                && ! str_starts_with($tag, self::INTEGRITY_TAG_PREFIX)
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
        $integrityStatus = (string) ($integrity['status'] ?? 'blocked');
        $integrityQuality = (string) ($integrity['quality'] ?? 'insufficient');

        $tags->push(self::VALUATION_TAG_PREFIX.$status);
        $tags->push(self::VALUATION_TAG_PREFIX.'quality_'.$quality);
        $tags->push(self::INTEGRITY_TAG_PREFIX.$integrityStatus);
        $tags->push(self::INTEGRITY_TAG_PREFIX.'quality_'.$integrityQuality);

        if (
            ($freshness['usable_for_matching'] ?? false)
            && ($integrity['sufficient_for_matching'] ?? false)
        ) {
            $tags->push(self::VALUATION_TAG_PREFIX.'match_safe');
            $tags->push(self::INTEGRITY_TAG_PREFIX.'match_safe');
        }

        $conversation->update([
            'tags' => $tags->unique()->values()->all(),
        ]);
    }
}
