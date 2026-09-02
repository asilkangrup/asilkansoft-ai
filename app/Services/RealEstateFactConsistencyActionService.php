<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;

class RealEstateFactConsistencyActionService
{
    private const NEXT_ACTION_KEY = 'next_best_action_intelligence';

    public function sync(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || $profile->profile_type !== 'seller') {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $consistency = app(RealEstateFactConsistencyService::class)
            ->summary($data);

        if (($consistency['status'] ?? null) !== 'confirmation_required') {
            return null;
        }

        $question = trim((string) ($consistency['confirmation_question'] ?? ''));
        $field = trim((string) ($consistency['highest_priority_field'] ?? ''));

        if ($question === '' || $field === '') {
            return null;
        }

        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];
        $matches = is_array($data['opportunity_matches'] ?? null)
            ? $data['opportunity_matches']
            : [];
        $plan = [
            'action_code' => 'confirm_property_fact_conflict',
            'priority' => 'high',
            'action_text' => mb_substr($question, 0, 500),
            'single_question' => mb_substr($question, 0, 320),
            'blocking' => true,
            'reason_codes' => [
                'customer_property_fact_conflict',
                'conflict_'.$this->code($field),
            ],
            'match_count' => count($matches),
            'stage' => trim((string) ($decision['stage'] ?? 'discovery')) ?: 'discovery',
            'deterministic' => true,
            'guardrails' => [
                'one_primary_action_per_turn' => true,
                'follow_up_scheduling_allowed' => false,
                'unconfirmed_property_identity_may_be_used_for_valuation' => false,
                'unconfirmed_property_identity_may_be_used_for_matching' => false,
                'binding_offer_claims_allowed' => false,
                'seller_private_floor_may_be_disclosed_to_investor' => false,
            ],
        ];

        $existing = is_array($data[self::NEXT_ACTION_KEY] ?? null)
            ? $data[self::NEXT_ACTION_KEY]
            : [];

        if ($this->comparable($existing) === $plan) {
            $stored = $existing;
        } else {
            $stored = [
                ...$plan,
                'updated_at' => now()->toIso8601String(),
            ];
            $data[self::NEXT_ACTION_KEY] = $stored;
            $profile->data = $data;
            $profile->saveQuietly();
        }

        $conversation->update([
            'next_best_action' => $stored['action_text'],
            'next_follow_up_at' => null,
        ]);

        $this->recordState($profile, $conversation, $stored);

        return $stored;
    }

    private function recordState(
        RealEstateProfile $profile,
        ConversationControl $conversation,
        array $plan,
    ): void {
        if (! Schema::hasTable('real_estate_next_best_action_events')) {
            return;
        }

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        $state = [
            'profile_id' => (int) $profile->id,
            'action_code' => (string) ($plan['action_code'] ?? ''),
            'priority' => (string) ($plan['priority'] ?? ''),
            'stage' => (string) ($plan['stage'] ?? ''),
            'reason_codes' => array_values($plan['reason_codes'] ?? []),
            'match_count' => (int) ($plan['match_count'] ?? 0),
            'blocking' => true,
        ];
        $stateKey = hash(
            'sha256',
            (string) json_encode(
                $state,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
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
                    'contains_raw_customer_message' => false,
                    'contains_contact_details' => false,
                    'contains_private_seller_floor' => false,
                    'follow_up_scheduling_allowed' => false,
                    'fact_consistency_override' => true,
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
        $normalized = preg_replace(
            '/[^a-z0-9_\-]+/i',
            '_',
            mb_strtolower(trim($value))
        );

        return trim((string) $normalized, '_-') ?: 'unknown';
    }
}
