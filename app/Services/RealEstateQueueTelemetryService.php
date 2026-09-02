<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RealEstateQueueTelemetryService
{
    public const QUEUE = 'real-estate';

    public function snapshot(): array
    {
        if (! Schema::hasTable('jobs')) {
            return $this->emptySnapshot('jobs_table_missing');
        }

        $now = now()->timestamp;
        $base = DB::table('jobs')->where('queue', self::QUEUE);
        $pending = (clone $base)->count();
        $reserved = (clone $base)->whereNotNull('reserved_at')->count();
        $available = (clone $base)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->count();
        $delayed = (clone $base)
            ->whereNull('reserved_at')
            ->where('available_at', '>', $now)
            ->count();
        $oldestAvailableAt = (clone $base)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->min('available_at');
        $oldestWaitSeconds = is_numeric($oldestAvailableAt)
            ? max(0, $now - (int) $oldestAvailableAt)
            : 0;

        $state = match (true) {
            $available >= 20 || $oldestWaitSeconds >= 120 => 'critical',
            $available >= 5 || $oldestWaitSeconds >= 30 => 'busy',
            default => 'healthy',
        };

        return [
            'ready' => true,
            'queue' => self::QUEUE,
            'pending_jobs' => $pending,
            'available_jobs' => $available,
            'reserved_jobs' => $reserved,
            'delayed_jobs' => $delayed,
            'oldest_wait_seconds' => $oldestWaitSeconds,
            'state' => $state,
            'target_parallel_workers' => $this->targetWorkers(),
            'contains_customer_payload' => false,
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
        ];
    }

    private function targetWorkers(): int
    {
        $workers = (int) env('REAL_ESTATE_QUEUE_TARGET_WORKERS', 4);

        return max(2, min(8, $workers));
    }

    private function emptySnapshot(string $reason): array
    {
        return [
            'ready' => false,
            'queue' => self::QUEUE,
            'pending_jobs' => 0,
            'available_jobs' => 0,
            'reserved_jobs' => 0,
            'delayed_jobs' => 0,
            'oldest_wait_seconds' => 0,
            'state' => 'unavailable',
            'reason' => $reason,
            'target_parallel_workers' => $this->targetWorkers(),
            'contains_customer_payload' => false,
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
        ];
    }
}
