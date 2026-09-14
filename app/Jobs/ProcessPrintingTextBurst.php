<?php

namespace App\Jobs;

use App\Services\Printing\PrintingWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

final class ProcessPrintingTextBurst implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;
    public int $timeout = 180;

    public function __construct(public string $bufferKey)
    {
        $this->onConnection('database');
    }

    public function handle(PrintingWhatsAppInboundService $service): void
    {
        $cache = Cache::store('database');

        $batch = $cache->lock($this->bufferKey.':lock', 15)->block(5, function () use ($cache) {
            $batch = $cache->get($this->bufferKey);
            if (! is_array($batch)) {
                return null;
            }

            $wait = max(1, (int) config('matbaa.debounce_seconds', 10));
            $remaining = (int) ceil($wait - (microtime(true) - (float) ($batch['updated_at'] ?? 0)));

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
        $payload['data']['message'] = [
            'conversation' => implode("\n", array_values($batch['texts'] ?? [])),
        ];
        $payload['data']['key']['id'] = 'printing-burst-'.$batch['id'];

        $service->processPayload($payload, true);
    }
}
