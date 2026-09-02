<?php

namespace App\Jobs;

use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;

class ProcessRealEstateMediaBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 120;

    public int $timeout = 240;

    public function __construct(
        public string $cacheKey,
        public string $generation,
    ) {
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
        $prefix = 'real-estate-media-batch:';
        $hash = str_starts_with($this->cacheKey, $prefix)
            ? substr($this->cacheKey, strlen($prefix))
            : '';

        if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $hash = hash('sha256', $this->cacheKey);
        }

        return 'real-estate-chat:'.$hash;
    }

    public function handle(RealEstateWhatsAppInboundService $inbound): void
    {
        $batch = Cache::get($this->cacheKey);

        if (! is_array($batch) || ($batch['generation'] ?? null) !== $this->generation) {
            return;
        }

        $payloads = is_array($batch['payloads'] ?? null) ? $batch['payloads'] : [];
        Cache::forget($this->cacheKey);

        $payloads = array_values(array_filter($payloads, 'is_array'));
        $last = count($payloads) - 1;

        foreach ($payloads as $index => $payload) {
            unset($payload['_real_estate_authorized']);
            $inbound->process($payload, suppressReply: $index < $last);
        }
    }
}
