<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Facades\Log;

class RealEstateAwareFinanceLeadExtractorService extends FinanceLeadExtractorService
{
    public function extract(AiBot $aiBot, array $messages): array
    {
        if (! app(RealEstateIsolationService::class)->dedicatedOpenAiOnlyForBot($aiBot)) {
            return parent::extract($aiBot, $messages);
        }

        Log::info('REAL ESTATE SHARED FINANCE EXTRACTOR BLOCKED', [
            'user_id' => $aiBot->user_id,
            'ai_bot_id' => $aiBot->id,
            'shared_openai_used' => false,
        ]);

        return [
            'type' => null,
            'name' => null,
            'phone' => null,
            'city' => null,
            'line_owner' => null,
            'mother_maiden_surname' => null,
            'limit_score' => null,
            'birth_date' => null,
            'tc_identity_number' => null,
            'limit' => null,
        ];
    }
}
