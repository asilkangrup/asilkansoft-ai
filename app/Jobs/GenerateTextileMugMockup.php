<?php

namespace App\Jobs;

use App\Services\Textile\TextileWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTextileMugMockup implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public readonly int $botId,
        public readonly int $conversationId,
        public readonly string $instance,
        public readonly string $phone,
        public readonly string $batchToken,
    ) {
        $this->onQueue('default');
    }

    public function handle(TextileWhatsAppInboundService $service): void
    {
        $service->completeMugBatch(
            botId: $this->botId,
            conversationId: $this->conversationId,
            instance: $this->instance,
            phone: $this->phone,
            batchToken: $this->batchToken,
        );
    }
}
