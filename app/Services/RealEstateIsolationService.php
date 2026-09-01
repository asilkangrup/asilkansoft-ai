<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;

class RealEstateIsolationService
{
    public const USER_ID = 40;

    public const ORGANIZATION_ID = 37;

    public const BOT_ID = 35;

    public const INSTANCE = 'emlak-ai-35';

    public function supportsBotIdentity(?AiBot $bot): bool
    {
        if (! $bot) {
            return false;
        }

        return (int) $bot->id === self::BOT_ID
            && (int) $bot->user_id === self::USER_ID
            && trim((string) $bot->business_sector) === 'real_estate';
    }

    public function supportsProductionBot(?AiBot $bot): bool
    {
        return $this->supportsBotIdentity($bot)
            && trim((string) $bot?->whatsapp_instance) === self::INSTANCE;
    }

    public function supportsConversation(?ConversationControl $conversation): bool
    {
        if (! $conversation) {
            return false;
        }

        return (int) $conversation->user_id === self::USER_ID
            && (int) $conversation->organization_id === self::ORGANIZATION_ID
            && (int) $conversation->ai_bot_id === self::BOT_ID;
    }

    public function organizationValid(): bool
    {
        return Organization::query()
            ->whereKey(self::ORGANIZATION_ID)
            ->where('owner_user_id', self::USER_ID)
            ->where('status', 'active')
            ->exists();
    }

    public function botValid(): bool
    {
        $bot = AiBot::query()->find(self::BOT_ID);

        return $this->supportsProductionBot($bot);
    }

    public function organizationIdFor(AiBot $bot): ?int
    {
        if (! $this->supportsBotIdentity($bot) || ! $this->organizationValid()) {
            return null;
        }

        return self::ORGANIZATION_ID;
    }

    public function blocksGenericCommerce(AiBot $bot): bool
    {
        return $this->supportsBotIdentity($bot);
    }

    public function blocksFollowUps(AiBot $bot): bool
    {
        return (int) $bot->id === self::BOT_ID
            && (int) $bot->user_id === self::USER_ID;
    }
}
