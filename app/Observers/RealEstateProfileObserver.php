<?php

namespace App\Observers;

use App\Models\RealEstateProfile;
use App\Services\RealEstateOperatorAlertService;

class RealEstateProfileObserver
{
    public function saved(RealEstateProfile $profile): void
    {
        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());
    }
}
