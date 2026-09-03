<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RealEstateOperatorMediaAnalysisService
{
    private const DATA_KEY = 'operator_media_analysis';

    private const LOCK_SECONDS = 360;

    /**
     * Privacy-safe queue for operator-triggered image/PDF analysis.
     *
     * The normal WhatsApp ingress remains CRM-only. Nothing in this method
     * starts vision/OCR, sends a message or schedules a customer follow-up.
     */
    public function queueItems(int $limit = 100): Collection
    {
        $limit = max(1, min(250, $limit));

        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->where('profile_type', 'seller')
            ->get()
            ->filter(fn (RealEstateProfile $profile): bool =>
                $profile->conversation !== null
                && app(RealEstateIsolationService::class)->supportsConversation($profile->conversation)
            )
            ->keyBy(fn (RealEstateProfile $profile): string => (string) $profile->conversation?->session_id);

        if ($profiles->isEmpty()) {
            return collect();
        }

        return ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->whereIn('message_type', ['image', 'document'])
            ->whereNotNull('whatsapp_message_id')
            ->whereIn('session_id', $profiles->keys()->all())
            ->latest('id')
            ->get()
            ->map(function (ChatMessage $message) use ($profiles): ?array {
                $profile = $profiles->get((string) $message->session_id);

                if (! $profile) {
                    return null;
                }

                $finding = $this->findingFor($profile, (string) $message->whatsapp_message_id);
                $state = $this->stateFor($profile, $message);
                $status = trim((string) ($state['status'] ?? '')) ?: null;
                $analyzed = $this->isAnalyzedFinding($finding)
                    || $status === 'completed';

                if ($analyzed && ! in_array($status, ['queued', 'running', 'failed', 'blocked'], true)) {
                    return null;
                }

                $decision = is_array(data_get($profile->data, 'decision_intelligence'))
                    ? data_get($profile->data, 'decision_intelligence')
                    : [];
                $leadScore = max(
                    (int) ($decision['lead_score'] ?? 0),
                    (int) ($profile->conversation?->lead_score ?? 0),
                );
                $base = $message->message_type === 'document' ? 260 : 220;

                if (in_array($status, ['queued', 'running'], true)) {
                    $base += 40;
                }

                return [
                    'chat_message_id' => (int) $message->id,
                    'profile_id' => (int) $profile->id,
                    'conversation_control_id' => (int) $profile->conversation_control_id,
                    'media_type' => (string) $message->message_type,
                    'mime_type' => app(RealEstateMediaSafetyService::class)->normalizeMime(
                        (string) $message->media_mime_type
                    ) ?: null,
                    'priority_score' => min(500, $base + $leadScore),
                    'lead_score' => $leadScore,
                    'lead_temperature' => (string) (
                        $decision['lead_temperature']
                        ?? $profile->conversation?->lead_temperature
                        ?? 'unknown'
                    ),
                    'analysis_status' => $status,
                    'already_analyzed' => $analyzed,
                    'requested_at' => $state['requested_at'] ?? null,
                    'completed_at' => $state['completed_at'] ?? null,
                    'failure_code' => $state['failure_code'] ?? null,
                    'automatic_analysis_allowed' => false,
                    'automatic_outbound_allowed' => false,
                    'customer_follow_up_allowed' => false,
                    'contains_customer_pii' => false,
                    'contains_private_seller_floor' => false,
                    'contains_raw_conversation' => false,
                    'contains_media_content' => false,
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
                'needs_analysis' => $items->where('already_analyzed', false)->count(),
                'images' => $items->where('media_type', 'image')->count(),
                'documents' => $items->where('media_type', 'document')->count(),
                'high_priority' => $items->where('priority_score', '>=', 320)->count(),
                'queued' => $items->where('analysis_status', 'queued')->count(),
                'running' => $items->where('analysis_status', 'running')->count(),
                'failed' => $items->where('analysis_status', 'failed')->count(),
                'blocked' => $items->where('analysis_status', 'blocked')->count(),
            ],
            'bot' => [
                'identity_valid' => $bot !== null,
                'ai_enabled' => (bool) ($bot?->ai_enabled ?? false),
                'dedicated_openai_key_configured' => filled($bot?->openai_api_key),
                'dedicated_openai_key_only' => true,
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
            'automatic_media_analysis_allowed' => false,
            'operator_trigger_required' => true,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
            'contains_media_content' => false,
        ];
    }

    public function request(int $chatMessageId, int $operatorUserId): array
    {
        $this->assertOperator($operatorUserId);

        return DB::transaction(function () use ($chatMessageId, $operatorUserId): array {
            [$message, $conversation, $profile] = $this->lockedTarget($chatMessageId);
            $finding = $this->findingFor($profile, (string) $message->whatsapp_message_id);

            if ($this->isAnalyzedFinding($finding)) {
                return $this->result('completed', $message, $profile);
            }

            $states = $this->states($profile);
            $key = $this->stateKey($message);
            $previous = is_array($states[$key] ?? null) ? $states[$key] : [];
            $now = now()->toIso8601String();

            $states[$key] = [
                'chat_message_id' => (int) $message->id,
                'status' => 'queued',
                'media_type' => (string) $message->message_type,
                'requested_at' => $now,
                'requested_by_user_id' => $operatorUserId,
                'started_at' => null,
                'completed_at' => null,
                'failure_code' => null,
                'attempt_count' => (int) ($previous['attempt_count'] ?? 0),
                'automatic_analysis_allowed' => false,
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_customer_pii' => false,
                'contains_private_seller_floor' => false,
                'contains_raw_conversation' => false,
                'contains_media_content' => false,
            ];

            $this->saveStates($profile, $states);

            return $this->safeState($message, $profile, $states[$key]);
        }, 3);
    }

    /**
     * Run exactly one operator-requested image/PDF analysis pass.
     *
     * CRM-only placeholder findings are removed only for the duration of this
     * explicit pass because the generic media analyzer is idempotent by
     * WhatsApp message id. If the analysis fails, the placeholder is restored.
     */
    public function run(int $chatMessageId, int $operatorUserId): array
    {
        $this->assertOperator($operatorUserId);

        $lock = Cache::lock(
            'real-estate:operator-media-analysis:'
                .RealEstateIsolationService::ORGANIZATION_ID.':'
                .RealEstateIsolationService::BOT_ID.':'
                .$chatMessageId,
            self::LOCK_SECONDS,
        );

        if (! $lock->get()) {
            return $this->resultById('busy', $chatMessageId, 'analysis_already_running');
        }

        $placeholder = null;
        $profileId = null;
        $message = null;

        try {
            [$message, $conversation, $profile] = $this->target($chatMessageId);
            $profileId = (int) $profile->id;
            $bot = $this->productionBot();

            if (
                ! app(RealEstateIsolationService::class)->supportsConversation($conversation)
                || ! $bot
                || ! (bool) $bot->ai_enabled
                || blank($bot->openai_api_key)
                || (bool) $bot->follow_up_enabled
                || (bool) $bot->second_follow_up_enabled
            ) {
                $this->markState($profile, $message, $operatorUserId, 'blocked', 'isolation_or_bot_guard_failed');

                return $this->result('blocked', $message, $profile, 'isolation_or_bot_guard_failed');
            }

            $existing = $this->findingFor($profile, (string) $message->whatsapp_message_id);

            if ($this->isAnalyzedFinding($existing)) {
                $this->markState($profile, $message, $operatorUserId, 'completed');

                return $this->result('completed', $message, $profile);
            }

            $this->markState($profile, $message, $operatorUserId, 'running');
            $placeholder = $this->removeCrmOnlyPlaceholder($profile, $message);
            $profile->refresh();

            $analysis = app(RealEstateMediaAnalysisService::class)->process(
                conversation: $conversation,
                instanceName: RealEstateIsolationService::INSTANCE,
                mediaContext: [
                    'type' => (string) $message->message_type,
                    'url' => $message->media_url,
                    'mime_type' => $message->media_mime_type,
                    'filename' => $message->media_filename,
                    'caption' => $message->media_caption,
                    'size' => $message->media_size,
                    'message_id' => $message->whatsapp_message_id,
                    'message_envelope' => [
                        'key' => ['id' => $message->whatsapp_message_id],
                    ],
                ],
            );

            if (! is_array($analysis) || $analysis === []) {
                $this->restorePlaceholder($profile, $message, $placeholder);
                $this->markState($profile, $message, $operatorUserId, 'blocked', 'analysis_not_generated');

                return $this->result('blocked', $message, $profile, 'analysis_not_generated');
            }

            $this->ensureAnalyzedFinding($profile, $message, $analysis);
            $this->recomputeDeterministicIntelligence($conversation);
            $profile->refresh();
            $this->markState($profile, $message, $operatorUserId, 'completed');

            return [
                ...$this->result('completed', $message, $profile),
                'media_category' => $this->safeToken($analysis['media_category'] ?? null),
                'confidence_score' => max(0, min(100, (int) ($analysis['confidence_score'] ?? 0))),
            ];
        } catch (Throwable $exception) {
            if ($profileId !== null && $message instanceof ChatMessage) {
                $profile = RealEstateProfile::query()
                    ->isolatedProduction()
                    ->whereKey($profileId)
                    ->first();

                if ($profile) {
                    $this->restorePlaceholder($profile, $message, $placeholder);
                    $this->markState(
                        $profile,
                        $message,
                        $operatorUserId,
                        'failed',
                        'analysis_execution_failed:'.class_basename($exception),
                    );
                }
            }

            report($exception);

            return $this->resultById(
                'failed',
                $chatMessageId,
                'analysis_execution_failed:'.class_basename($exception),
            );
        } finally {
            $lock->release();
        }
    }

    /** @return array{0:ChatMessage,1:ConversationControl,2:RealEstateProfile} */
    private function target(int $chatMessageId): array
    {
        $message = ChatMessage::query()
            ->whereKey($chatMessageId)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->whereIn('message_type', ['image', 'document'])
            ->whereNotNull('whatsapp_message_id')
            ->firstOrFail();

        $conversation = ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $message->session_id)
            ->firstOrFail();

        abort_unless(app(RealEstateIsolationService::class)->supportsConversation($conversation), 404);

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->where('profile_type', 'seller')
            ->firstOrFail();

        return [$message, $conversation, $profile];
    }

    /** @return array{0:ChatMessage,1:ConversationControl,2:RealEstateProfile} */
    private function lockedTarget(int $chatMessageId): array
    {
        [$message, $conversation, $profile] = $this->target($chatMessageId);

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->whereKey($profile->id)
            ->where('profile_type', 'seller')
            ->lockForUpdate()
            ->firstOrFail();

        return [$message, $conversation, $profile];
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

    private function removeCrmOnlyPlaceholder(
        RealEstateProfile $profile,
        ChatMessage $message,
    ): ?array {
        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [];
        $messageId = trim((string) $message->whatsapp_message_id);
        $placeholder = null;
        $kept = [];

        foreach ($findings as $finding) {
            $sameMessage = is_array($finding)
                && trim((string) ($finding['message_id'] ?? '')) === $messageId;
            $crmOnly = $sameMessage
                && ($finding['source'] ?? null) === 'crm_only_media_registration'
                && ! (bool) ($finding['vision_analyzed'] ?? false)
                && blank($finding['analyzed_at'] ?? null);

            if ($crmOnly && $placeholder === null) {
                $placeholder = $finding;
                continue;
            }

            $kept[] = $finding;
        }

        if ($placeholder !== null) {
            $data['media_findings'] = array_values($kept);
            $profile->forceFill(['data' => $data])->saveQuietly();
        }

        return $placeholder;
    }

    private function restorePlaceholder(
        RealEstateProfile $profile,
        ChatMessage $message,
        ?array $placeholder,
    ): void {
        if ($placeholder === null) {
            return;
        }

        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [];
        $messageId = trim((string) $message->whatsapp_message_id);

        foreach ($findings as $finding) {
            if (
                is_array($finding)
                && trim((string) ($finding['message_id'] ?? '')) === $messageId
            ) {
                return;
            }
        }

        $findings[] = $placeholder;
        $data['media_findings'] = array_slice($findings, -100);
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function ensureAnalyzedFinding(
        RealEstateProfile $profile,
        ChatMessage $message,
        array $analysis,
    ): void {
        $profile->refresh();
        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [];
        $messageId = trim((string) $message->whatsapp_message_id);
        $analysis['message_id'] = $messageId;
        $analysis['analyzed_at'] = $analysis['analyzed_at'] ?? now()->toIso8601String();
        $analysis['vision_analyzed'] = true;
        $analysis['legal_verification'] = (bool) ($analysis['legal_verification'] ?? false);
        $analysis['source'] = $analysis['source'] ?? 'operator_media_analysis';

        $findings = collect($findings)
            ->reject(fn ($finding): bool =>
                is_array($finding)
                && trim((string) ($finding['message_id'] ?? '')) === $messageId
            )
            ->values()
            ->all();
        $findings[] = $analysis;
        $data['media_findings'] = array_slice($findings, -100);
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function recomputeDeterministicIntelligence(ConversationControl $conversation): void
    {
        app(RealEstateEvidenceReconciliationService::class)->process($conversation);
        app(RealEstateVerificationService::class)->process($conversation);
        app(RealEstateDecisionService::class)->process($conversation);
        app(RealEstateValuationDecisionGuardService::class)->process($conversation);
        app(RealEstateVerificationDecisionGuardService::class)->process($conversation);
        app(RealEstateMatchService::class)->process($conversation);
        app(RealEstateMatchValuationFreshnessFilterService::class)->process($conversation);
        app(RealEstateMatchVerificationFilterService::class)->process($conversation);

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->where('profile_type', 'seller')
            ->first();

        if ($profile) {
            app(RealEstateSellerOfferPacketService::class)->sync($profile);
        }
    }

    private function findingFor(RealEstateProfile $profile, string $messageId): ?array
    {
        $messageId = trim($messageId);

        if ($messageId === '') {
            return null;
        }

        $findings = is_array(data_get($profile->data, 'media_findings'))
            ? data_get($profile->data, 'media_findings')
            : [];

        foreach ($findings as $finding) {
            if (
                is_array($finding)
                && trim((string) ($finding['message_id'] ?? '')) === $messageId
            ) {
                return $finding;
            }
        }

        return null;
    }

    private function isAnalyzedFinding(?array $finding): bool
    {
        return $finding !== null
            && (
                (bool) ($finding['vision_analyzed'] ?? false)
                || filled($finding['analyzed_at'] ?? null)
            );
    }

    private function states(RealEstateProfile $profile): array
    {
        $states = data_get($profile->data, self::DATA_KEY);

        return is_array($states) ? $states : [];
    }

    private function stateFor(RealEstateProfile $profile, ChatMessage $message): array
    {
        $states = $this->states($profile);
        $state = $states[$this->stateKey($message)] ?? [];

        return is_array($state) ? $state : [];
    }

    private function stateKey(ChatMessage $message): string
    {
        return hash('sha256', implode('|', [
            RealEstateIsolationService::USER_ID,
            RealEstateIsolationService::ORGANIZATION_ID,
            RealEstateIsolationService::BOT_ID,
            (int) $message->id,
            trim((string) $message->whatsapp_message_id),
        ]));
    }

    private function saveStates(RealEstateProfile $profile, array $states): void
    {
        if (count($states) > 100) {
            $states = array_slice($states, -100, null, true);
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $data[self::DATA_KEY] = $states;
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function markState(
        RealEstateProfile $profile,
        ChatMessage $message,
        int $operatorUserId,
        string $status,
        ?string $failureCode = null,
    ): void {
        DB::transaction(function () use ($profile, $message, $operatorUserId, $status, $failureCode): void {
            $locked = RealEstateProfile::query()
                ->isolatedProduction()
                ->whereKey($profile->id)
                ->where('profile_type', 'seller')
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return;
            }

            $states = $this->states($locked);
            $key = $this->stateKey($message);
            $state = is_array($states[$key] ?? null) ? $states[$key] : [];
            $now = now()->toIso8601String();

            $states[$key] = array_merge($state, [
                'chat_message_id' => (int) $message->id,
                'status' => $status,
                'media_type' => (string) $message->message_type,
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
                'automatic_analysis_allowed' => false,
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
                'contains_customer_pii' => false,
                'contains_private_seller_floor' => false,
                'contains_raw_conversation' => false,
                'contains_media_content' => false,
            ]);

            $this->saveStates($locked, $states);
        }, 3);
    }

    private function safeState(
        ChatMessage $message,
        RealEstateProfile $profile,
        array $state,
    ): array {
        return [
            'status' => $state['status'] ?? null,
            'chat_message_id' => (int) $message->id,
            'profile_id' => (int) $profile->id,
            'media_type' => (string) $message->message_type,
            'requested_at' => $state['requested_at'] ?? null,
            'failure_code' => $state['failure_code'] ?? null,
            'dedicated_openai_key_only' => true,
            'automatic_analysis_allowed' => false,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
            'contains_media_content' => false,
        ];
    }

    private function result(
        string $status,
        ChatMessage $message,
        RealEstateProfile $profile,
        ?string $failureCode = null,
    ): array {
        return [
            ...$this->resultById($status, (int) $message->id, $failureCode),
            'profile_id' => (int) $profile->id,
            'media_type' => (string) $message->message_type,
        ];
    }

    private function resultById(
        string $status,
        int $chatMessageId,
        ?string $failureCode = null,
    ): array {
        return [
            'status' => $status,
            'chat_message_id' => $chatMessageId,
            'failure_code' => $failureCode,
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'dedicated_openai_key_only' => true,
            'automatic_analysis_allowed' => false,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'contains_raw_conversation' => false,
            'contains_media_content' => false,
        ];
    }

    private function safeToken(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = strtolower(trim((string) $value));

        return $value !== '' && preg_match('/^[a-z0-9_-]{1,48}$/', $value) === 1
            ? $value
            : null;
    }
}
