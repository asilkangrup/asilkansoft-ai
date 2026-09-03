<?php

namespace App\Console\Commands;

use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOperatorMediaAnalysisService;
use Illuminate\Console\Command;

class RealEstateMediaAnalyze extends Command
{
    protected $signature = 'real-estate:media-analyze
        {chat_message_id : Isolated customer image/PDF ChatMessage id}';

    protected $description = 'Run one explicit operator image/PDF analysis for the isolated Emlak AI tenant without sending any customer message';

    public function handle(RealEstateOperatorMediaAnalysisService $service): int
    {
        $chatMessageId = max(1, (int) $this->argument('chat_message_id'));

        $state = $service->request(
            chatMessageId: $chatMessageId,
            operatorUserId: RealEstateIsolationService::USER_ID,
        );

        if (($state['status'] ?? null) === 'completed') {
            $this->line((string) json_encode(
                $state,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $result = $service->run(
            chatMessageId: $chatMessageId,
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
