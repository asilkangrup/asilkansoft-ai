<?php

namespace App\Jobs;

use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessRealEstateWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Multiple dedicated real-estate workers are allowed to process different
     * customer chats concurrently. Jobs for the same WhatsApp chat are
     * released briefly until the preceding turn finishes, so memory, CRM and
     * outbound replies cannot race or arrive out of order.
     */
    public int $tries = 120;

    public int $timeout = 180;

    public function __construct(public array $payload)
    {
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))
                ->releaseAfter(1)
                ->expireAfter(300)
                ->shared(),
        ];
    }

    public function overlapKey(): string
    {
        $remoteJid = strtolower(trim((string) data_get(
            $this->payload,
            'data.key.remoteJid',
            ''
        )));
        $messageId = trim((string) data_get($this->payload, 'data.key.id', ''));
        $identity = $remoteJid !== ''
            ? $remoteJid
            : 'message:'.($messageId !== '' ? $messageId : 'unknown');

        return 'real-estate-chat:'.hash('sha256', $identity);
    }

    public function handle(RealEstateWhatsAppInboundService $inbound): void
    {
        unset($this->payload['_real_estate_authorized']);
        $inbound->process($this->payload);
    }
}
