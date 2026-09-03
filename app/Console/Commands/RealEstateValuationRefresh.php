<?php

namespace App\Console\Commands;

use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOperatorValuationService;
use Illuminate\Console\Command;

class RealEstateValuationRefresh extends Command
{
    protected $signature = 'real-estate:valuation-refresh
        {profile_id : Isolated seller profile id}';

    protected $description = 'Run one explicit operator valuation refresh for the isolated Emlak AI tenant without sending any customer message';

    public function handle(RealEstateOperatorValuationService $service): int
    {
        $profileId = max(1, (int) $this->argument('profile_id'));

        $service->request(
            profileId: $profileId,
            operatorUserId: RealEstateIsolationService::USER_ID,
        );

        $result = $service->run(
            profileId: $profileId,
            operatorUserId: RealEstateIsolationService::USER_ID,
        );

        $this->line((string) json_encode(
            $result,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return in_array($result['status'] ?? null, ['completed', 'blocked', 'busy'], true)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
