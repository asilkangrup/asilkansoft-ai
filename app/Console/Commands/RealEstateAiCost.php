<?php

namespace App\Console\Commands;

use App\Services\RealEstateAiCostService;
use Illuminate\Console\Command;

class RealEstateAiCost extends Command
{
    protected $signature = 'real-estate:ai-cost {--hours=24 : Aggregate window in hours, max 720}';

    protected $description = 'Show aggregate OpenAI token-cost telemetry for the isolated real-estate account';

    public function handle(RealEstateAiCostService $costs): int
    {
        $hours = max(1, min(720, (int) $this->option('hours')));
        $summary = $costs->summary($hours);

        $this->line((string) json_encode(
            $summary,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return self::SUCCESS;
    }
}
