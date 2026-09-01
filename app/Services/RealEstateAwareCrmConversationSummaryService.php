<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;

class RealEstateAwareCrmConversationSummaryService extends CrmConversationSummaryService
{
    public function updateIfNeeded(
        ConversationControl $conversation,
        bool $force = false
    ): array {
        $isolation = app(RealEstateIsolationService::class);

        if (! $isolation->dedicatedOpenAiOnlyForConversation($conversation)) {
            return parent::updateIfNeeded($conversation, $force);
        }

        if (! $isolation->supportsConversation($conversation)) {
            Log::warning('REAL ESTATE SHARED OPENAI FIREWALL BLOCKED CRM SUMMARY', [
                'conversation_control_id' => $conversation->id,
                'user_id' => $conversation->user_id,
                'organization_id' => $conversation->organization_id,
                'ai_bot_id' => $conversation->ai_bot_id,
            ]);

            return [
                'updated' => false,
                'reason' => 'real_estate_scope_mismatch_blocked',
                'summary' => null,
                'next_best_action' => null,
                'source' => 'real_estate_firewall',
                'shared_openai_used' => false,
            ];
        }

        return app(RealEstateCrmSummaryService::class)->update($conversation);
    }
}
