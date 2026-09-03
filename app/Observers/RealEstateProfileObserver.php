<?php

namespace App\Observers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateAuthorizationNextBestActionService;
use App\Services\RealEstateCaseLifecycleService;
use App\Services\RealEstateCommercialConsistencyGuardService;
use App\Services\RealEstateEvidenceLedgerService;
use App\Services\RealEstateFactConsistencyActionService;
use App\Services\RealEstateGroupNotificationService;
use App\Services\RealEstateInvestorMandateService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
use App\Services\RealEstateNextBestActionDecisionBridgeService;
use App\Services\RealEstateNextBestActionService;
use App\Services\RealEstateOperatorAlertService;
use App\Services\RealEstateOpportunityScoreService;
use App\Services\RealEstateSellerMotivationService;
use App\Services\RealEstateSellerOfferPacketService;
use App\Services\RealEstateSellerInvestorHandoffService;
use App\Services\RealEstateTitleOwnershipIntelligenceService;
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

        app(RealEstateValuationResearchOutputGuardService::class)->sanitize($profile);
        $profile->refresh();

        app(RealEstateInvestorMandateService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateSellerMotivationService::class)->sync($profile);
        $profile->refresh();

        app(RealEstateTitleOwnershipIntelligenceService::class)->sync($profile);
        $profile->refresh();

        app(RealEstateSellerOfferPacketService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateSellerInvestorHandoffService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateOpportunityScoreService::class)->sync($profile);
        $profile->refresh();

        $this->recordEvidence($profile, $conversation);
        app(RealEstateOperatorAlertService::class)->sync($profile);
        app(RealEstateMatchLedgerService::class)->sync($profile);

        // The AI never chats in groups. Inbound @g.us traffic remains ignored;
        // this service only pushes notification summaries to the three exact
        // Emlak AI operational groups.
        app(RealEstateGroupNotificationService::class)->sync($profile);
        $profile->refresh();

        app(RealEstateCaseLifecycleService::class)->sync($profile);

        app(RealEstateNextBestActionService::class)->process($conversation);
        app(RealEstateAuthorizationNextBestActionService::class)->sync($conversation);
        app(RealEstateFactConsistencyActionService::class)->sync($conversation);
        app(RealEstateNextBestActionDecisionBridgeService::class)->sync($conversation);

        $profile->refresh();
        app(RealEstateCommercialConsistencyGuardService::class)->sync($profile);
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
