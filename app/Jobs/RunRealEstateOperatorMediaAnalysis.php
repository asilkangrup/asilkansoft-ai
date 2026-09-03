<?php

namespace App\Jobs;

use App\Services\RealEstateOperatorMediaAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRealEstateOperatorMediaAnalysis implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $chatMessageId,
        public readonly int $operatorUserId,
    ) {
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function uniqueId(): string
    {
        return 'isolated-real-estate-operator-media:'.$this->chatMessageId;
    }

    public function handle(RealEstateOperatorMediaAnalysisService $service): void
    {
        // Deliberately one attempt. Vision/PDF analysis is a paid operation and
        // must never silently retry after a provider/model failure.
        $service->run(
            chatMessageId: $this->chatMessageId,
            operatorUserId: $this->operatorUserId,
        );
    }
}
