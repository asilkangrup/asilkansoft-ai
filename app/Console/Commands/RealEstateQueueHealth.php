<?php

namespace App\Console\Commands;

use App\Services\RealEstateQueueTelemetryService;
use Illuminate\Console\Command;

class RealEstateQueueHealth extends Command
{
    protected $signature = 'real-estate:queue-health {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect only the isolated real-estate queue backlog without exposing customer payloads';

    public function handle(RealEstateQueueTelemetryService $telemetry): int
    {
        $snapshot = $telemetry->snapshot();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return ($snapshot['ready'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Emlak AI Queue: '.($snapshot['state'] ?? 'unknown'));
        $this->line('Bekleyen: '.(int) ($snapshot['pending_jobs'] ?? 0));
        $this->line('Hazır: '.(int) ($snapshot['available_jobs'] ?? 0));
        $this->line('İşlenen: '.(int) ($snapshot['reserved_jobs'] ?? 0));
        $this->line('Gecikmeli: '.(int) ($snapshot['delayed_jobs'] ?? 0));
        $this->line('En eski bekleme: '.(int) ($snapshot['oldest_wait_seconds'] ?? 0).' sn');
        $this->line('Hedef paralel worker: '.(int) ($snapshot['target_parallel_workers'] ?? 0));

        return ($snapshot['ready'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
