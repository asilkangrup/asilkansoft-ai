<?php

namespace App\Observers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateCaseLifecycleService;
use App\Services\RealEstateEvidenceLedgerService;
use App\Services\RealEstateInvestorMandateService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
use App\Services\RealEstateOperatorAlertService;
use App\Services\RealEstateSellerMotivationService;

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

        app(RealEstateInvestorMandateService::class)->sync($profile);
        $profile->refresh();
        app(RealEstateSellerMotivationService::class)->sync($profile);
        $profile->refresh();

        $this->recordEvidence($profile, $conversation);
        app(RealEstateOperatorAlertService::class)->sync($profile);
        app(RealEstateMatchLedgerService::class)->sync($profile);
        app(RealEstateCaseLifecycleService::class)->sync($profile);
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
