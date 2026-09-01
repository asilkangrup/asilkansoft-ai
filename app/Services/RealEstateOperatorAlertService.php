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
        $alerts = [];

        $verificationStatus = (string) ($verification['status'] ?? '');
        $riskScore = (int) ($verification['risk_score'] ?? 0);

        if (in_array($verificationStatus, ['blocked', 'high_risk'], true)) {
            $critical = $verificationStatus === 'blocked' || $riskScore >= 85;
            $alerts[] = [
                'alert_key' => 'verification_risk:'.$profile->id,
                'type' => 'verification_risk',
                'severity' => $critical ? 'critical' : 'high',
                'title' => $critical
                    ? 'Kritik emlak doğrulama çelişkisi'
                    : 'Yüksek riskli emlak doğrulaması',
                'message' => (string) ($verification['next_best_action']
                    ?? 'Belge ve müşteri beyanı arasındaki çelişkiyi operatör incelemeli.'),
                'payload' => [
                    'verification_status' => $verificationStatus,
                    'risk_score' => $riskScore,
                    'conflict_fields' => collect($verification['conflicts'] ?? [])
                        ->filter(fn ($conflict): bool => is_array($conflict))
                        ->pluck('field')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'safe_to_match' => (bool) ($verification['safe_to_match'] ?? false),
                ],
            ];
        }

        $leadScore = max((int) ($decision['lead_score'] ?? 0), (int) ($conversation->lead_score ?? 0));
        $temperature = (string) ($decision['lead_temperature'] ?? $conversation->lead_temperature ?? '');
        $stage = (string) ($decision['stage'] ?? '');

        if (
            $leadScore >= 70
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
                ],
            ];
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

        return $alerts;
    }

    private function upsert(
        RealEstateProfile $profile,
        ConversationControl $conversation,
        array $alert
    ): void {
        $existing = RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('alert_key', $alert['alert_key'])
            ->first();

        $openedAt = $existing?->status === 'open'
            ? $existing->opened_at
            : now();

        RealEstateOperatorAlert::updateOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'alert_key' => $alert['alert_key'],
            ],
            [
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
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
        return (int) $profile->user_id === RealEstateIsolationService::USER_ID
            && (int) $profile->ai_bot_id === RealEstateIsolationService::BOT_ID;
    }

    private function supportsConversation(?ConversationControl $conversation): bool
    {
        return $conversation !== null
            && (int) $conversation->user_id === RealEstateIsolationService::USER_ID
            && (int) $conversation->ai_bot_id === RealEstateIsolationService::BOT_ID;
    }
}
