<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;

class RealEstateAuthorizationNextBestActionService
{
    private const DATA_KEY = 'next_best_action_intelligence';

    public function sync(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->where('profile_type', 'seller')
            ->first();

        if (! $profile || ! $profile->belongsToIsolatedProductionScope()) {
            return null;
        }

        $handoff = app(RealEstateSellerInvestorHandoffService::class)
            ->sync($profile);

        if ((string) ($handoff['status'] ?? '') !== 'authorization_required') {
            return null;
        }

        $authorization = app(RealEstateAuthorizationService::class)
            ->readiness($profile->fresh());

        if ((bool) ($authorization['ready'] ?? false)) {
            return null;
        }

        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];
        $reasonCodes = collect($authorization['blocking_reason_codes'] ?? [])
            ->filter(fn ($reason): bool => is_string($reason) && trim($reason) !== '')
            ->map(fn (string $reason): string => 'authorization_'.$this->code($reason))
            ->prepend('seller_authorization_required')
            ->unique()
            ->values()
            ->all();

        $plan = [
            'action_code' => 'complete_seller_authorization',
            'priority' => 'high',
            'action_text' => (string) ($authorization['recommended_operator_action']
                ?? 'Operatör yetkilendirme ve sunum onayı kontrolünü tamamlamadan yatırımcı sunumu veya teklif toplama adımına geçme.'),
            'single_question' => null,
            'blocking' => true,
            'reason_codes' => $reasonCodes,
            'match_count' => max(0, (int) ($handoff['candidate_count'] ?? 0)),
            'stage' => trim((string) ($decision['stage'] ?? 'qualified')) ?: 'qualified',
            'deterministic' => true,
            'guardrails' => [
                'one_primary_action_per_turn' => true,
                'follow_up_scheduling_allowed' => false,
                'automatic_investor_outreach_allowed' => false,
                'investor_presentation_export_allowed' => false,
                'binding_offer_claims_allowed' => false,
                'seller_private_floor_may_be_disclosed_to_investor' => false,
                'authorization_required_before_investor_contact' => true,
            ],
            'updated_at' => now()->toIso8601String(),
        ];

        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) !== $this->comparable($plan)) {
            $data[self::DATA_KEY] = $plan;
            $profile->forceFill(['data' => $data])->saveQuietly();
        } else {
            $plan = $existing;
        }

        $conversation->forceFill([
            'next_best_action' => $plan['action_text'],
            'next_follow_up_at' => null,
        ])->saveQuietly();

        $this->recordState($profile, $conversation, $plan);

        return $plan;
    }

    private function recordState(
        RealEstateProfile $profile,
        ConversationControl $conversation,
        array $plan,
    ): void {
        if (! Schema::hasTable('real_estate_next_best_action_events')) {
            return;
        }

        $state = [
            'profile_id' => (int) $profile->id,
            'action_code' => (string) ($plan['action_code'] ?? ''),
            'priority' => (string) ($plan['priority'] ?? ''),
            'stage' => (string) ($plan['stage'] ?? ''),
            'reason_codes' => array_values($plan['reason_codes'] ?? []),
            'match_count' => (int) ($plan['match_count'] ?? 0),
            'blocking' => (bool) ($plan['blocking'] ?? false),
        ];
        $stateKey = hash(
            'sha256',
            (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        RealEstateNextBestActionEvent::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'state_key' => $stateKey,
            ],
            [
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile->id,
                'action_code' => $state['action_code'],
                'priority' => $state['priority'],
                'stage' => $state['stage'],
                'reason_codes' => $state['reason_codes'],
                'metadata' => [
                    'profile_type' => 'seller',
                    'blocking' => true,
                    'match_count' => $state['match_count'],
                    'deterministic' => true,
                    'authorization_gate' => true,
                    'contains_raw_customer_message' => false,
                    'contains_contact_details' => false,
                    'contains_private_seller_floor' => false,
                    'follow_up_scheduling_allowed' => false,
                ],
                'occurred_at' => now(),
            ]
        );
    }

    private function comparable(array $value): array
    {
        unset($value['updated_at']);

        return $value;
    }

    private function code(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_]+/u', '_', $value) ?? '';

        return trim($value, '_') ?: 'required';
    }
}
