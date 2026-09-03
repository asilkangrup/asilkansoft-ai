<?php

namespace App\Jobs;

use App\Services\RealEstateOperatorValuationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRealEstateOperatorValuationResearch implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $profileId,
        public readonly int $operatorUserId,
    ) {
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function uniqueId(): string
    {
        return 'isolated-real-estate-operator-valuation:'.$this->profileId;
    }

    public function handle(RealEstateOperatorValuationService $service): void
    {
        // Deliberately one attempt: an internal web-search/model failure must
        // never silently spend a second request. The operator can review and
        // explicitly queue a new pass after the failure is visible in CRM.
        $service->run(
            profileId: $this->profileId,
            operatorUserId: $this->operatorUserId,
        );
    }
}
