<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationFollowUp;
use App\Models\FinanceLead;
use App\Models\Order;
use App\Models\RealEstateCaseEvent;
use App\Models\RealEstateNegotiationEvent;
use App\Models\RealEstateOperatorAlert;
use App\Models\RealEstateOutboundDelivery;
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
        $instanceValid = $botIdentityValid
            && trim((string) ($bot?->whatsapp_instance ?? '')) === RealEstateIsolationService::INSTANCE;
        $organizationIdentityValid = $isolation->organizationValid();
        $webhookAuthConfigured = app(RealEstateWebhookAuthService::class)->configured();
        $durableWebhookReceiptsReady = Schema::hasTable('real_estate_webhook_receipts');
        $outboundDeliveryGuardReady = Schema::hasTable('real_estate_outbound_deliveries')
            && class_exists(RealEstateOutboundDeliveryService::class);
        $operatorAlertQueueReady = Schema::hasTable('real_estate_operator_alerts')
            && class_exists(RealEstateOperatorAlertService::class);
        $evidenceQualityGuardReady = class_exists(RealEstateEvidenceQualityService::class);
        $negotiationMemoryReady = Schema::hasTable('real_estate_negotiation_events')
            && class_exists(RealEstateNegotiationMemoryService::class);
        $caseLifecycleReady = Schema::hasTable('real_estate_case_events')
            && class_exists(RealEstateCaseLifecycleService::class);
        $sellerInvestorHandoffReady = class_exists(RealEstateSellerInvestorHandoffService::class);
        $audioTranscriptionReady = $this->audioTranscriptionReady();
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

        $unresolvedOutboundDeliveries = $outboundDeliveryGuardReady
            ? RealEstateOutboundDelivery::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->whereIn('status', ['sending', 'uncertain'])
                ->count()
            : 0;

        $checks = [
            'bot_identity_valid' => $botIdentityValid,
            'instance_valid' => $instanceValid,
            'organization_identity_valid' => $organizationIdentityValid,
            'webhook_auth_configured' => $webhookAuthConfigured,
            'durable_webhook_receipts_ready' => $durableWebhookReceiptsReady,
            'outbound_delivery_guard_ready' => $outboundDeliveryGuardReady,
            'operator_alert_queue_ready' => $operatorAlertQueueReady,
            'evidence_quality_guard_ready' => $evidenceQualityGuardReady,
            'negotiation_memory_ready' => $negotiationMemoryReady,
            'case_lifecycle_ready' => $caseLifecycleReady,
            'seller_investor_handoff_ready' => $sellerInvestorHandoffReady,
            'audio_transcription_ready' => $audioTranscriptionReady,
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
            'unresolved_outbound_deliveries' => $unresolvedOutboundDeliveries,
        ];

        $zeroRequired = [
            'active_follow_up_records',
            'generic_order_records',
            'finance_lead_records',
            'unresolved_outbound_deliveries',
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
            'ok' => $botIdentityValid && $instanceValid && $organizationIdentityValid,
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
            'outbound_telemetry_24h' => $this->outboundTelemetry($outboundDeliveryGuardReady),
            'operator_alert_telemetry' => $this->operatorAlertTelemetry($operatorAlertQueueReady),
            'negotiation_telemetry_24h' => $this->negotiationTelemetry($negotiationMemoryReady),
            'case_lifecycle_telemetry_24h' => $this->caseLifecycleTelemetry($caseLifecycleReady),
            'audio_telemetry_24h' => $this->audioTelemetry($audioTranscriptionReady),
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

    private function audioTranscriptionReady(): bool
    {
        if (! Schema::hasTable('chat_messages')) {
            return false;
        }

        $columns = [
            'media_transcript',
            'media_transcription_status',
            'media_transcription_model',
            'media_transcription_language',
            'media_transcribed_at',
        ];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('chat_messages', $column)) {
                return false;
            }
        }

        return method_exists(RealEstateOpenAIClient::class, 'transcribeAudio')
            && class_exists(RealEstateAudioTranscriptionService::class);
    }

    private function webhookTelemetry(bool $tableReady): array
    {
        if (! $tableReady) {
            return [
                'received' => 0,
                'replied' => 0,
                'processed_without_confirmed_reply' => 0,
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
            'processed_without_confirmed_reply' => (clone $base)
                ->where('status', 'processed')
                ->count(),
            'ignored' => (clone $base)->where('status', 'ignored')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'retried' => (clone $base)->where('attempts', '>', 1)->count(),
        ];
    }

    private function outboundTelemetry(bool $ready): array
    {
        if (! $ready) {
            return [
                'reserved' => 0,
                'sending' => 0,
                'sent' => 0,
                'uncertain' => 0,
                'network_retries' => 0,
            ];
        }

        $base = RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subDay());

        return [
            'reserved' => (clone $base)->where('status', 'reserved')->count(),
            'sending' => (clone $base)->where('status', 'sending')->count(),
            'sent' => (clone $base)->where('status', 'sent')->count(),
            'uncertain' => (clone $base)->where('status', 'uncertain')->count(),
            'network_retries' => (clone $base)->where('attempts', '>', 1)->count(),
        ];
    }

    private function operatorAlertTelemetry(bool $ready): array
    {
        if (! $ready) {
            return [
                'open' => 0,
                'critical_open' => 0,
                'high_open' => 0,
                'medium_open' => 0,
                'opened_24h' => 0,
                'resolved_24h' => 0,
            ];
        }

        $base = RealEstateOperatorAlert::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID);

        return [
            'open' => (clone $base)->where('status', 'open')->count(),
            'critical_open' => (clone $base)
                ->where('status', 'open')
                ->where('severity', 'critical')
                ->count(),
            'high_open' => (clone $base)
                ->where('status', 'open')
                ->where('severity', 'high')
                ->count(),
            'medium_open' => (clone $base)
                ->where('status', 'open')
                ->where('severity', 'medium')
                ->count(),
            'opened_24h' => (clone $base)
                ->where('opened_at', '>=', now()->subDay())
                ->count(),
            'resolved_24h' => (clone $base)
                ->where('resolved_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    private function negotiationTelemetry(bool $ready): array
    {
        if (! $ready) {
            return [
                'events' => 0,
                'seller_asking_changes' => 0,
                'seller_floor_changes' => 0,
                'investor_budget_changes' => 0,
                'confidential_floor_events' => 0,
            ];
        }

        $base = RealEstateNegotiationEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subDay());

        return [
            'events' => (clone $base)->count(),
            'seller_asking_changes' => (clone $base)
                ->where('event_type', 'seller_asking_price')
                ->whereIn('direction', ['up', 'down'])
                ->count(),
            'seller_floor_changes' => (clone $base)
                ->where('event_type', 'seller_minimum_price')
                ->whereIn('direction', ['up', 'down'])
                ->count(),
            'investor_budget_changes' => (clone $base)
                ->where('event_type', 'investor_budget_max')
                ->whereIn('direction', ['up', 'down'])
                ->count(),
            'confidential_floor_events' => (clone $base)
                ->where('event_type', 'seller_minimum_price')
                ->count(),
        ];
    }

    private function caseLifecycleTelemetry(bool $ready): array
    {
        if (! $ready) {
            return [
                'events' => 0,
                'case_opened' => 0,
                'stage_changed' => 0,
                'valuation_ready' => 0,
                'match_ready' => 0,
                'match_blocked' => 0,
                'match_candidates_changed' => 0,
            ];
        }

        $base = RealEstateCaseEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('created_at', '>=', now()->subDay());

        return [
            'events' => (clone $base)->count(),
            'case_opened' => (clone $base)->where('event_type', 'case_opened')->count(),
            'stage_changed' => (clone $base)->where('event_type', 'stage_changed')->count(),
            'valuation_ready' => (clone $base)->where('event_type', 'valuation_ready')->count(),
            'match_ready' => (clone $base)->where('event_type', 'match_ready')->count(),
            'match_blocked' => (clone $base)->where('event_type', 'match_blocked')->count(),
            'match_candidates_changed' => (clone $base)
                ->where('event_type', 'match_candidates_changed')
                ->count(),
        ];
    }

    private function audioTelemetry(bool $ready): array
    {
        if (! $ready) {
            return [
                'received' => 0,
                'transcribed' => 0,
                'failed' => 0,
                'too_large' => 0,
                'unsupported_format' => 0,
            ];
        }

        $base = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('message_type', 'audio')
            ->where('created_at', '>=', now()->subDay());

        return [
            'received' => (clone $base)->count(),
            'transcribed' => (clone $base)
                ->where('media_transcription_status', 'transcribed')
                ->count(),
            'failed' => (clone $base)
                ->where('media_transcription_status', 'failed')
                ->count(),
            'too_large' => (clone $base)
                ->where('media_transcription_status', 'too_large')
                ->count(),
            'unsupported_format' => (clone $base)
                ->where('media_transcription_status', 'unsupported_format')
                ->count(),
        ];
    }
}
