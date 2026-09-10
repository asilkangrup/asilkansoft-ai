<?php

namespace App\Jobs;

use App\Services\Textile\TextileWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ProcessTextileTextBurst implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct(public string $bufferKey)
    {
        $this->onConnection('database');
    }

    public function handle(TextileWhatsAppInboundService $service): void
    {
        $cache = Cache::store('database');
        $batch = $cache->lock($this->bufferKey.':lock', 15)->block(5, function () use ($cache) {
            $batch = $cache->get($this->bufferKey);
            if (! is_array($batch)) {
                return null;
            }
            $remaining = (int) ceil(10 - (microtime(true) - $batch['updated_at']));
            if ($remaining > 0) {
                $this->release($remaining);
                return null;
            }
            $cache->forget($this->bufferKey);
            return $batch;
        });
        if (! is_array($batch)) {
            return;
        }
        $payload = $batch['payload'];
        $payload['data']['message'] = ['conversation' => implode("\n", array_values($batch['texts']))];
        $payload['data']['key']['id'] = 'textile-burst-'.$batch['id'];
        $service->processPayload($payload, true);
    }
}
