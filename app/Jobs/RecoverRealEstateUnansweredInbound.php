<?php

namespace App\Jobs;

use App\Services\RealEstateInboundRecoveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecoverRealEstateUnansweredInbound implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public array $backoff = [300, 900, 1800];

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
        // A failed provider call can mean exhausted credit. Never loop
        // automatically and spend the same request repeatedly; failed receipts
        // remain recoverable after the operator restores capacity. Only a busy
        // receipt is retried because no model request was made for it.
        if ((int) ($stats['busy'] ?? 0) > 0) {
            $this->release(300);
        }
    }
}
