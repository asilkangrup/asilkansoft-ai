<?php

namespace App\Jobs;

use App\Services\RealEstateInboundRecoveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecoverRealEstateUnansweredInbound implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 20;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public array $backoff = [60, 90, 120, 180, 300];

    public function __construct()
    {
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function uniqueId(): string
    {
        return 'isolated-real-estate-unanswered-inbound';
    }

    public function handle(RealEstateInboundRecoveryService $recovery): void
    {
        $stats = $recovery->recover(
            limit: 20,
            failedAgeSeconds: 60,
        );

        // Provider rate limits are transient. Keep the recovery job alive
        // until the saved inbound messages receive a reply; this never starts
        // a new conversation or creates a follow-up.
        if (
            (int) ($stats['failed'] ?? 0) > 0
            || (int) ($stats['busy'] ?? 0) > 0
        ) {
            $this->release(60);
        }
    }
}
