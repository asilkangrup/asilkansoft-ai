<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Collection;

class RealEstateInvestorShortlistService
{
    public function buildForSeller(RealEstateProfile $seller): array
    {
        if (
            $seller->profile_type !== 'seller'
            || ! $seller->belongsToIsolatedProductionScope()
        ) {
            return [];
        }

        $sellerData = is_array($seller->data) ? $seller->data : [];
        $matches = is_array($sellerData['opportunity_matches'] ?? null)
            ? $sellerData['opportunity_matches']
            : [];

        $rows = collect($matches)
            ->filter('is_array')
            ->map(fn (array $match): ?array => $this->row($seller, $match))
            ->filter()
            ->unique('investor_profile_id')
            ->sortByDesc('match_score')
            ->values();

        return [
            'seller_profile_id' => $seller->id,
            'property_label' => $this->propertyLabel($sellerData),
            'candidate_count' => $rows->count(),
            'ready_to_call_count' => $rows->where('readiness', 'ready_to_call')->count(),
            'candidates' => $rows->all(),
            'guardrails' => [
                'automatic_outbound_allowed' => false,
                'automatic_follow_up_allowed' => false,
                'operator_review_required' => true,
                'seller_confidential_floor_exposed' => false,
                'seller_urgency_exposed' => false,
                'cross_tenant_candidates_allowed' => false,
            ],
        ];
    }

    private function row(RealEstateProfile $seller, array $match): ?array
    {
        $sellerProfileId = $this->positiveInt($match['seller_profile_id'] ?? null);

        if ($sellerProfileId !== null && $sellerProfileId !== (int) $seller->id) {
            return null;
        }

        $candidateId = $this->positiveInt(
            $match['candidate_profile_id'] ?? $match['investor_profile_id'] ?? null
        );

        if ($candidateId === null || $candidateId === (int) $seller->id) {
            return null;
        }

        $investor = RealEstateProfile::query()
            ->isolatedProduction()
            ->whereIn('profile_type', ['investor', 'buyer'])
            ->with('conversation')
            ->whereKey($candidateId)
            ->first();

        if (! $investor || ! $investor->belongsToIsolatedProductionScope()) {
            return null;
        }

        $storedInvestorId = $this->positiveInt($match['investor_profile_id'] ?? null);
        if ($storedInvestorId !== null && $storedInvestorId !== (int) $investor->id) {
            return null;
        }

        $storedConversationId = $this->positiveInt(
            $match['candidate_conversation_id']
                ?? $match['investor_conversation_id']
                ?? null
        );
        if (
            $storedConversationId !== null
            && $storedConversationId !== (int) $investor->conversation_control_id
        ) {
            return null;
        }

        $data = is_array($investor->data) ? $investor->data : [];
        $mandate = app(RealEstateInvestorMandateService::class)
            ->summaryForProfile($investor);
        $score = max(0, min(100, (int) ($match['match_score'] ?? 0)));
        $coreReady = (bool) ($mandate['core_ready'] ?? false);
        $readiness = $this->readiness($score, $coreReady);

        return [
            'investor_profile_id' => $investor->id,
            'investor_conversation_id' => $investor->conversation_control_id,
            'name' => $investor->conversation?->customer_name ?: 'İsimsiz yatırımcı',
            'phone' => $investor->conversation?->whatsapp_number,
            'crm_url' => $investor->conversation
                ? url('/admin/emlak-musteri-detay?customer='.$investor->conversation->id)
                : null,
            'match_score' => $score,
            'grade' => $this->safeEnum(
                $match['grade'] ?? null,
                ['strong', 'good', 'possible'],
                'possible'
            ),
            'readiness' => $readiness,
            'readiness_label' => $this->readinessLabel($readiness),
            'recommended_operator_action' => $this->recommendedAction($readiness),
            'mandate_strength' => max(0, min(100, (int) ($mandate['strength_score'] ?? 0))),
            'mandate_status' => $this->safeEnum(
                $mandate['status'] ?? null,
                ['strong', 'qualified', 'building', 'early'],
                'early'
            ),
            'mandate_core_ready' => $coreReady,
            'recommended_next_question' => is_string($mandate['recommended_next_question'] ?? null)
                ? trim((string) $mandate['recommended_next_question'])
                : null,
            'criteria' => [
                'property_type' => $this->text($data['property_type'] ?? null),
                'city' => $this->text($data['city'] ?? null),
                'district' => $this->text($data['district'] ?? null),
                'budget_min' => $this->positiveNumber($data['budget_min'] ?? null),
                'budget_max' => $this->positiveNumber($data['budget_max'] ?? null),
                'financing' => $this->text($data['financing'] ?? null),
                'investment_goal' => $this->text($data['investment_goal'] ?? null),
                'timeline' => $this->text($data['timeline'] ?? null),
                'area_min_sqm' => $this->positiveNumber($data['area_min_sqm'] ?? null),
                'area_max_sqm' => $this->positiveNumber($data['area_max_sqm'] ?? null),
                'location_flexibility' => $this->text($data['location_flexibility'] ?? null),
                'accepts_shared_title' => is_bool($data['accepts_shared_title'] ?? null)
                    ? $data['accepts_shared_title']
                    : null,
                'target_discount_percent' => $this->boundedPercent(
                    $data['target_discount_percent'] ?? null
                ),
            ],
            'reasons' => $this->safeTextList($match['reasons'] ?? []),
            'risks' => $this->safeTextList($match['risks'] ?? []),
            'criteria_checks' => $this->safeCriteriaChecks($match['criteria_checks'] ?? []),
            'guardrails' => [
                'automatic_outbound_allowed' => false,
                'automatic_follow_up_allowed' => false,
                'operator_review_required' => true,
                'seller_confidential_floor_exposed' => false,
                'seller_urgency_exposed' => false,
            ],
        ];
    }

