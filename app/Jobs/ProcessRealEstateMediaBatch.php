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

    public int $tries = 2;

    public int $timeout = 240;

    public array $backoff = [60];

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

    private function isImagePayload(array $payload): bool
    {
        $message = data_get($payload, 'data.message', []);

        if (! is_array($message)) {
            return false;
        }

        $encoded = json_encode($message);

        return is_string($encoded) && str_contains($encoded, '"imageMessage"');
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

        if (! is_array($batch)) {
            return;
        }

        $payloads = is_array($batch['payloads'] ?? null) ? $batch['payloads'] : [];
        $payloads = array_values(array_filter($payloads, 'is_array'));
        $last = count($payloads) - 1;
        foreach ($payloads as $index => $payload) {
            unset($payload['_real_estate_authorized']);

            try {
                $inbound->process(
                    $payload,
                    suppressReply: $index < $last,
                    // Photos and documents are stored in the CRM gallery. No
                    // automatic vision/OCR call is made in the low-cost mode.
                    analyzeMedia: false,
                );
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

        // A fragment may arrive after this job reads the cache but before it
        // pulls the current batch. Keep the scheduled marker throughout
        // processing, then atomically hand any newer pending burst to one new
        // job. This closes the debounce race that could strand the customer's
        // final word without a reply.
        $this->schedulePendingBatch($scheduledKey);
    }

    private function schedulePendingBatch(string $scheduledKey): void
    {
        Cache::forget($scheduledKey);
        $pending = Cache::get($this->cacheKey);

        if (! is_array($pending)) {
            return;
        }

        $generation = trim((string) ($pending['generation'] ?? ''));
        $generation = $generation !== '' ? $generation : $this->generation;

        if (! Cache::add($scheduledKey, true, now()->addSeconds(90))) {
            return;
        }

        $delay = max(
            1,
            (int) ($pending['quiet_until'] ?? now()->timestamp)
                - now()->timestamp
        );

        self::dispatch($this->cacheKey, $generation)
            ->delay(now()->addSeconds($delay));
    }
}
