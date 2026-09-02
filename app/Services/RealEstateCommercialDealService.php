<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateCommercialDealService
{
    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if ($profile->profile_type !== 'seller' || ! $profile->belongsToIsolatedProductionScope()) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];
        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
            ? $data['investor_offer_handoff_intelligence'] : [];

        $asking = $this->positive($data['asking_price'] ?? null);
        $realisticMin = $this->positive($valuation['realistic_sale_min'] ?? $valuation['quick_sale_min'] ?? null);
        $realisticMax = $this->positive($valuation['realistic_sale_max'] ?? $valuation['quick_sale_max'] ?? null);
        $investorMin = $this->positive($valuation['investor_buy_min'] ?? null);
        $investorMax = $this->positive($valuation['investor_buy_max'] ?? null);
        $commissionRate = $this->rate(data_get($data, 'commercial_terms.commission_rate_percent'));

        $targetDealAmount = $asking !== null && $investorMax !== null
            ? min($asking, $investorMax)
            : $investorMax;
        $commissionAmount = $commissionRate !== null && $targetDealAmount !== null
            ? (int) round($targetDealAmount * ($commissionRate / 100))
            : null;
        $gapAmount = $asking !== null && $investorMax !== null
            ? max(0, (int) round($asking - $investorMax))
            : null;
        $gapPercent = $gapAmount !== null && $asking !== null && $asking > 0
            ? (int) round(($gapAmount / $asking) * 100)
            : null;

        $state = match (true) {
            $realisticMax === null || $investorMax === null => 'valuation_required',
            ! (bool) ($handoff['ready_for_operator_handoff'] ?? false) => 'file_required',
            $asking === null => 'asking_price_required',
            $asking > $investorMax => 'negotiation_required',
            default => 'investor_ready',
        };

        return [
            'state' => $state,
            'asking_price' => $asking !== null ? (int) round($asking) : null,
            'realistic_sale_min' => $realisticMin !== null ? (int) round($realisticMin) : null,
            'realistic_sale_max' => $realisticMax !== null ? (int) round($realisticMax) : null,
            'negotiation_target_min' => $investorMin !== null ? (int) round($investorMin) : null,
            'negotiation_target_max' => $investorMax !== null ? (int) round($investorMax) : null,
            'gap_to_investor_band_amount' => $gapAmount,
            'gap_to_investor_band_percent' => $gapPercent,
            'expected_deal_amount' => $targetDealAmount !== null ? (int) round($targetDealAmount) : null,
            'commission_rate_percent' => $commissionRate,
            'expected_commission_amount' => $commissionAmount,
            'candidate_count' => max(0, (int) ($handoff['candidate_count'] ?? 0)),
            'recommended_operator_action' => $this->action($state, $investorMin, $investorMax, $gapAmount),
            'guardrails' => [
                'urgency_changes_target_price' => false,
                'target_derived_from_verified_valuation' => true,
                'private_seller_floor_included' => false,
                'commission_is_estimate' => true,
                'human_approval_required' => true,
            ],
        ];
    }

    private function action(string $state, ?float $min, ?float $max, ?int $gap): string
    {
        return match ($state) {
            'valuation_required' => 'Önce güncel değerleme ve yatırımcı alım bandını doğrula.',
            'file_required' => 'Eksik belge, konum veya fotoğrafı tamamla; dosya hazır olmadan yatırımcıya sunma.',
            'asking_price_required' => 'Satıcının mevcut fiyat beklentisini netleştir.',
            'negotiation_required' => 'Satıcıyla doğrulanmış yatırımcı bandını görüş'
                .($min && $max ? ': '.number_format($min, 0, ',', '.').'–'.number_format($max, 0, ',', '.').' TL' : '')
                .($gap ? '. Mevcut beklenti ile üst sınır arasında '.number_format($gap, 0, ',', '.').' TL fark var.' : '.'),
            default => 'Fiyat yatırımcı bandında; uygun gerçek yatırımcılardan teklif toplamaya geç.',
        };
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private function rate(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $rate = (float) $value;

        return $rate > 0 && $rate <= 20 ? round($rate, 2) : null;
    }
}
