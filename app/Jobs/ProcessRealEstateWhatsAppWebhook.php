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
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function handle(RealEstateWhatsAppInboundService $inbound): void
    {
        unset($this->payload['_real_estate_authorized']);
        $inbound->process($this->payload);
    }
}
