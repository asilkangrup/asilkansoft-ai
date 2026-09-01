<?php

namespace App\Jobs;

use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRealEstateWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public array $payload)
    {
        // Production currently has no dedicated queue worker. Keep this isolated
        // job synchronous until a worker is provisioned, while preserving a clean
        // boundary for switching to an async queue later.
        $this->onConnection('sync');
    }

    public function handle(RealEstateWhatsAppInboundService $service): void
    {
        $service->process($this->payload);
    }
}
