<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateOperatorAlert;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RealEstateOperatorAlertService
{
    private const MANAGED_TYPES = [
        'verification_risk',
        'hot_lead',
        'valuation_attention',
        'evidence_attention',
        'seller_protection_attention',
        'investor_offer_handoff',
    ];

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile) || ! Schema::hasTable('real_estate_operator_alerts')) {
            return [];
        }

        $conversation = $profile->conversation()->first();

        if (! $this->supportsConversation($conversation)) {
            return [];
        }

        try {
            $desired = $this->desiredAlerts($profile, $conversation);
            $activeKeys = [];

            foreach ($desired as $alert) {
                $activeKeys[] = $alert['alert_key'];
                $this->upsert($profile, $conversation, $alert);
            }

            RealEstateOperatorAlert::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('real_estate_profile_id', $profile->id)
                ->whereIn('type', self::MANAGED_TYPES)
                ->where('status', 'open')
                ->when(
                    $activeKeys !== [],
                    fn ($query) => $query->whereNotIn('alert_key', $activeKeys)
                )
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);

            return RealEstateOperatorAlert::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('real_estate_profile_id', $profile->id)
                ->where('status', 'open')
                ->orderByRaw("case severity when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end")
                ->get()
                ->map(fn (RealEstateOperatorAlert $alert): array => $this->safePayload($alert))
                ->all();
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE OPERATOR ALERT SYNC FAILED', [
                'user_id' => $profile->user_id,
                'ai_bot_id' => $profile->ai_bot_id,
                'real_estate_profile_id' => $profile->id,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return [];
        }
    }

    public function openCount(): int
    {
        if (! Schema::hasTable('real_estate_operator_alerts')) {
            return 0;
        }

        return RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('status', 'open')
            ->count();
    }

    public function criticalCount(): int
    {
        if (! Schema::hasTable('real_estate_operator_alerts')) {
            return 0;
        }

        return RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('status', 'open')
            ->where('severity', 'critical')
            ->count();
    }

    private function desiredAlerts(
        RealEstateProfile $profile,
        ConversationControl $conversation
    ): array {
        $data = is_array($profile->data) ? $profile->data : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $sellerMotivation = is_array($data['seller_motivation_intelligence'] ?? null)
            ? $data['seller_motivation_intelligence']
            : [];
        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
            ? $data['investor_offer_handoff_intelligence']
            : [];
        $alerts = [];
        $operatorContactEligible = app(RealEstateConversationHandoffService::class)
            ->operatorContactEligible($conversation);

        // Person-risk classification is intentionally disabled. Existing
        // verification_risk alerts remain in MANAGED_TYPES only so the next sync
        // resolves historical records. Document/property consistency checks still
        // protect matching, but customers are never labelled as risky people.

        $leadScore = max((int) ($decision['lead_score'] ?? 0), (int) ($conversation->lead_score ?? 0));
        $temperature = (string) ($decision['lead_temperature'] ?? $conversation->lead_temperature ?? '');
        $stage = (string) ($decision['stage'] ?? '');

        if (
            $operatorContactEligible
            && $leadScore >= 70
            && $temperature === 'hot'
            && ! in_array((string) $conversation->lead_status, ['won', 'lost'], true)
        ) {
            $alerts[] = [
                'alert_key' => 'hot_lead:'.$profile->id,
                'type' => 'hot_lead',
                'severity' => $leadScore >= 85 ? 'high' : 'medium',
                'title' => $profile->profile_type === 'seller'
                    ? 'Sıcak satıcı fırsatı'
                    : 'Sıcak yatırımcı/alıcı fırsatı',
                'message' => (string) ($decision['next_best_action']
                    ?? $conversation->next_best_action
                    ?? 'Operatör bu yüksek niyetli görüşmeyi kontrol etmeli.'),
                'payload' => [
                    'profile_type' => $profile->profile_type,
                    'lead_score' => $leadScore,
                    'lead_temperature' => $temperature,
                    'stage' => $stage,
                    'ready_for_match' => (bool) ($decision['ready_for_match'] ?? false),
                    'operator_contact_eligible' => true,
                    'whatsapp_idle_threshold_minutes' => RealEstateConversationHandoffService::IDLE_MINUTES,
                ],
            ];
        }

        if ($profile->profile_type === 'seller') {
            $protection = is_array($sellerMotivation['seller_protection'] ?? null)
                ? $sellerMotivation['seller_protection']
                : [];

            if ((bool) ($protection['required'] ?? false)) {
                $reasons = collect($protection['reasons'] ?? [])
                    ->filter(fn ($reason): bool => is_string($reason) && $reason !== '')
                    ->unique()
                    ->values()
                    ->all();
                $pricingAlignment = (string) ($sellerMotivation['pricing_alignment'] ?? 'unknown');
                $motivationLevel = (string) ($sellerMotivation['motivation_level'] ?? 'unknown');
                $belowRange = $pricingAlignment === 'materially_below_market_range';

                $alerts[] = [
                    'alert_key' => 'seller_protection_attention:'.$profile->id,
                    'type' => 'seller_protection_attention',
                    'severity' => $belowRange ? 'high' : 'medium',
                    'title' => 'Satıcı değer koruma incelemesi',
                    'message' => $belowRange
                        ? 'Satıcının beklentisi mevcut tahmini piyasa aralığının belirgin altında görünüyor. Aciliyeti fırsata çevirmeden değerleme ve beklentiyi operatör kontrol etsin.'
                        : 'Satıcı açık yüksek aciliyet sinyali verdi. Fiyat baskısı kurmadan değerleme, satış hızı ve beklenti dengesini operatör kontrol etsin.',
                    'payload' => [
                        'motivation_level' => $motivationLevel,
                        'pricing_alignment' => $pricingAlignment,
                        'protection_reasons' => $reasons,
                        'readiness_score' => (int) ($sellerMotivation['readiness_score'] ?? 0),
                        'private_floor_included' => false,
                        'raw_urgency_reason_included' => false,
                    ],
                ];
            }
        }

        if ($profile->profile_type === 'seller' && $leadScore >= 60) {
            $freshness = app(RealEstateValuationFreshnessService::class)->assess($profile);

            if (! (bool) ($freshness['usable_for_matching'] ?? false)) {
                $alerts[] = [
                    'alert_key' => 'valuation_attention:'.$profile->id,
                    'type' => 'valuation_attention',
                    'severity' => (bool) ($decision['ready_for_match'] ?? false) ? 'high' : 'medium',
                    'title' => 'Değerleme yenileme gerekiyor',
                    'message' => 'Nitelikli satıcı için yatırımcı eşleştirmesinden önce güncel ve yeterli emsal araştırmasını tamamla.',
                    'payload' => [
                        'freshness_status' => $freshness['status'] ?? null,
                        'freshness_reasons' => array_values($freshness['reasons'] ?? []),
                        'confidence_score' => (int) ($freshness['confidence_score'] ?? 0),
                        'source_count' => (int) ($freshness['source_count'] ?? 0),
                        'comparable_count' => (int) ($freshness['comparable_count'] ?? 0),
                        'usable_for_matching' => (bool) ($freshness['usable_for_matching'] ?? false),
                    ],
                ];
            }

            $evidenceQuality = app(RealEstateEvidenceQualityService::class)
                ->assess($profile, persist: false);

            if (! (bool) ($evidenceQuality['sufficient_for_matching'] ?? false)) {
                $alerts[] = [
                    'alert_key' => 'evidence_attention:'.$profile->id,
                    'type' => 'evidence_attention',
                    'severity' => (bool) ($decision['ready_for_match'] ?? false) ? 'high' : 'medium',
                    'title' => 'Belge desteği yetersiz',
                    'message' => (string) ($evidenceQuality['next_best_action']
                        ?? 'Nitelikli satıcı için yatırımcı eşleştirmesinden önce yeterli taşınmaz belge desteğini tamamla.'),
                    'payload' => [
                        'evidence_status' => $evidenceQuality['status'] ?? null,
                        'documentary_evidence_count' => (int) (
                            $evidenceQuality['qualified_documentary_evidence_count'] ?? 0
                        ),
                        'identity_signals' => array_values(
                            $evidenceQuality['identity_signals'] ?? []
                        ),
                        'reason_codes' => array_values(
                            $evidenceQuality['reason_codes'] ?? []
                        ),
                        'sufficient_for_matching' => false,
                        'official_verification_complete' => false,
                    ],
                ];
            }
        }

        if (
            $operatorContactEligible
            && $profile->profile_type === 'seller'
            && (bool) ($handoff['ready_for_operator_handoff'] ?? false)
            && (string) ($handoff['status'] ?? '') === 'ready'
        ) {
            $candidateCount = max(0, (int) ($handoff['candidate_count'] ?? 0));
            $strongestGrade = (string) ($handoff['strongest_match_grade'] ?? '');
            $alerts[] = [
                'alert_key' => 'investor_offer_handoff:'.$profile->id,
                'type' => 'investor_offer_handoff',
                'severity' => $strongestGrade === 'strong' ? 'high' : 'medium',
                'title' => 'Yatırımcı teklif toplama dosyası hazır',
                'message' => (string) ($handoff['recommended_operator_action']
                    ?? 'Uygun yatırımcı adaylarını operatör inceleyip gerçek teklif toplama sürecini manuel başlatsın.'),
                'payload' => [
                    'candidate_count' => $candidateCount,
                    'strongest_match_score' => is_numeric($handoff['strongest_match_score'] ?? null)
                        ? (int) $handoff['strongest_match_score']
                        : null,
                    'strongest_match_grade' => in_array($strongestGrade, ['possible', 'good', 'strong'], true)
                        ? $strongestGrade
                        : null,
                    'candidate_refs' => collect($handoff['candidate_refs'] ?? [])
                        ->filter(fn ($candidate): bool => is_array($candidate))
                        ->take(3)
                        ->values()
                        ->all(),
                    'operator_contact_eligible' => true,
                    'whatsapp_idle_threshold_minutes' => RealEstateConversationHandoffService::IDLE_MINUTES,
                    'automatic_investor_outreach_allowed' => false,
                    'automatic_customer_follow_up_allowed' => false,
                    'private_seller_floor_included' => false,
                    'customer_pii_included' => false,
                    'human_review_required_before_investor_contact' => true,
                ],
            ];
        }

        return $alerts;
    }

    private function upsert(
        RealEstateProfile $profile,
        ConversationControl $conversation,
        array $alert
    ): void {
        $existing = RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('alert_key', $alert['alert_key'])
            ->first();

        $openedAt = $existing?->status === 'open'
            ? $existing->opened_at
            : now();

        RealEstateOperatorAlert::updateOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'alert_key' => $alert['alert_key'],
            ],
            [
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile->id,
                'type' => $alert['type'],
                'severity' => $alert['severity'],
                'status' => 'open',
                'title' => $alert['title'],
                'message' => $alert['message'],
                'payload' => $alert['payload'],
                'opened_at' => $openedAt,
                'resolved_at' => null,
            ]
        );
    }

    private function safePayload(RealEstateOperatorAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'title' => $alert->title,
            'message' => $alert->message,
            'conversation_control_id' => $alert->conversation_control_id,
            'real_estate_profile_id' => $alert->real_estate_profile_id,
            'payload' => is_array($alert->payload) ? $alert->payload : [],
            'opened_at' => $alert->opened_at?->toIso8601String(),
        ];
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return $profile->belongsToIsolatedProductionScope();
    }

    private function supportsConversation(?ConversationControl $conversation): bool
    {
        return app(RealEstateIsolationService::class)->supportsConversation($conversation);
    }
}
