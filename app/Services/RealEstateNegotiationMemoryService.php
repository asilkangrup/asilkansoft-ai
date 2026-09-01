<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstateNegotiationEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;

class RealEstateNegotiationMemoryService
{
    private const SOURCE_WINDOW_MINUTES = 10;

    private const MATERIAL_CHANGE_PERCENT = 10.0;

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile) || ! Schema::hasTable('real_estate_negotiation_events')) {
            return [];
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $sourceMessage = $this->latestCustomerMessage($profile);

        // Negotiation memory is deliberately customer-sourced. Background
        // recalculations and assistant/system writes must never manufacture a
        // price position or a bargaining term.
        if (! $sourceMessage) {
            return $this->persistSummary($profile);
        }

        $data = is_array($profile->data) ? $profile->data : [];

        foreach ($this->positions($profile->profile_type, $data) as $position) {
            $this->recordPosition(
                profile: $profile,
                sourceMessage: $sourceMessage,
                eventType: $position['event_type'],
                actorRole: $position['actor_role'],
                numericValue: $position['numeric_value'],
                textValue: $position['text_value'],
                confidential: $position['confidential'],
            );
        }

        return $this->persistSummary($profile);
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile) || ! Schema::hasTable('real_estate_negotiation_events')) {
            return [];
        }

        $events = RealEstateNegotiationEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('real_estate_profile_id', $profile->id)
            ->orderBy('id')
            ->get();

        if ($events->isEmpty()) {
            return [];
        }

        $latest = $events
            ->groupBy('event_type')
            ->map(function ($group): array {
                /** @var RealEstateNegotiationEvent $event */
                $event = $group->last();
                $metadata = is_array($event->metadata) ? $event->metadata : [];

                return [
                    'value' => $event->numeric_value !== null
                        ? (float) $event->numeric_value
                        : $event->text_value,
                    'previous_value' => $event->previous_numeric_value !== null
                        ? (float) $event->previous_numeric_value
                        : $event->previous_text_value,
                    'direction' => $event->direction,
                    'change_percent' => isset($metadata['change_percent'])
                        ? (float) $metadata['change_percent']
                        : null,
                    'confidential' => (bool) ($metadata['confidential'] ?? false),
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ];
            })
            ->all();

        $materialChanges = $events->filter(function (RealEstateNegotiationEvent $event): bool {
            $metadata = is_array($event->metadata) ? $event->metadata : [];

            return (bool) ($metadata['material_change'] ?? false);
        })->count();

        return [
            'event_count' => $events->count(),
            'customer_sourced' => true,
            'latest_positions' => $latest,
            'seller_asking_trajectory_percent' => $this->trajectoryPercent($events, 'seller_asking_price'),
            'seller_floor_trajectory_percent' => $this->trajectoryPercent($events, 'seller_minimum_price'),
            'investor_budget_max_trajectory_percent' => $this->trajectoryPercent($events, 'investor_budget_max'),
            'material_change_count' => $materialChanges,
            'latest_change_at' => $events->last()?->occurred_at?->toIso8601String(),
            'guardrails' => [
                'positions_are_not_binding_offers' => true,
                'seller_minimum_price_is_confidential' => true,
                'do_not_disclose_private_floor_to_investors' => true,
                'do_not_invent_acceptance_or_counter_offer' => true,
            ],
        ];
    }

    public function promptFor(\App\Models\ConversationControl $conversation): string
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->first();

        if (! $profile) {
            return '';
        }

        $summary = $this->summaryForProfile($profile);

        if ($summary === []) {
            return '';
        }

        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
