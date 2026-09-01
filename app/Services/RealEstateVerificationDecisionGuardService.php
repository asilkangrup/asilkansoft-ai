<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateVerificationDecisionGuardService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    public function process(ConversationControl $conversation): ?array
    {
        if (
            (int) $conversation->user_id !== self::REAL_ESTATE_USER_ID
            || (int) $conversation->ai_bot_id !== self::REAL_ESTATE_BOT_ID
        ) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->first();

        if (! $profile || $profile->profile_type !== 'seller') {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];

        if ($verification === [] || $decision === []) {
            return null;
        }

        $safeToMatch = (bool) ($verification['safe_to_match'] ?? false);
        $status = (string) ($verification['status'] ?? 'unverified');
        $riskScore = max(0, min(100, (int) ($verification['risk_score'] ?? 100)));

        $decision['verification_status'] = $status;
        $decision['verification_risk_score'] = $riskScore;
        $decision['ready_for_match'] = (bool) ($decision['ready_for_match'] ?? false)
            && $safeToMatch;

        if (! $safeToMatch) {
            $decision['next_best_action'] = (string) (
                $verification['next_best_action']
                ?? 'Taşınmaz doğrulamasını tamamla; doğrulanmadan yatırımcı eşleşmesini hazır fırsat gibi sunma.'
            );
        }

        $data['decision_intelligence'] = $decision;
        $profile->update(['data' => $data]);

        $updates = [];

        if (! $safeToMatch) {
            $updates['next_best_action'] = $decision['next_best_action'];
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }

        return $decision;
    }
}
