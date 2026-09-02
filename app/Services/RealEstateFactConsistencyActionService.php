<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;

class RealEstateFactConsistencyActionService
{
    private const NEXT_ACTION_KEY = 'next_best_action_intelligence';

    private const MATCH_TAG_PREFIX = 'real_estate:match:';

    public function sync(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! in_array($profile->profile_type, ['seller', 'investor', 'buyer'], true)) {
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
        $existing = is_array($data[self::NEXT_ACTION_KEY] ?? null)
            ? $data[self::NEXT_ACTION_KEY]
            : [];
        $existingSummary = is_array($data['opportunity_match_summary'] ?? null)
            ? $data['opportunity_match_summary']
            : [];
        $quarantinedMatchCount = count($matches) > 0
            ? count($matches)
            : max(
                (int) ($existing['quarantined_match_count'] ?? 0),
                (int) ($existingSummary['quarantined_match_count'] ?? 0),
            );
        $isInvestor = in_array($profile->profile_type, ['investor', 'buyer'], true);
        $actionCode = $isInvestor
            ? 'confirm_investor_mandate_conflict'
            : 'confirm_property_fact_conflict';
        $primaryReason = $isInvestor
            ? 'customer_investor_mandate_conflict'
            : 'customer_property_fact_conflict';

        $plan = [
            'action_code' => $actionCode,
            'priority' => 'high',
            'action_text' => mb_substr($question, 0, 500),
            'single_question' => mb_substr($question, 0, 320),
            'blocking' => true,
            'reason_codes' => [
                $primaryReason,
                'conflict_'.$this->code($field),
            ],
            'match_count' => 0,
            'quarantined_match_count' => $quarantinedMatchCount,
            'stage' => trim((string) ($decision['stage'] ?? 'discovery')) ?: 'discovery',
            'deterministic' => true,
            'guardrails' => [
                'one_primary_action_per_turn' => true,
                'follow_up_scheduling_allowed' => false,
                'unconfirmed_property_identity_may_be_used_for_valuation' => $isInvestor,
                'unconfirmed_property_identity_may_be_used_for_matching' => $isInvestor,
                'unconfirmed_investor_mandate_may_be_used_for_matching' => ! $isInvestor,
                'binding_offer_claims_allowed' => false,
                'seller_private_floor_may_be_disclosed_to_investor' => false,
            ],
        ];

        $data['opportunity_matches'] = [];
        $data['opportunity_match_summary'] = [
            'count' => 0,
            'strongest_score' => null,
            'strongest_grade' => null,
            'mandate_aware' => true,
            'blocked_by_fact_consistency' => true,
            'blocking_profile_type' => $profile->profile_type,
            'quarantined_match_count' => $quarantinedMatchCount,
            'updated_at' => $existingSummary['updated_at'] ?? now()->toIso8601String(),
        ];

        if ($this->comparable($existing) === $plan) {
            $stored = $existing;
        } else {
            $stored = [
                ...$plan,
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $data[self::NEXT_ACTION_KEY] = $stored;
        $profile->data = $data;
        $profile->saveQuietly();

        $conversation->update([
            'next_best_action' => $stored['action_text'],
            'next_follow_up_at' => null,
            'tags' => $this->withoutMatchTags($conversation->etiketler()),
        ]);

        // If a pair was previously persisted as active, immediately reconcile
        // the privacy-safe match ledger after quarantine. This never sends a
        // message and cannot schedule follow-ups.
        app(RealEstateMatchLedgerService::class)->sync($profile->fresh());

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
            'match_count' => 0,
            'quarantined_match_count' => (int) ($plan['quarantined_match_count'] ?? 0),
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
                    'profile_type' => $profile->profile_type,
                    'blocking' => true,
                    'match_count' => 0,
                    'quarantined_match_count' => $state['quarantined_match_count'],
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

    private function withoutMatchTags(array $currentTags): array
    {
        return collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::MATCH_TAG_PREFIX)
            )
            ->unique()
            ->values()
            ->all();
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
