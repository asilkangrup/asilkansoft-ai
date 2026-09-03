<?php

namespace App\Console\Commands;

use App\Services\RealEstateOperatorValuationService;
use Illuminate\Console\Command;

class RealEstateValuationQueue extends Command
{
    protected $signature = 'real-estate:valuation-queue
        {--limit=25 : Maximum privacy-safe queue rows}
        {--json : Emit machine-readable JSON}';

    protected $description = 'Inspect operator-triggered isolated real-estate valuation research without exposing customer payloads, prices or PII';

    public function handle(RealEstateOperatorValuationService $service): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $summary = $service->summary();
        $items = $service->queueItems($limit)->all();
        $payload = [
            'summary' => $summary,
            'items' => $items,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $counts = (array) ($summary['counts'] ?? []);
        $this->info('Emlak AI Operatör Değerleme Kuyruğu');
        $this->line('Araştırma bekleyen: '.(int) ($counts['needs_research'] ?? 0));
        $this->line('Yüksek öncelik: '.(int) ($counts['high_priority'] ?? 0));
        $this->line('Kuyrukta: '.(int) ($counts['queued'] ?? 0));
        $this->line('Çalışıyor: '.(int) ($counts['running'] ?? 0));
        $this->line('Başarısız: '.(int) ($counts['failed'] ?? 0));
        $this->line('Bloklu: '.(int) ($counts['blocked'] ?? 0));
        $this->line('Otomatik WhatsApp araştırması: kapalı');
        $this->line('Müşteri follow-up: kapalı');

        return self::SUCCESS;
    }
}
