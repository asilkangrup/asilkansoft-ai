<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationFollowUp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class RealEstateReadinessService
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    private const REAL_ESTATE_INSTANCE = 'emlak-ai-35';

    public function snapshot(): array
    {
        $bot = AiBot::query()
            ->whereKey(self::REAL_ESTATE_BOT_ID)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->first();

        $botIdentityValid = $bot !== null;
        $instanceValid = $botIdentityValid
            && trim((string) $bot->whatsapp_instance) === self::REAL_ESTATE_INSTANCE;
        $webhookAuthConfigured = app(RealEstateWebhookAuthService::class)->configured();
        $apiKeyConfigured = $botIdentityValid && filled($bot->openai_api_key);
        $apiKeyEncryptedAtRest = $apiKeyConfigured
            && $this->apiKeyEncryptedAtRest();
        $whatsappConnected = $botIdentityValid
            && trim((string) $bot->whatsapp_status) === 'connected';
        $followUpsDisabled = $botIdentityValid
            && ! (bool) $bot->follow_up_enabled
            && ! (bool) $bot->second_follow_up_enabled;
        $activeFollowUps = ConversationFollowUp::query()
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->where('is_active', true)
            ->count();

        $checks = [
            'bot_identity_valid' => $botIdentityValid,
            'instance_valid' => $instanceValid,
            'webhook_auth_configured' => $webhookAuthConfigured,
            'openai_api_key_configured' => $apiKeyConfigured,
            'openai_api_key_encrypted_at_rest' => $apiKeyEncryptedAtRest,
            'whatsapp_connected' => $whatsappConnected,
            'follow_ups_disabled' => $followUpsDisabled,
            'active_follow_up_records' => $activeFollowUps,
        ];

        $ready = $botIdentityValid
            && $instanceValid
            && $webhookAuthConfigured
            && $apiKeyConfigured
            && $apiKeyEncryptedAtRest
            && $whatsappConnected
            && $followUpsDisabled
            && $activeFollowUps === 0;

        return [
            'ok' => $botIdentityValid,
            'service' => 'real-estate-ai',
            'user_id' => self::REAL_ESTATE_USER_ID,
            'bot_id' => self::REAL_ESTATE_BOT_ID,
            'instance' => self::REAL_ESTATE_INSTANCE,
            'isolated' => true,
            'ready_for_live_traffic' => $ready,
            'checks' => $checks,
            'blocking_checks' => collect($checks)
                ->filter(function (mixed $value, string $key): bool {
                    if ($key === 'active_follow_up_records') {
                        return (int) $value !== 0;
                    }

                    return $value !== true;
                })
                ->keys()
                ->values()
                ->all(),
        ];
    }

    private function apiKeyEncryptedAtRest(): bool
    {
        $raw = DB::table('ai_bots')
            ->where('id', self::REAL_ESTATE_BOT_ID)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->value('openai_api_key');

        $raw = trim((string) ($raw ?? ''));

        if ($raw === '') {
            return false;
        }

        try {
            return trim(Crypt::decryptString($raw)) !== '';
        } catch (Throwable) {
            return false;
        }
    }
}