    private function readiness(int $score, bool $coreReady): string
    {
        if (! $coreReady) {
            return 'criteria_incomplete';
        }

        return $score >= 72 ? 'ready_to_call' : 'review_match';
    }

    private function readinessLabel(string $readiness): string
    {
        return match ($readiness) {
            'ready_to_call' => 'Aramaya hazır',
            'criteria_incomplete' => 'Kriter teyidi gerekli',
            default => 'Eşleşmeyi incele',
        };
    }

    private function recommendedAction(string $readiness): string
    {
        return match ($readiness) {
            'ready_to_call' => 'Yatırımcıyı operatör olarak ara; dosyayı ve fiyat bandını insan kontrolünde sun.',
            'criteria_incomplete' => 'Yatırımcı kriterlerindeki eksik yüksek değerli alanı netleştir; otomatik takip veya mesaj gönderme.',
            default => 'Eşleşme nedenlerini ve riskleri kontrol et; uygun görürsen yatırımcıyı manuel ara.',
        };
    }

    private function propertyLabel(array $data): string
    {
        $parts = collect([
            $this->text($data['city'] ?? null),
            $this->text($data['district'] ?? null),
            $this->text($data['neighborhood'] ?? null),
            $this->text($data['property_type'] ?? null),
        ])->filter()->values();

        return $parts->isEmpty() ? 'Taşınmaz bilgisi eksik' : $parts->implode(' · ');
    }

    private function safeTextList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter('is_string')
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->take(8)
            ->values()
            ->all();
    }

    private function safeCriteriaChecks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $allowed = [
            'location_flexibility', 'district_match', 'seller_area_sqm',
            'area_min_sqm', 'area_max_sqm', 'area_match',
            'seller_shared_title', 'accepts_shared_title',
            'budget_coverage_percent', 'target_discount_percent',
            'observed_discount_percent', 'hard_filters_passed',
        ];

        return collect($value)
            ->only($allowed)
            ->map(fn (mixed $item): mixed => is_scalar($item) || $item === null ? $item : null)
            ->all();
    }

    private function safeEnum(mixed $value, array $allowed, string $default): string
    {
        return is_string($value) && in_array($value, $allowed, true)
            ? $value
            : $default;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private function boundedPercent(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return $value >= 0 && $value <= 60 ? $value : null;
    }
}