[INTERNAL REAL ESTATE NEGOTIATION MEMORY]
Bu blok yalnız müşterinin kendi mesajlarından çıkarılan fiyat/şart değişimlerinin kalıcı CRM hafızasıdır. Bir fiyat pozisyonunu üçüncü tarafın bağlayıcı teklifi gibi sunma; kabul, ret veya karşı teklif kaydı yoksa bunları uydurma. seller_minimum_price gizli pazarlık tabanıdır: satıcıyla kendi görüşmesinde bağlam olarak kullanılabilir ancak yatırımcıya/alıcıya otomatik olarak açıklanamaz. Fiyat düşüşünü veya yüksek aciliyeti müşteriye baskı kurmak için kullanma. En güncel pozisyonu esas al, eski pozisyonları tekrar sorma ve önemli değişiklik varsa profesyonel biçimde teyit et.
Pazarlık hafızası: {$json}
PROMPT;
    }

    private function recordPosition(
        RealEstateProfile $profile,
        ChatMessage $sourceMessage,
        string $eventType,
        string $actorRole,
        ?float $numericValue,
        ?string $textValue,
        bool $confidential,
    ): void {
        if ($numericValue === null && $textValue === null) {
            return;
        }

        $previous = RealEstateNegotiationEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('real_estate_profile_id', $profile->id)
            ->where('event_type', $eventType)
            ->latest('id')
            ->first();

        if ($this->samePosition($previous, $numericValue, $textValue)) {
            return;
        }

        $previousNumeric = $previous?->numeric_value !== null
            ? (float) $previous->numeric_value
            : null;
        $previousText = $previous?->text_value;
        $changePercent = $this->changePercent($previousNumeric, $numericValue);
        $direction = $this->direction(
            previousNumeric: $previousNumeric,
            numericValue: $numericValue,
            previousText: $previousText,
            textValue: $textValue,
        );

        $normalizedValue = $numericValue !== null
            ? number_format($numericValue, 2, '.', '')
            : mb_strtolower(trim((string) $textValue));
        $positionKey = hash('sha256', implode('|', [
            (string) $profile->id,
            $eventType,
            $normalizedValue,
            (string) $sourceMessage->id,
        ]));

        RealEstateNegotiationEvent::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'real_estate_profile_id' => $profile->id,
                'event_type' => $eventType,
                'position_key' => $positionKey,
            ],
            [
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'conversation_control_id' => $profile->conversation_control_id,
                'source_chat_message_id' => $sourceMessage->id,
                'actor_role' => $actorRole,
                'numeric_value' => $numericValue,
                'text_value' => $textValue,
                'previous_numeric_value' => $previousNumeric,
                'previous_text_value' => $previousText,
                'direction' => $direction,
                'metadata' => [
                    'customer_sourced' => true,
                    'confidential' => $confidential,
                    'change_percent' => $changePercent,
                    'material_change' => $changePercent !== null
                        && abs($changePercent) >= self::MATERIAL_CHANGE_PERCENT,
                    'source_message_type' => $sourceMessage->message_type ?: 'text',
                ],
                'occurred_at' => $sourceMessage->created_at ?? now(),
            ]
        );
    }

    private function positions(string $profileType, array $data): array
    {
        if ($profileType === 'seller') {
            return array_values(array_filter([
                $this->numericPosition('seller_asking_price', 'seller', $data['asking_price'] ?? null, false),
                $this->numericPosition('seller_minimum_price', 'seller', $data['minimum_price'] ?? null, true),
                $this->textPosition('seller_urgency', 'seller', $data['urgency'] ?? null, false),
            ]));
        }

        if (in_array($profileType, ['investor', 'buyer'], true)) {
            return array_values(array_filter([
                $this->numericPosition('investor_budget_min', 'investor', $data['budget_min'] ?? null, false),
                $this->numericPosition('investor_budget_max', 'investor', $data['budget_max'] ?? null, false),
                $this->textPosition('investor_financing', 'investor', $data['financing'] ?? null, false),
                $this->textPosition('investor_timeline', 'investor', $data['timeline'] ?? null, false),
                $this->textPosition('investor_risk_preference', 'investor', $data['risk_preference'] ?? null, false),
            ]));
        }

        return [];
    }

    private function numericPosition(
        string $eventType,
        string $actorRole,
        mixed $value,
        bool $confidential,
    ): ?array {
        if (! is_numeric($value) || (float) $value <= 0) {
            return null;
        }

        return [
            'event_type' => $eventType,
            'actor_role' => $actorRole,
            'numeric_value' => (float) $value,
            'text_value' => null,
            'confidential' => $confidential,
        ];
    }

    private function textPosition(
        string $eventType,
        string $actorRole,
        mixed $value,
        bool $confidential,
    ): ?array {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return [
            'event_type' => $eventType,
            'actor_role' => $actorRole,
            'numeric_value' => null,
            'text_value' => mb_substr($value, 0, 255),
            'confidential' => $confidential,
        ];
    }

    private function latestCustomerMessage(RealEstateProfile $profile): ?ChatMessage
    {
        $conversation = $profile->conversation()->first();

        if (! $conversation) {
            return null;
        }

        $message = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $conversation->session_id)
            ->where('role', 'user')
            ->where('sender_type', 'customer')
            ->latest('id')
            ->first();

        if (! $message || ! $message->created_at) {
            return null;
        }

        if ($message->created_at->lt(now()->subMinutes(self::SOURCE_WINDOW_MINUTES))) {
            return null;
        }

        return $message;
    }

    private function persistSummary(RealEstateProfile $profile): array
    {
        $summary = $this->summaryForProfile($profile);
        $data = is_array($profile->data) ? $profile->data : [];

        if ($summary === []) {
            unset($data['negotiation_memory_intelligence']);
        } else {
            $data['negotiation_memory_intelligence'] = $summary;
        }

        if ($data !== $profile->data) {
            $profile->updateQuietly(['data' => $data]);
        }

        return $summary;
    }

    private function samePosition(
        ?RealEstateNegotiationEvent $previous,
        ?float $numericValue,
        ?string $textValue,
    ): bool {
        if (! $previous) {
            return false;
        }

        if ($numericValue !== null) {
            return $previous->numeric_value !== null
                && abs(((float) $previous->numeric_value) - $numericValue) < 0.01;
        }

        return trim((string) $previous->text_value) === trim((string) $textValue);
    }

    private function direction(
        ?float $previousNumeric,
        ?float $numericValue,
        ?string $previousText,
        ?string $textValue,
    ): string {
        if ($previousNumeric === null && $previousText === null) {
            return 'initial';
        }

        if ($previousNumeric !== null && $numericValue !== null) {
            if (abs($previousNumeric - $numericValue) < 0.01) {
                return 'same';
            }

            return $numericValue > $previousNumeric ? 'up' : 'down';
        }

        return $previousText === $textValue ? 'same' : 'changed';
    }

    private function changePercent(?float $previous, ?float $current): ?float
    {
        if ($previous === null || $current === null || $previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function trajectoryPercent($events, string $eventType): ?float
    {
        $typed = $events
            ->where('event_type', $eventType)
            ->filter(fn (RealEstateNegotiationEvent $event): bool => $event->numeric_value !== null)
            ->values();

        if ($typed->count() < 2) {
            return null;
        }

        $first = (float) $typed->first()->numeric_value;
        $last = (float) $typed->last()->numeric_value;

        return $this->changePercent($first, $last);
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return (int) $profile->user_id === RealEstateIsolationService::USER_ID
            && (int) $profile->ai_bot_id === RealEstateIsolationService::BOT_ID;
    }
}
