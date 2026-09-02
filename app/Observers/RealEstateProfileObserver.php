<?php

namespace App\Observers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateCaseLifecycleService;
use App\Services\RealEstateEvidenceLedgerService;
use App\Services\RealEstateFactConsistencyActionService;
use App\Services\RealEstateInvestorMandateService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
use App\Services\RealEstateNextBestActionDecisionBridgeService;
use App\Services\RealEstateNextBestActionService;
use App\Services\RealEstateOperatorAlertService;
use App\Services\RealEstateSellerMotivationService;
use App\Services\RealEstateValuationResearchOutputGuardService;

class RealEstateProfileObserver
{
    public function saved(RealEstateProfile $profile): void
    {
        $profile = $profile->fresh();

        if (! $profile) {
            return;
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        // Valuation data may contain prose/labels originating from web search.
        // Remove that untrusted research text before any deterministic CRM,
        // negotiation or prompt-building service can consume the saved profile.
        app(RealEstateValuationResearchOutputGuardService::class)->sanitize($profile);
        $profile->refresh();

        app(RealEstateInvestorMandateService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateSellerMotivationService::class)->sync($profile);
        $profile->refresh();

        $this->recordEvidence($profile, $conversation);
        app(RealEstateOperatorAlertService::class)->sync($profile);
        app(RealEstateMatchLedgerService::class)->sync($profile);
        app(RealEstateCaseLifecycleService::class)->sync($profile);

        // Recompute after every guarded profile mutation. Both services save
        // quietly, so they cannot recurse through this observer. The final
        // valuation / verification / matching filter in a turn therefore gets
        // the last word on the single next action exposed to the AI context.
        app(RealEstateNextBestActionService::class)->process($conversation);

        // A customer-side conflict in stable property identity is even more
        // fundamental than valuation or matching readiness. Keep the last
        // confirmed fact in memory and require one deterministic confirmation
        // question before any unconfirmed replacement can influence pricing or
        // investor matching.
        app(RealEstateFactConsistencyActionService::class)->sync($conversation);

        app(RealEstateNextBestActionDecisionBridgeService::class)->sync($conversation);
    }

    private function recordEvidence(
        RealEstateProfile $profile,
        \App\Models\ConversationControl $conversation
    ): void {
        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null)
            ? $data['media_findings']
            : [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            app(RealEstateEvidenceLedgerService::class)->record(
                conversation: $conversation,
                profile: $profile,
                analysis: $finding,
            );
        }
    }
}
