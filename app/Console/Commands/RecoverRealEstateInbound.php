<?php

namespace App\Console\Commands;

use App\Services\RealEstateInboundRecoveryService;
use Illuminate\Console\Command;

class RecoverRealEstateInbound extends Command
{
    protected $signature = 'real-estate:recover-inbound
        {--limit=5 : Maximum failed/stale inbound receipts to inspect}
        {--age=90 : Minimum failed-receipt age in seconds}';

    protected $description = 'Recover isolated Emlak AI replies that failed after the customer message was safely received.';

    public function handle(RealEstateInboundRecoveryService $recovery): int
    {
        $stats = $recovery->recover(
            limit: (int) $this->option('limit'),
            failedAgeSeconds: (int) $this->option('age'),
        );

        $this->line((string) json_encode([
            'scope' => 'isolated_real_estate',
            'automatic_follow_up' => false,
            'stats' => $stats,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
