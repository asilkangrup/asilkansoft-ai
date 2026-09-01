<?php

namespace App\Observers;

use App\Models\ConversationControl;
use App\Services\RealEstateIsolationService;

class ConversationControlObserver
{
    public function saving(ConversationControl $conversation): void
    {
        if ((int) $conversation->ai_bot_id !== RealEstateIsolationService::BOT_ID) {
            return;
        }

        // Bot 35 is permanently owned by the isolated Emlak AI tenant. Even
        // though organization_id is not mass assignable on the shared model,
        // direct model assignment here guarantees every new/update path keeps
        // this conversation inside user 40 / organization 37.
        $conversation->user_id = RealEstateIsolationService::USER_ID;
        $conversation->organization_id = RealEstateIsolationService::ORGANIZATION_ID;
    }
}
