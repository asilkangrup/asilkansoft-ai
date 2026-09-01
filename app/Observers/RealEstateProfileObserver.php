<?php

namespace App\Observers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateNegotiationMemoryService;
use App\Services\RealEstateOperatorAlertService;

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

        app(RealEstateNegotiationMemoryService::class)->sync($profile);
        app(RealEstateOperatorAlertService::class)->sync($profile->fresh() ?? $profile);
    }
}
