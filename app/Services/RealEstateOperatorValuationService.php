<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RealEstateOperatorValuationService
{
    private const DATA_KEY = 'operator_valuation_research';

    private const LOCK_SECONDS = 360;

    /**
     * Privacy-safe operator backlog for the one isolated production tenant.
     * No customer text, phone, seller floor, parcel identity or price is
     * returned from this service.
     */
    public function queueItems(int $limit = 100): Collection
    {
        $limit = max(1, min(250, $limit));

        return RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->where('profile_type', 'seller')
            ->latest('last_extracted_at')
            ->latest('id')
            ->get()
            ->map(function (RealEstateProfile $profile): ?array {
                $data = is_array($profile->data) ? $profile->data : [];
                $decision = is_array($data['decision_intelligence'] ?? null)
                    ? $data['decision_intelligence']
                    : [];
                $plan = is_array($data['next_best_action_intelligence'] ?? null)
                    ? $data['next_best_action_intelligence']
                    : [];
                $request = is_array($data[self::DATA_KEY] ?? null)
                    ? $data[self::DATA_KEY]
                    : [];
                $freshness = app(RealEstateValuationFreshnessService::class)
                    ->assess($profile);

                $leadScore = max(
                    (int) ($decision['lead_score'] ?? 0),
                    (int) ($profile->conversation?->lead_score ?? 0),
                );
                $actionCode = trim((string) ($plan['action_code'] ?? ''));
                $requestStatus = trim((string) ($request['status'] ?? '')) ?: null;
                $researchActionActive = in_array($actionCode, [
                    'refresh_valuation_research',
                    'repair_comparable_integrity',
                ], true);

                // The deterministic next-best-action orchestrator has already
                // ranked stronger blockers such as identity/verification/core
                // data collection. Do not infer a paid valuation task merely
                // from lead score + stale/missing valuation, otherwise the
                // operator queue could spend credit before the stronger blocker
                // is resolved. Historical failed/blocked requests remain visible
                // for observability, but are marked inactive and cannot be
                // re-queued from the UI until valuation becomes the active NBA.
                if (
                    ! $researchActionActive
                    && ! in_array($requestStatus, ['queued', 'running', 'failed', 'blocked'], true)
                ) {
                    return null;
                }

                $priorityBase = match ($actionCode) {
                    'repair_comparable_integrity' => 300,
                    'refresh_valuation_research' => 250,
                    default => 200,
                };

                if (in_array($requestStatus, ['queued', 'running'], true)) {
                    $priorityBase += 50;
                }

                return [
                    'profile_id' => $profile->id,
                    'conversation_control_id' => $profile->conversation_control_id,
                    'action_code' => $actionCode !== ''
                        ? $actionCode
                        : 'refresh_valuation_research',
                    'research_action_active' => $researchActionActive,
                    'priority_score' => min(500, $priorityBase + $leadScore),
                    'lead_score' => $leadScore,
                    'lead_temperature' => (string) (
                        $decision['lead_temperature']
                        ?? $profile->conversation?->lead_temperature
                        ?? 'unknown'
                    ),
                    'freshness_status' => $freshness['status'] ?? 'missing',
                    'freshness_reasons' => array_values($freshness['reasons'] ?? []),
                    'confidence_score' => (int) ($freshness['confidence_score'] ?? 0),
                    'source_count' => (int) ($freshness['source_count'] ?? 0),
                    'comparable_count' => (int) ($freshness['comparable_count'] ?? 0),
                    'usable_for_decision' => (bool) ($freshness['usable_for_decision'] ?? false),
                    'usable_for_matching' => (bool) ($freshness['usable_for_matching'] ?? false),
                    'request_status' => $requestStatus,
                    'requested_at' => $request['requested_at'] ?? null,
                    'completed_at' => $request['completed_at'] ?? null,
                    'failure_code' => $request['failure_code'] ?? null,
                    'automatic_outbound_allowed' => false,
                    'customer_follow_up_allowed' => false,
                    'contains_customer_pii' => false,
                    'contains_private_seller_floor' => false,
                    'contains_raw_conversation' => false,
                ];
            })
            ->filter()
            ->sortByDesc('priority_score')
            ->take($limit)
            ->values();
    }

    public function summary(): array
    {
        $items = $this->queueItems(250);
        $bot = $this->productionBot();

        return [
            'ready' => $bot !== null
                && (bool) $bot->ai_enabled
                && filled($bot->openai_api_key)
                && ! (bool) $bot->follow_up_enabled
                && ! (bool) $bot->second_follow_up_enabled,
            'state' => 'operator_triggered_only',
            'counts' => [
                'needs_research' => $items->where('research_action_active', true)->count(),
                'high_priority' => $items
                    ->where('research_action_active', true)
                    ->where('priority_score', '>=', 320)
                    ->count(),
                'queued' => $items->where('request_status', 'queued')->count(),
                'running' => $items->where('request_status', 'running')->count(),
                'failed' => $items->where('request_status', 'failed')->count(),
                'blocked' => $items->where('request_status', 'blocked')->count(),
            ],
            'bot' => [
                'identity_valid' => $bot !== null,
                'ai_enabled' => (bool) ($bot?->ai_enabled ?? false),
                'dedicated_openai_key_configured' => filled($bot?->openai_api_key),
                'follow_ups_disabled' => $bot !== null
                    && ! (bool) $bot->follow_up_enabled
                    && ! (bool) $bot->second_follow_up_enabled,
            ],
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'automatic_market_research_allowed' => false,
            'operator_trigger_required' => true,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
        ];
    }

    public function request(int $profileId, int $operatorUserId): array
    {
        $this->assertOperator($operatorUserId);

        return DB::transaction(function () use ($profileId, $operatorUserId): array {
            $profile = RealEstateProfile::query()
                ->isolatedProduction()
                ->where('profile_type', 'seller')
                ->whereKey($profileId)
                ->lockForUpdate()
                ->firstOrFail();

            $now = now()->toIso8601String();
            $data = is_array($profile->data) ? $profile->data : [];
            $previous = is_array($data[self::DATA_KEY] ?? null)
                ? $data[self::DATA_KEY]
                : [];

            $state = [
                'status' => 'queued',
                'requested_at' => $now,
                'requested_by_user_id' => $operatorUserId,
                'started_at' => null,
                'completed_at' => null,
                'failure_code' => null,
                'attempt_count' => (int) ($previous['attempt_count'] ?? 0),
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_customer_pii' => false,
                'contains_private_seller_floor' => false,
                'contains_raw_conversation' => false,
            ];

            $data[self::DATA_KEY] = $state;
            $profile->forceFill(['data' => $data])->saveQuietly();

            return $this->safeState($profile->id, $state);
        }, 3);
    }

    /**
     * Execute exactly one operator-requested valuation pass.
     *
     * The existing valuation service is intentionally disabled for production
     * WhatsApp turns. For this one process-local, operator-requested call we
     * temporarily expose the existing feature flag, then restore its exact
     * previous state in finally. Queue workers execute jobs serially inside a
     * PHP process, so normal inbound turns can never inherit this capability.
     */
    public function run(int $profileId, int $operatorUserId): array
    {
        $this->assertOperator($operatorUserId);

        $lock = Cache::lock(
            'real-estate:operator-valuation:'
                .RealEstateIsolationService::ORGANIZATION_ID.':'
                .RealEstateIsolationService::BOT_ID.':'
                .$profileId,
            self::LOCK_SECONDS,
        );

        if (! $lock->get()) {
            return $this->result('busy', $profileId, 'research_already_running');
        }

        $flag = 'REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED';
        $previous = $this->captureEnvironment($flag);

        try {
            $profile = RealEstateProfile::query()
                ->isolatedProduction()
                ->with('conversation')
                ->where('profile_type', 'seller')
                ->whereKey($profileId)
                ->firstOrFail();
            $conversation = $profile->conversation;
            $bot = $this->productionBot();

            if (
                ! $conversation
                || ! app(RealEstateIsolationService::class)->supportsConversation($conversation)
                || ! $bot
                || ! (bool) $bot->ai_enabled
                || blank($bot->openai_api_key)
                || (bool) $bot->follow_up_enabled
                || (bool) $bot->second_follow_up_enabled
            ) {
                $this->markState($profileId, $operatorUserId, 'blocked', 'isolation_or_bot_guard_failed');

                return $this->result('blocked', $profileId, 'isolation_or_bot_guard_failed');
            }

            $this->markState($profileId, $operatorUserId, 'running');
            $this->enableEnvironment($flag);

            $valuation = app(RealEstateValuationService::class)->process(
                conversation: $conversation,
                message: 'güncel değerleme yeniden araştır',
            );

            if (! is_array($valuation) || $valuation === []) {
                $this->markState($profileId, $operatorUserId, 'blocked', 'valuation_not_generated');

                return $this->result('blocked', $profileId, 'valuation_not_generated');
            }

            $profile->refresh();
            $freshness = app(RealEstateValuationFreshnessService::class)->assess($profile);
            $this->markState($profileId, $operatorUserId, 'completed');

            return [
                ...$this->result('completed', $profileId),
                'freshness_status' => $freshness['status'] ?? null,
                'confidence_score' => (int) ($freshness['confidence_score'] ?? 0),
                'source_count' => (int) ($freshness['source_count'] ?? 0),
                'comparable_count' => (int) ($freshness['comparable_count'] ?? 0),
                'usable_for_decision' => (bool) ($freshness['usable_for_decision'] ?? false),
                'usable_for_matching' => (bool) ($freshness['usable_for_matching'] ?? false),
            ];
        } catch (Throwable $exception) {
            $this->markState(
                $profileId,
                $operatorUserId,
                'failed',
                'research_execution_failed:'.class_basename($exception),
            );
            report($exception);

            return $this->result(
                'failed',
                $profileId,
                'research_execution_failed:'.class_basename($exception),
            );
        } finally {
            $this->restoreEnvironment($flag, $previous);
            $lock->release();
        }
    }

    private function markState(
        int $profileId,
        int $operatorUserId,
        string $status,
        ?string $failureCode = null,
    ): void {
        DB::transaction(function () use ($profileId, $operatorUserId, $status, $failureCode): void {
            $profile = RealEstateProfile::query()
                ->isolatedProduction()
                ->where('profile_type', 'seller')
                ->whereKey($profileId)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                return;
            }

            $data = is_array($profile->data) ? $profile->data : [];
            $state = is_array($data[self::DATA_KEY] ?? null)
                ? $data[self::DATA_KEY]
                : [];
            $now = now()->toIso8601String();

            $state = array_merge($state, [
                'status' => $status,
                'requested_by_user_id' => $operatorUserId,
                'started_at' => $status === 'running'
                    ? $now
                    : ($state['started_at'] ?? null),
                'completed_at' => in_array($status, ['completed', 'blocked', 'failed'], true)
                    ? $now
                    : null,
                'failure_code' => $failureCode,
                'attempt_count' => $status === 'running'
                    ? ((int) ($state['attempt_count'] ?? 0) + 1)
                    : (int) ($state['attempt_count'] ?? 0),
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_customer_pii' => false,
                'contains_private_seller_floor' => false,
                'contains_raw_conversation' => false,
            ]);

            $data[self::DATA_KEY] = $state;
            $profile->forceFill(['data' => $data])->saveQuietly();
        }, 3);
    }

    private function productionBot(): ?AiBot
    {
        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('business_sector', 'real_estate')
            ->where('whatsapp_instance', RealEstateIsolationService::INSTANCE)
            ->first();

        return app(RealEstateIsolationService::class)->supportsProductionBot($bot)
            ? $bot
            : null;
    }

    private function assertOperator(int $operatorUserId): void
    {
        $operator = User::query()->find($operatorUserId);

        abort_unless(
            $operator
            && (int) $operator->id === RealEstateIsolationService::USER_ID
            && $operator->activeOrganizations()
                ->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)
                ->exists()
            && $operator->canManageOrganization(RealEstateIsolationService::ORGANIZATION_ID),
            403,
        );
    }

    private function safeState(int $profileId, array $state): array
    {
        return [
            'profile_id' => $profileId,
            'status' => $state['status'] ?? null,
            'requested_at' => $state['requested_at'] ?? null,
            'failure_code' => $state['failure_code'] ?? null,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
        ];
    }

    private function result(string $status, int $profileId, ?string $failureCode = null): array
    {
        return [
            'status' => $status,
            'profile_id' => $profileId,
            'failure_code' => $failureCode,
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'dedicated_openai_key_only' => true,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
        ];
    }

    /** @return array{putenv:string|false,env_exists:bool,env:mixed,server_exists:bool,server:mixed} */
    private function captureEnvironment(string $key): array
    {
        return [
            'putenv' => getenv($key),
            'env_exists' => array_key_exists($key, $_ENV),
            'env' => $_ENV[$key] ?? null,
            'server_exists' => array_key_exists($key, $_SERVER),
            'server' => $_SERVER[$key] ?? null,
        ];
    }

    private function enableEnvironment(string $key): void
    {
        putenv($key.'=true');
        $_ENV[$key] = 'true';
        $_SERVER[$key] = 'true';
    }

    private function restoreEnvironment(string $key, array $previous): void
    {
        if ($previous['putenv'] === false) {
            putenv($key);
        } else {
            putenv($key.'='.$previous['putenv']);
        }

        if ($previous['env_exists']) {
            $_ENV[$key] = $previous['env'];
        } else {
            unset($_ENV[$key]);
        }

        if ($previous['server_exists']) {
            $_SERVER[$key] = $previous['server'];
        } else {
            unset($_SERVER[$key]);
        }
    }
}
