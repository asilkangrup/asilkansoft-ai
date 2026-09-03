<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateSellerInvestorHandoffService
{
    private const DATA_KEY = 'investor_offer_handoff_intelligence';

    private const TAG_PREFIX = 'real_estate:handoff:';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $summary = $this->build($profile);
        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) !== $summary) {
            $stored = [
                ...$summary,
                'updated_at' => now()->toIso8601String(),
            ];
            $data[self::DATA_KEY] = $stored;
            $profile->forceFill(['data' => $data])->saveQuietly();
            $summary = $stored;
        } elseif ($existing !== []) {
            $summary = $existing;
        }

        $this->syncConversationState($profile, $summary);

        return $summary;
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        return $this->supports($profile)
            ? $this->sync($profile)
            : [];
    }

    private function build(RealEstateProfile $profile): array
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $packet = app(RealEstateSellerOfferPacketService::class)
            ->summaryForProfile($profile);
        $freshness = app(RealEstateValuationFreshnessService::class)
            ->assess($profile);
        $integrity = app(RealEstateComparableIntegrityService::class)
            ->assess($profile);
        $evidence = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: false);
        $authorization = app(RealEstateAuthorizationService::class)
            ->readiness($profile);
        $factConsistency = app(RealEstateFactConsistencyService::class)
            ->summary($data);
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $matches = collect(
            is_array($data['opportunity_matches'] ?? null)
                ? $data['opportunity_matches']
                : []
        )->filter(fn ($match): bool => is_array($match))->values();

        $verificationStatus = (string) ($verification['status'] ?? 'unverified');
        $verificationSafe = (bool) ($verification['safe_to_match'] ?? false)
            && ! in_array($verificationStatus, ['blocked', 'high_risk', 'unverified'], true)
            && (int) ($verification['risk_score'] ?? 100) < 55;
        $factConsistent = ($factConsistency['status'] ?? 'consistent') !== 'confirmation_required';

        $checks = [
            'offer_packet_ready' => (bool) ($packet['ready_for_investor_offer'] ?? false),
            'fact_consistent' => $factConsistent,
            'verification_safe' => $verificationSafe,
            'evidence_sufficient' => (bool) ($evidence['sufficient_for_matching'] ?? false),
            'valuation_fresh' => (bool) ($freshness['usable_for_matching'] ?? false),
            'comparables_sufficient' => (bool) ($integrity['sufficient_for_matching'] ?? false),
            'eligible_investor_match' => $matches->isNotEmpty(),
            'authorization_ready' => (bool) ($authorization['ready'] ?? false),
        ];

        $status = match (false) {
            $checks['offer_packet_ready'] => 'packet_incomplete',
            $checks['fact_consistent'] => 'confirmation_required',
            $checks['verification_safe'] => 'verification_required',
            $checks['evidence_sufficient'] => 'evidence_required',
            $checks['valuation_fresh'] => 'valuation_required',
            $checks['comparables_sufficient'] => 'comparable_review',
            $checks['eligible_investor_match'] => 'investor_sourcing',
            $checks['authorization_ready'] => 'authorization_required',
            default => 'ready',
        };

        $topCandidates = $matches
            ->take(3)
            ->map(function (array $match): array {
                return [
                    'investor_profile_id' => is_numeric($match['investor_profile_id'] ?? null)
                        ? (int) $match['investor_profile_id']
                        : null,
                    'match_score' => is_numeric($match['match_score'] ?? null)
                        ? max(0, min(100, (int) $match['match_score']))
                        : null,
                    'grade' => in_array(($match['grade'] ?? null), ['possible', 'good', 'strong'], true)
                        ? $match['grade']
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'status' => $status,
            'ready_for_operator_handoff' => $status === 'ready',
            'checks' => $checks,
            'authorization' => [
                'status' => $authorization['status'] ?? 'incomplete',
                'ready' => (bool) ($authorization['ready'] ?? false),
                'blocking_reason_codes' => array_values(
                    $authorization['blocking_reason_codes'] ?? []
                ),
                'seller_commission_percent' => RealEstateAuthorizationService::SELLER_COMMISSION_PERCENT,
                'buyer_commission_percent' => RealEstateAuthorizationService::BUYER_COMMISSION_PERCENT,
            ],
            'candidate_count' => $matches->count(),
            'strongest_match_score' => $topCandidates[0]['match_score'] ?? null,
            'strongest_match_grade' => $topCandidates[0]['grade'] ?? null,
            'candidate_refs' => $topCandidates,
            'recommended_operator_action' => $this->recommendedOperatorAction(
                status: $status,
                packet: $packet,
                freshness: $freshness,
                evidence: $evidence,
                integrity: $integrity,
                authorization: $authorization,
            ),
            'guardrails' => [
                'automatic_investor_outreach_allowed' => false,
                'automatic_customer_follow_up_allowed' => false,
                'fake_offer_or_buyer_allowed' => false,
                'private_seller_floor_included' => false,
                'customer_pii_included' => false,
                'human_review_required_before_investor_contact' => true,
                'authorization_required_before_investor_contact' => true,
                'investor_presentation_export_allowed' => $status === 'ready',
            ],
        ];
    }

    private function recommendedOperatorAction(
        string $status,
        array $packet,
        array $freshness,
        array $evidence,
        array $integrity,
        array $authorization,
    ): string {
        return match ($status) {
            'packet_incomplete' => (string) ($packet['recommended_next_request']
                ?? 'Satıcı dosyasındaki en kritik taşınmaz bilgisini tamamla.'),
            'confirmation_required' => 'Çelişkili taşınmaz kimliği bilgisini müşteriden tek soruyla doğrula; doğrulanana kadar fiyatlama ve yatırımcı eşleştirmesini ilerletme.',
            'verification_required' => 'Belge ve taşınmaz kimliği doğrulamasını güvenli eşleştirme seviyesine getir; doğrulanmamış dosyayı yatırımcıya sunma.',
            'evidence_required' => (string) ($evidence['next_best_action']
                ?? 'Yatırımcıya sunmadan önce yeterli belge/taşınmaz kanıtını tamamla.'),
            'valuation_required' => 'Güncel ve yeterli emsal araştırmasını tamamla; eski veya zayıf değerlemeyle yatırımcı teklifi toplama.',
            'comparable_review' => 'Emsal setini konum, taşınmaz türü, fiyat/m² ve kaynak bütünlüğü açısından güçlendir; ardından eşleştirmeyi yeniden hesapla.',
            'investor_sourcing' => 'Dosya veri ve doğrulama açısından hazır ancak uygun aktif yatırımcı eşleşmesi yok. Mevcut yatırımcı mandatlarını gözden geçir; müşteriye otomatik takip mesajı gönderme.',
            'authorization_required' => (string) ($authorization['recommended_operator_action']
                ?? 'Yetkilendirme ve sunum onayı tamamlanmadan yatırımcı sunumu veya teklif toplama adımına geçme.'),
            'ready' => 'Dosya, geçerli yetkilendirme ve uygun yatırımcı eşleşmeleri hazır. En güçlü adayları operatör inceleyip gerçek yatırımcı teması/teklif toplama sürecini manuel başlatsın; otomatik mesaj veya sahte teklif üretme.',
            default => 'Dosyayı operatör incelemesine al.',
        };
    }

    private function syncConversationState(RealEstateProfile $profile, array $summary): void
    {
        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        $status = trim((string) ($summary['status'] ?? 'packet_incomplete'))
            ?: 'packet_incomplete';
        $tags = collect($conversation->etiketler())
            ->filter(fn ($tag): bool => is_string($tag) && ! str_starts_with($tag, self::TAG_PREFIX))
            ->push(self::TAG_PREFIX.$status)
            ->unique()
            ->values()
            ->all();

        $conversation->forceFill([
            'tags' => $tags,
            'next_follow_up_at' => null,
        ])->saveQuietly();
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        if ($profile->profile_type !== 'seller' || ! $profile->belongsToIsolatedProductionScope()) {
            return false;
        }

        $conversation = $profile->conversation()->first();

        return app(RealEstateIsolationService::class)->supportsConversation($conversation);
    }
}
