<?php

namespace App\Services;

use App\Models\RealEstateCaseEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RealEstateCaseLifecycleService
{
    public function sync(RealEstateProfile $profile): ?RealEstateCaseEvent
    {
        if (! Schema::hasTable('real_estate_case_events')) {
            return null;
        }

        return DB::transaction(function () use ($profile): ?RealEstateCaseEvent {
            $lockedProfile = RealEstateProfile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedProfile || ! $this->supports($lockedProfile)) {
                return null;
            }

            $state = $this->state($lockedProfile);
            $stateHash = hash('sha256', $this->canonicalJson($state));

            $previous = RealEstateCaseEvent::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('real_estate_profile_id', $lockedProfile->id)
                ->latest('id')
                ->first();

            $previousMetadata = is_array($previous?->metadata)
                ? $previous->metadata
                : [];

            if (($previousMetadata['state_hash'] ?? null) === $stateHash) {
                return $previous;
            }

            $eventType = $this->eventType($previous, $state);
            $eventKey = hash('sha256', implode('|', [
                (string) $lockedProfile->id,
                $stateHash,
                (string) ($previous?->id ?? 0),
                $eventType,
            ]));

            return RealEstateCaseEvent::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => RealEstateIsolationService::USER_ID,
                    'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                    'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                    'conversation_control_id' => $lockedProfile->conversation_control_id,
                    'real_estate_profile_id' => $lockedProfile->id,
                    'event_type' => $eventType,
                    'profile_type' => $state['profile_type'],
                    'stage' => $state['stage'],
                    'lead_score' => $state['lead_score'],
                    'lead_temperature' => $state['lead_temperature'],
                    'ready_for_valuation' => $state['ready_for_valuation'],
                    'ready_for_match' => $state['ready_for_match'],
                    'valuation_present' => $state['valuation_present'],
                    'match_count' => $state['match_count'],
                    'strongest_match_grade' => $state['strongest_match_grade'],
                    'metadata' => [
                        'state_hash' => $stateHash,
                        'previous_event_id' => $previous?->id,
                        'previous_state' => $previous
                            ? $this->safePreviousState($previous)
                            : null,
                        'guardrails' => [
                            'contains_customer_message_text' => false,
                            'contains_phone_or_email' => false,
                            'contains_document_or_audio_content' => false,
                            'contains_private_seller_floor' => false,
                            'schedules_follow_up' => false,
                            'sends_outbound_message' => false,
                        ],
                    ],
                    'occurred_at' => now(),
                ]
            );
        });
    }

    public function latestForProfile(RealEstateProfile $profile): ?RealEstateCaseEvent
    {
        if (! $this->supports($profile) || ! Schema::hasTable('real_estate_case_events')) {
            return null;
        }

        return RealEstateCaseEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('real_estate_profile_id', $profile->id)
            ->latest('id')
            ->first();
    }

    private function supports(RealEstateProfile $profile): bool
    {
        if (
            (int) $profile->user_id !== RealEstateIsolationService::USER_ID
            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID
        ) {
            return false;
        }

        $conversation = $profile->conversation()->first();

        return app(RealEstateIsolationService::class)
            ->supportsConversation($conversation);
    }

    private function state(RealEstateProfile $profile): array
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $decision = is_array($data['decision_intelligence'] ?? null)
            ? $data['decision_intelligence']
            : [];
        $matchSummary = is_array($data['opportunity_match_summary'] ?? null)
            ? $data['opportunity_match_summary']
            : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];

        $profileType = in_array(
            $profile->profile_type,
            ['seller', 'investor', 'buyer', 'general'],
            true
        ) ? $profile->profile_type : 'general';

        $stage = $this->allowedString(
            $decision['stage'] ?? null,
            ['new', 'discovery', 'qualified', 'ready']
        );
        $temperature = $this->allowedString(
            $decision['lead_temperature'] ?? null,
            ['cold', 'warm', 'hot']
        );
        $leadScore = is_numeric($decision['lead_score'] ?? null)
            ? max(0, min(100, (int) $decision['lead_score']))
            : null;
        $matchCount = is_numeric($matchSummary['count'] ?? null)
            ? max(0, min(65535, (int) $matchSummary['count']))
            : 0;
        $strongestGrade = $this->allowedString(
            $matchSummary['strongest_grade'] ?? null,
            ['possible', 'good', 'strong']
        );

        return [
            'profile_type' => $profileType,
            'stage' => $stage,
            'lead_score' => $leadScore,
            'lead_temperature' => $temperature,
            'ready_for_valuation' => (bool) ($decision['ready_for_valuation'] ?? false),
            'ready_for_match' => (bool) ($decision['ready_for_match'] ?? false),
            'valuation_present' => $this->valuationPresent($valuation),
            'match_count' => $matchCount,
            'strongest_match_grade' => $strongestGrade,
        ];
    }

    private function eventType(?RealEstateCaseEvent $previous, array $state): string
    {
        if (! $previous) {
            return 'case_opened';
        }

        if (! $previous->ready_for_match && $state['ready_for_match']) {
            return 'match_ready';
        }

        if ($previous->ready_for_match && ! $state['ready_for_match']) {
            return 'match_blocked';
        }

        if (! $previous->valuation_present && $state['valuation_present']) {
            return 'valuation_ready';
        }

        if ((string) ($previous->stage ?? '') !== (string) ($state['stage'] ?? '')) {
            return 'stage_changed';
        }

        if (
            (string) ($previous->lead_temperature ?? '')
            !== (string) ($state['lead_temperature'] ?? '')
        ) {
            return 'temperature_changed';
        }

        if ((int) $previous->match_count !== (int) $state['match_count']) {
            return 'match_candidates_changed';
        }

        return 'state_changed';
    }

    private function safePreviousState(RealEstateCaseEvent $event): array
    {
        return [
            'stage' => $event->stage,
            'lead_score' => $event->lead_score,
            'lead_temperature' => $event->lead_temperature,
            'ready_for_valuation' => (bool) $event->ready_for_valuation,
            'ready_for_match' => (bool) $event->ready_for_match,
            'valuation_present' => (bool) $event->valuation_present,
            'match_count' => (int) $event->match_count,
            'strongest_match_grade' => $event->strongest_match_grade,
        ];
    }

    private function valuationPresent(array $valuation): bool
    {
        foreach ([
            'market_min',
            'market_max',
            'quick_sale_min',
            'quick_sale_max',
            'investor_buy_min',
            'investor_buy_max',
        ] as $field) {
            if (is_numeric($valuation[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function allowedString(mixed $value, array $allowed): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return in_array($value, $allowed, true)
            ? $value
            : null;
    }

    private function canonicalJson(array $state): string
    {
        return (string) json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }
}
