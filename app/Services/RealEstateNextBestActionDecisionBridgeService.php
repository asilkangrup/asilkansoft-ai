<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateNextBestActionDecisionBridgeService
{
    public function sync(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! $profile->belongsToIsolatedProductionScope()) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $plan = is_array($data['next_best_action_intelligence'] ?? null)
            ? $data['next_best_action_intelligence']
            : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];

        if ($plan === [] || $decision === []) {
            return null;
        }

        $decision['next_best_action'] = (string) ($plan['action_text'] ?? '');
        $decision['orchestrated_action_code'] = (string) ($plan['action_code'] ?? '');
        $decision['orchestrated_action_priority'] = (string) ($plan['priority'] ?? 'medium');
        $decision['orchestrated_action_blocking'] = (bool) ($plan['blocking'] ?? false);
        $decision['orchestrated_action_reasons'] = array_values($plan['reason_codes'] ?? []);
        $decision['orchestrated_single_question'] = $plan['single_question'] ?? null;
        $decision['orchestrated_guardrails'] = $plan['guardrails'] ?? [];

        $data['decision_intelligence'] = $decision;
        $profile->data = $data;
        $profile->saveQuietly();

        $conversation->update([
            'next_best_action' => $decision['next_best_action'],
        ]);

        return $decision;
    }
}
