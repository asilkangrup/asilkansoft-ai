<?php

namespace App\Console\Commands;

use App\Services\RealEstateCommissionIntegrityService;
use Illuminate\Console\Command;

class RealEstateCommissionHealth extends Command
{
    protected $signature = 'real-estate:commission-health {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect isolated real-estate commission integrity without exposing customer payloads or PII';

    public function handle(RealEstateCommissionIntegrityService $integrity): int
    {
        $snapshot = $integrity->health();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $counts = (array) ($snapshot['counts'] ?? []);
        $this->info('Emlak AI Komisyon Kontrolü: '.($snapshot['state'] ?? 'unknown'));
        $this->line('Kabul edilmiş anlaşma: '.(int) ($counts['accepted_deals'] ?? 0));
        $this->line('Kapanışı tamamlanan: '.(int) ($counts['closing_completed'] ?? 0));
        $this->line('Tahsilat kaydı: '.(int) ($counts['collection_records'] ?? 0));
        $this->line('Tam tahsil edilen: '.(int) ($counts['fully_collected'] ?? 0));
        $this->line('Kısmi tahsilat: '.(int) ($counts['partial'] ?? 0));
        $this->line('Kritik tutarsızlık: '.(int) ($counts['critical'] ?? 0));
        $this->line('Operatör aksiyonu: '.(int) ($counts['warning'] ?? 0));
        $this->line('Bütünlük uyuşmazlığı: '.(int) ($counts['integrity_mismatch'] ?? 0));
        $this->line('72+ saat güncellenmeyen: '.(int) ($counts['stale'] ?? 0));

        return self::SUCCESS;
    }
}
