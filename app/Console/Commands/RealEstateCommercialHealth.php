<?php

namespace App\Console\Commands;

use App\Services\RealEstateCommercialConsistencyGuardService;
use Illuminate\Console\Command;

class RealEstateCommercialHealth extends Command
{
    protected $signature = 'real-estate:commercial-health {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect isolated real-estate commercial consistency without exposing customer payloads, prices or PII';

    public function handle(RealEstateCommercialConsistencyGuardService $guard): int
    {
        $snapshot = $guard->health();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $counts = (array) ($snapshot['counts'] ?? []);
        $this->info('Emlak AI Ticari Tutarlılık: '.($snapshot['state'] ?? 'unknown'));
        $this->line('İzole profil: '.(int) ($counts['profiles'] ?? 0));
        $this->line('Teyit bekleyen: '.(int) ($counts['blocked'] ?? 0));
        $this->line('Satıcı fiyat/alt sınır çelişkisi: '.(int) ($counts['seller_floor_above_asking'] ?? 0));
        $this->line('Yatırımcı bütçe aralığı çelişkisi: '.(int) ($counts['investor_budget_range_inverted'] ?? 0));

        return self::SUCCESS;
    }
}
