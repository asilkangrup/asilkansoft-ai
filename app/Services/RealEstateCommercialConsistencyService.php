<?php

namespace App\Services;

use App\Models\ConversationControl;

class RealEstateCommercialConsistencyService
{
    private const DATA_KEY = 'commercial_consistency_intelligence';

    public function reconcile(
        ConversationControl $conversation,
        string $profileType,
        array $data,
    ): array {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return $data;
        }

        $previous = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];
        $summary = $this->assess($profileType, $data);

        if ($this->comparable($previous) !== $summary) {
            $summary['updated_at'] = now()->toIso8601String();
        } elseif (isset($previous['updated_at'])) {
            $summary['updated_at'] = $previous['updated_at'];
        }

        $data[self::DATA_KEY] = $summary;

        return $data;
    }

    public function assess(string $profileType, array $data): array
    {
        $conflicts = $this->conflicts($profileType, $data);

        return [
            'status' => $conflicts === [] ? 'consistent' : 'confirmation_required',
            'profile_type' => $profileType,
            'conflict_count' => count($conflicts),
            'conflict_codes' => $conflicts,
            'highest_priority_conflict' => $conflicts[0] ?? null,
            'confirmation_question' => $this->question($conflicts[0] ?? null),
            'guardrails' => [
                'confidential_seller_floor_included' => false,
                'raw_customer_message_included' => false,
                'inconsistent_terms_may_be_used_for_matching' => false,
                'inconsistent_terms_may_be_used_for_investor_handoff' => false,
                'follow_up_scheduling_allowed' => false,
            ],
        ];
    }

    public function summary(array $profileData): array
    {
        $summary = $profileData[self::DATA_KEY] ?? null;

        return is_array($summary) ? $summary : [];
    }

    public function isConsistent(array $profileData): bool
    {
        $summary = $this->summary($profileData);

        return $summary === []
            || ($summary['status'] ?? 'consistent') !== 'confirmation_required';
    }

    private function conflicts(string $profileType, array $data): array
    {
        $conflicts = [];

        if ($profileType === 'seller') {
            $asking = $this->positive($data['asking_price'] ?? null);
            $floor = $this->positive($data['minimum_price'] ?? null);

            if ($asking !== null && $floor !== null && $floor > $asking) {
                $conflicts[] = 'seller_floor_above_asking';
            }
        }

        if (in_array($profileType, ['investor', 'buyer'], true)) {
            $budgetMin = $this->positive($data['budget_min'] ?? null);
            $budgetMax = $this->positive($data['budget_max'] ?? null);

            if (
                $budgetMin !== null
                && $budgetMax !== null
                && $budgetMin > $budgetMax
            ) {
                $conflicts[] = 'investor_budget_range_inverted';
            }
        }

        return $conflicts;
    }

    private function question(?string $conflict): ?string
    {
        return match ($conflict) {
            'seller_floor_above_asking' => 'Satış fiyatı ile hızlı nakitte düşündüğünüz alt sınır birbiriyle çelişiyor. Güncel satış beklentinizi ve alt sınırınızı yeniden yazar mısınız?',
            'investor_budget_range_inverted' => 'Alt ve üst bütçe sınırlarınız ters görünüyor. Güncel bütçe aralığınızı tek mesajda yazar mısınız?',
            default => null,
        };
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }
}
