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

    public int $tries = 12;

    public int $timeout = 240;

    public array $backoff = [2, 5, 10, 20, 30, 60];

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
        $hash = '';

        foreach (['real-estate-inbound-burst:', 'real-estate-media-batch:'] as $prefix) {
            if (str_starts_with($this->cacheKey, $prefix)) {
                $hash = substr($this->cacheKey, strlen($prefix));
                break;
            }
        }

        if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $hash = hash('sha256', $this->cacheKey);
        }

        return 'real-estate-chat:'.$hash;
    }

    public function handle(RealEstateWhatsAppInboundService $inbound): void
    {
        $batch = Cache::get($this->cacheKey);
        $scheduledKey = $this->cacheKey.':scheduled';

        if (! is_array($batch)) {
            Cache::forget($scheduledKey);

            return;
        }

        $quietUntil = (int) ($batch['quiet_until'] ?? 0);

        if ($quietUntil > now()->timestamp) {
            $this->release(max(1, $quietUntil - now()->timestamp));

            return;
        }

        // Pull first so a message arriving during slow media/valuation work
        // creates a fresh batch and its own scheduled job instead of being
        // deleted when this batch completes.
        $batch = Cache::pull($this->cacheKey);
        Cache::forget($scheduledKey);

        if (! is_array($batch)) {
            return;
        }

        $payloads = is_array($batch['payloads'] ?? null) ? $batch['payloads'] : [];
        $payloads = array_values(array_filter($payloads, 'is_array'));
        $last = count($payloads) - 1;

        foreach ($payloads as $index => $payload) {
            unset($payload['_real_estate_authorized']);

            try {
                $inbound->process($payload, suppressReply: $index < $last);
            } catch (\Throwable $exception) {
                // Preserve the failed turn and all following fragments. The
                // queue will retry internally with backoff; customers never
                // need to resend their first message after a transient AI or
                // provider error.
                Cache::put($this->cacheKey, [
                    'generation' => $this->generation,
                    'payloads' => array_slice($payloads, $index),
                    'quiet_until' => now()->timestamp,
                ], now()->addMinutes(10));
                Cache::put(
                    $scheduledKey,
                    true,
                    now()->addMinutes(10)
                );

                throw $exception;
            }
        }
    }
}
