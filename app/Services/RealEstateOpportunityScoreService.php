<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateOpportunityScoreService
{
    private const DATA_KEY = 'opportunity_score_intelligence';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $summary = $this->build($profile);
        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null) ? $data[self::DATA_KEY] : [];

        if ($this->comparable($existing) === $summary) {
            return $existing;
        }

        $stored = [...$summary, 'updated_at' => now()->toIso8601String()];
        $data[self::DATA_KEY] = $stored;
        $profile->forceFill(['data' => $data])->saveQuietly();

        return $stored;
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null) ? $data[self::DATA_KEY] : [];

        return $existing !== [] ? $existing : $this->build($profile);
    }

    private function build(RealEstateProfile $profile): array
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];
        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
            ? $data['investor_offer_handoff_intelligence'] : [];
        $motivation = is_array($data['seller_motivation_intelligence'] ?? null)
            ? $data['seller_motivation_intelligence'] : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence'] : [];
        $fact = is_array($data['fact_consistency_intelligence'] ?? null)
            ? $data['fact_consistency_intelligence'] : [];

        $asking = $this->positive($data['asking_price'] ?? null);
        $realisticMax = $this->positive($valuation['realistic_sale_max'] ?? $valuation['market_max'] ?? null);
        $investorMax = $this->positive($valuation['investor_buy_max'] ?? null);
        $valuationConfidence = max(0, min(100, (int) ($valuation['confidence_score'] ?? $profile->confidence_score ?? 0)));
        $completeness = max(0, min(100, (int) ($profile->completeness_score ?? 0)));
        $matchScore = max(0, min(100, (int) ($handoff['strongest_match_score'] ?? 0)));
        $candidateCount = max(0, (int) ($handoff['candidate_count'] ?? 0));

        $priceScore = $this->priceScore($asking, $realisticMax, $investorMax);
        $readinessScore = (int) round($completeness * 0.15);
        $valuationScore = $realisticMax !== null ? (int) round($valuationConfidence * 0.10) : 0;
        $matchComponent = $candidateCount > 0 ? (int) round($matchScore * 0.15) : 0;
        $handoffScore = (bool) ($handoff['ready_for_operator_handoff'] ?? false) ? 15 : 0;
        $flexibilityScore = in_array(
            data_get($motivation, 'confidential_price_flexibility.band'),
            ['moderate', 'high'],
            true
        ) ? 5 : 0;

        $factBlocked = ($fact['status'] ?? null) === 'confirmation_required';
        $verificationStatus = (string) ($verification['status'] ?? 'unverified');
        $verificationBlocked = in_array($verificationStatus, ['blocked', 'high_risk'], true)
            || (int) ($verification['risk_score'] ?? 0) >= 70;
        $riskPenalty = ($factBlocked ? 30 : 0) + ($verificationBlocked ? 35 : 0);

        $raw = $priceScore + $readinessScore + $valuationScore + $matchComponent + $handoffScore + $flexibilityScore;
        $score = max(0, min(100, $raw - $riskPenalty));
        $state = match (true) {
            $factBlocked || $verificationBlocked => 'blocked',
            $asking === null || $realisticMax === null || $investorMax === null => 'research_required',
            ! (bool) ($handoff['ready_for_operator_handoff'] ?? false) => 'preparation_required',
            default => 'actionable',
        };
        $grade = match (true) {
            $state === 'blocked' => 'blocked',
            $score >= 80 => 'exceptional',
            $score >= 65 => 'strong',
            $score >= 45 => 'watch',
            default => 'weak',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'state' => $state,
            'components' => [
                'verified_price_advantage' => $priceScore,
                'file_readiness' => $readinessScore,
                'valuation_confidence' => $valuationScore,
                'investor_match' => $matchComponent,
                'operator_handoff' => $handoffScore,
                'price_flexibility_signal' => $flexibilityScore,
                'risk_penalty' => $riskPenalty,
                'urgency_score_contribution' => 0,
            ],
            'metrics' => [
                'asking_to_realistic_discount_percent' => $this->discountPercent($asking, $realisticMax),
                'asking_within_investor_band' => $asking !== null && $investorMax !== null && $asking <= $investorMax,
                'candidate_count' => $candidateCount,
                'strongest_match_score' => $matchScore ?: null,
                'valuation_confidence' => $valuationConfidence,
                'file_completeness' => $completeness,
            ],
            'recommended_operator_action' => $this->action($state, $grade, $handoff),
            'reasons' => array_values(array_filter([
                $priceScore >= 35 ? 'price_is_in_or_near_investor_band' : null,
                $matchComponent >= 10 ? 'strong_investor_match' : null,
                $handoffScore === 15 ? 'file_ready_for_operator_handoff' : null,
                $flexibilityScore > 0 ? 'confidential_flexibility_signal_present' : null,
                $factBlocked ? 'fact_confirmation_required' : null,
                $verificationBlocked ? 'verification_risk_blocks_action' : null,
                $realisticMax === null ? 'valuation_missing' : null,
            ])),
            'guardrails' => [
                'urgency_increases_score' => false,
                'seller_distress_may_be_used_for_pressure' => false,
                'private_seller_floor_included' => false,
                'automatic_investor_outreach_allowed' => false,
                'automatic_customer_follow_up_allowed' => false,
                'human_review_required' => true,
            ],
        ];
    }

    private function priceScore(?float $asking, ?float $realisticMax, ?float $investorMax): int
    {
        if ($asking === null || $realisticMax === null || $investorMax === null) {
            return 0;
        }

        return match (true) {
            $asking <= $investorMax => 40,
            $asking <= $investorMax * 1.10 => 32,
            $asking <= $realisticMax * 0.90 => 25,
            $asking <= $realisticMax => 15,
            default => 3,
        };
    }

    private function discountPercent(?float $asking, ?float $realisticMax): ?int
    {
        if ($asking === null || $realisticMax === null || $realisticMax <= 0) {
            return null;
        }

        return (int) round((($realisticMax - $asking) / $realisticMax) * 100);
    }

    private function action(string $state, string $grade, array $handoff): string
    {
        return match (true) {
            $state === 'blocked' => 'Belge veya bilgi tutarsızlığını çöz; bu dosyayı yatırımcıya sunma.',
            $state === 'research_required' => 'Eksik taşınmaz bilgisi ve güncel değerlemeyi tamamla.',
            $state === 'preparation_required' => (string) ($handoff['recommended_operator_action']
                ?? 'Dosyayı yatırımcı sunumuna hazırla.'),
            $grade === 'exceptional' => 'Öncelikli fırsat: en güçlü uygun yatırımcıyı bugün ara.',
            $grade === 'strong' => 'Dosyayı öncelikli sıraya al ve uygun yatırımcılardan teklif topla.',
            $grade === 'watch' => 'Fiyat avantajını ve yatırımcı ilgisini netleştir.',
            default => 'Şimdilik düşük öncelikte tut; fiyat veya dosya kalitesi iyileşmeden yatırımcıyı arama.',
        };
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return $profile->profile_type === 'seller' && $profile->belongsToIsolatedProductionScope();
    }
}
