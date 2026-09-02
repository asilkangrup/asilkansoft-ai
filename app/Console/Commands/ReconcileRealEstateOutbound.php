<?php

namespace App\Console\Commands;

use App\Services\RealEstateOutboundReconciliationService;
use Illuminate\Console\Command;

class ReconcileRealEstateOutbound extends Command
{
    protected $signature = 'real-estate:reconcile-outbound
        {--limit=10 : Tek çalışmada incelenecek en fazla kayıt}
        {--age=120 : Saniye cinsinden minimum ağ-sınırı yaşı}';

    protected $description = 'Reconcile aged isolated Emlak AI outbound records against Evolution without resending any message.';

    public function handle(
        RealEstateOutboundReconciliationService $service,
    ): int {
        $stats = $service->reconcile(
            limit: (int) $this->option('limit'),
            ageSeconds: (int) $this->option('age'),
        );

        $this->line(sprintf(
            'examined=%d confirmed_sent=%d quarantined=%d unchanged=%d lookup_errors=%d',
            $stats['examined'],
            $stats['confirmed_sent'],
            $stats['quarantined'],
            $stats['unchanged'],
            $stats['lookup_errors'],
        ));

        return self::SUCCESS;
    }
}
