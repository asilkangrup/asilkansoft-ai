<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationFollowUp;
use App\Models\FinanceLead;
use App\Models\Order;
use App\Models\RealEstateWebhookReceipt;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RealEstateReadinessService
{
    public function snapshot(): array
    {
        $isolation = app(RealEstateIsolationService::class);
        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->first();

        $botIdentityValid = $isolation->supportsProductionBot($bot);
        $organizationIdentityValid = $isolation->organizationValid();
        $webhookAuthConfigured = app(RealEstateWebhookAuthService::class)->configured();
        $durableWebhookReceiptsReady = Schema::hasTable('real_estate_webhook_receipts');
        $apiKeyConfigured = $botIdentityValid && filled($bot?->openai_api_key);
        $apiKeyEncryptedAtRest = $apiKeyConfigured && $this->apiKeyEncryptedAtRest();
        $whatsappStatus = strtolower(trim((string) ($bot?->whatsapp_status ?? '')));
        $whatsappConnected = in_array($whatsappStatus, ['connected', 'open'], true);
        $aiEnabled = $botIdentityValid && (bool) $bot?->ai_enabled;
        $subscriptionAllowsAi = $botIdentityValid
            && $bot !== null
            && $bot->whatsappAiKullanilabilirMi();
        $groupRoutingDisabled = $botIdentityValid && ! (bool) $bot?->group_routing_enabled;
        $followUpsDisabled = $botIdentityValid
            && ! (bool) $bot?->follow_up_enabled
            && ! (bool) $bot?->second_follow_up_enabled;

        $activeFollowUps = ConversationFollowUp::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('is_active', true)
            ->count();

        $genericOrders = Order::query()
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->count();

        $financeLeads = FinanceLead::query()
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->count();

        $checks = [
            'bot_identity_valid' => $botIdentityValid,
            'organization_identity_valid' => $organizationIdentityValid,
            'webhook_auth_configured' => $webhookAuthConfigured,
            'durable_webhook_receipts_ready' => $durableWebhookReceiptsReady,
            'openai_api_key_configured' => $apiKeyConfigured,
            'openai_api_key_encrypted_at_rest' => $apiKeyEncryptedAtRest,
            'ai_enabled' => $aiEnabled,
            'subscription_allows_ai' => $subscriptionAllowsAi,
            'whatsapp_connected' => $whatsappConnected,
            'group_routing_disabled' => $groupRoutingDisabled,
            'follow_ups_disabled' => $followUpsDisabled,
            'active_follow_up_records' => $activeFollowUps,
            'generic_order_records' => $genericOrders,
            'finance_lead_records' => $financeLeads,
        ];

        $zeroRequired = [
            'active_follow_up_records',
            'generic_order_records',
            'finance_lead_records',
        ];

        $blockingChecks = collect($checks)
            ->filter(function (mixed $value, string $key) use ($zeroRequired): bool {
                if (in_array($key, $zeroRequired, true)) {
                    return (int) $value !== 0;
                }

                return $value !== true;
            })
            ->keys()
            ->values()
            ->all();

        return [
            'ok' => $botIdentityValid && $organizationIdentityValid,
            'service' => 'real-estate-ai',
            'user_id' => RealEstateIsolationService::USER_ID,
            'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
            'bot_id' => RealEstateIsolationService::BOT_ID,
            'instance' => RealEstateIsolationService::INSTANCE,
            'isolated' => true,
            'dedicated_inbound_pipeline' => true,
            'shared_wai_commerce_pipeline' => false,
            'follow_up_runtime_blocked' => true,
            'ready_for_live_traffic' => $blockingChecks === [],
            'checks' => $checks,
            'blocking_checks' => $blockingChecks,
            'webhook_telemetry_24h' => $this->webhookTelemetry($durableWebhookReceiptsReady),
        ];
    }

    private function apiKeyEncryptedAtRest(): bool
    {
        $raw = DB::table('ai_bots')
            ->where('id', RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
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

    private function webhookTelemetry(bool $tableReady): array
    {
        if (! $tableReady) {
            return [
                'received' => 0,
                'replied' => 0,
                'ignored' => 0,
                'failed' => 0,
                'retried' => 0,
            ];
        }

        $base = RealEstateWebhookReceipt::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subDay());

        return [
            'received' => (clone $base)->count(),
            'replied' => (clone $base)->where('status', 'replied')->count(),
            'ignored' => (clone $base)->where('status', 'ignored')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'retried' => (clone $base)->where('attempts', '>', 1)->count(),
        ];
    }
}
