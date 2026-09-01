<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateVerificationDecisionGuardService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    private const EVIDENCE_TAG_PREFIX = 'real_estate:evidence:';

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

        $evidenceQuality = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: true);

        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : [];
        $verification = is_array($data['verification_intelligence'] ?? null)
            ? $data['verification_intelligence']
            : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];

        if ($verification === [] || $decision === []) {
            $this->updateEvidenceTags($conversation, $evidenceQuality, false);

            return null;
        }

        $verificationSafe = (bool) ($verification['safe_to_match'] ?? false);
        $evidenceSafe = (bool) ($evidenceQuality['sufficient_for_matching'] ?? false);
        $safeToMatch = $verificationSafe && $evidenceSafe;
        $status = (string) ($verification['status'] ?? 'unverified');
        $riskScore = max(0, min(100, (int) ($verification['risk_score'] ?? 100)));

        $decision['verification_status'] = $status;
        $decision['verification_risk_score'] = $riskScore;
        $decision['evidence_quality_status'] = (string) ($evidenceQuality['status'] ?? 'none');
        $decision['documentary_evidence_count'] = (int) (
            $evidenceQuality['qualified_documentary_evidence_count'] ?? 0
        );
        $decision['evidence_identity_signals'] = array_values(
            $evidenceQuality['identity_signals'] ?? []
        );
        $decision['evidence_sufficient_for_matching'] = $evidenceSafe;
        $decision['ready_for_match'] = (bool) ($decision['ready_for_match'] ?? false)
            && $safeToMatch;

        if (! $safeToMatch) {
            $decision['next_best_action'] = ! $verificationSafe
                ? (string) (
                    $verification['next_best_action']
                    ?? 'Taşınmaz doğrulamasını tamamla; doğrulanmadan yatırımcı eşleşmesini hazır fırsat gibi sunma.'
                )
                : (string) (
                    $evidenceQuality['next_best_action']
                    ?? 'Taşınmazı yatırımcı eşleştirmesine almadan önce yeterli belge desteğini tamamla.'
                );
        }

        $data['decision_intelligence'] = $decision;
        $profile->update(['data' => $data]);

        $updates = [
            'tags' => $this->evidenceTags(
                currentTags: $conversation->etiketler(),
                evidenceQuality: $evidenceQuality,
                readyForMatch: (bool) ($decision['ready_for_match'] ?? false),
            ),
        ];

        if (! $safeToMatch) {
            $updates['next_best_action'] = $decision['next_best_action'];
        }

        $conversation->update($updates);

        return $decision;
    }

    private function updateEvidenceTags(
        ConversationControl $conversation,
        array $evidenceQuality,
        bool $readyForMatch
    ): void {
        $conversation->update([
            'tags' => $this->evidenceTags(
                currentTags: $conversation->etiketler(),
                evidenceQuality: $evidenceQuality,
                readyForMatch: $readyForMatch,
            ),
        ]);
    }

    private function evidenceTags(
        array $currentTags,
        array $evidenceQuality,
        bool $readyForMatch
    ): array {
        $tags = collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::EVIDENCE_TAG_PREFIX)
            )
            ->filter(fn ($tag): bool =>
                $readyForMatch || $tag !== 'real_estate:state:ready_for_match'
            )
            ->values();

        $status = (string) ($evidenceQuality['status'] ?? 'none');
        $tags->push(self::EVIDENCE_TAG_PREFIX.$status);

        if ((bool) ($evidenceQuality['sufficient_for_matching'] ?? false)) {
            $tags->push(self::EVIDENCE_TAG_PREFIX.'match_sufficient');
        }

        return $tags->unique()->values()->all();
    }
}
