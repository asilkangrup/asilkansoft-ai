<?php

namespace App\Console\Commands;

use App\Services\RealEstateClosingRiskService;
use Illuminate\Console\Command;

class RealEstateClosingHealth extends Command
{
    protected $signature = 'real-estate:closing-health {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect isolated real-estate closing workload without exposing customer payloads or PII';

    public function handle(RealEstateClosingRiskService $risk): int
    {
        $snapshot = $risk->health();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $snapshot,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $counts = (array) ($snapshot['counts'] ?? []);
        $this->info('Emlak AI Kapanış Kontrolü: '.($snapshot['state'] ?? 'unknown'));
        $this->line('Kabul edilmiş anlaşma: '.(int) ($counts['accepted_deals'] ?? 0));
        $this->line('Açılmış kapanış dosyası: '.(int) ($counts['cases_opened'] ?? 0));
        $this->line('Kritik insan kontrolü: '.(int) ($counts['critical'] ?? 0));
        $this->line('Operatör aksiyonu: '.(int) ($counts['warning'] ?? 0));
        $this->line('Tamamlanan: '.(int) ($counts['completed'] ?? 0));
        $this->line('Geçmiş/teyitsiz randevu: '.(int) ($counts['appointment_missed_or_unconfirmed'] ?? 0));
        $this->line('48+ saat güncellenmeyen: '.(int) ($counts['stale'] ?? 0));

        return self::SUCCESS;
    }
}
