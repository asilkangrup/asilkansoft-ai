<?php

namespace App\Services;

use App\Models\ConversationControl;

class RealEstateFactConsistencyService
{
    private const DATA_KEY = 'fact_consistency_intelligence';

    /**
     * Seller property identity facts should not silently oscillate between
     * turns. Negotiation facts such as asking/minimum price are deliberately
     * excluded because they are expected to move during a negotiation.
     */
    private const STABLE_SELLER_FIELDS = [
        'block_no',
        'parcel_no',
        'city',
        'district',
        'neighborhood',
        'property_type',
        'area_sqm',
        'title_deed_type',
        'zoning_status',
        'is_shared_title',
    ];

    /**
     * Investor/buyer mandate facts directly drive matching. A one-turn model
     * extraction error must not silently move a budget, location, area or risk
     * constraint and immediately create/remove opportunities. Genuine changes
     * are accepted when the customer explicitly marks the mandate as changed,
     * or repeats the proposed value on a later turn.
     */
    private const STABLE_INVESTOR_FIELDS = [
        'city',
        'district',
        'property_type',
        'area_min_sqm',
        'area_max_sqm',
        'budget_min',
        'budget_max',
        'financing',
        'investment_goal',
        'risk_preference',
        'timeline',
        'location_flexibility',
        'accepts_shared_title',
        'target_discount_percent',
    ];

    public function reconcile(
        ConversationControl $conversation,
        string $profileType,
        array $existing,
        array $incoming,
        string $customerMessage,
    ): array {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return $existing;
        }

        $guardedFields = $this->guardedFields($profileType);

        if ($guardedFields === []) {
            return $this->mergeNonNull($existing, $incoming);
        }

        $result = $existing;
        $previous = is_array($existing[self::DATA_KEY] ?? null)
            ? $existing[self::DATA_KEY]
            : [];
        $pending = $this->pendingByField(
            $previous['pending_conflicts'] ?? [],
            $guardedFields,
        );
        $resolvedFields = [];
        $explicitCorrection = $this->hasExplicitCorrectionMarker($customerMessage);
        $explicitMandateChange = in_array($profileType, ['investor', 'buyer'], true)
            && $this->hasExplicitInvestorChangeMarker($customerMessage);

        foreach ($incoming as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (! in_array($field, $guardedFields, true)) {
                $result[$field] = $value;
                continue;
            }

            $currentExists = array_key_exists($field, $existing)
                && $existing[$field] !== null
                && $existing[$field] !== '';

            if (! $currentExists) {
                $result[$field] = $value;
                unset($pending[$field]);
                continue;
            }

            $current = $existing[$field];

            if ($this->equivalent($current, $value)) {
                $result[$field] = $current;
                unset($pending[$field]);
                continue;
            }

            $pendingProposalConfirmed = isset($pending[$field])
                && $this->equivalent(
                    $pending[$field]['proposed_value'] ?? null,
                    $value
                );

            if (
                $explicitCorrection
                || $explicitMandateChange
                || $pendingProposalConfirmed
            ) {
                $result[$field] = $value;
                unset($pending[$field]);
                $resolvedFields[] = $field;
                continue;
            }

            $result[$field] = $current;
            $existingConflict = $pending[$field] ?? null;
            $sameProposal = is_array($existingConflict)
                && $this->equivalent(
                    $existingConflict['proposed_value'] ?? null,
                    $value
                );

            $pending[$field] = [
                'field' => $field,
                'current_value' => $this->safeValue($field, $current),
                'proposed_value' => $this->safeValue($field, $value),
                'first_seen_at' => $sameProposal
                    ? ($existingConflict['first_seen_at'] ?? now()->toIso8601String())
                    : now()->toIso8601String(),
            ];
        }

        $orderedPending = $this->orderedPending($pending, $guardedFields);
        $primary = $orderedPending[0] ?? null;
        $isInvestor = in_array($profileType, ['investor', 'buyer'], true);
        $intelligence = [
            'status' => $primary ? 'confirmation_required' : 'consistent',
            'profile_type' => $profileType,
            'pending_count' => count($orderedPending),
            'highest_priority_field' => $primary['field'] ?? null,
            'confirmation_question' => $primary
                ? $this->confirmationQuestion($primary, $profileType)
                : null,
            'pending_conflicts' => $orderedPending,
            'resolved_fields' => array_values(array_unique($resolvedFields)),
            'guardrails' => [
                'unconfirmed_property_identity_may_be_used_for_valuation' => $isInvestor,
                'unconfirmed_property_identity_may_be_used_for_matching' => $isInvestor,
                'unconfirmed_investor_mandate_may_be_used_for_matching' => ! $isInvestor,
                'unconfirmed_investor_mandate_may_be_presented_as_current' => ! $isInvestor,
                'seller_minimum_price_is_never_a_consistency_prompt_field' => true,
                'follow_up_scheduling_allowed' => false,
            ],
        ];

        if ($this->comparable($previous) !== $this->comparable($intelligence)) {
            $intelligence['updated_at'] = now()->toIso8601String();
        } elseif (isset($previous['updated_at'])) {
            $intelligence['updated_at'] = $previous['updated_at'];
        }

        $result[self::DATA_KEY] = $intelligence;

        return $result;
    }

    public function summary(array $profileData): array
    {
        $summary = $profileData[self::DATA_KEY] ?? null;

        return is_array($summary) ? $summary : [];
    }

    private function guardedFields(string $profileType): array
    {
        return match ($profileType) {
            'seller' => self::STABLE_SELLER_FIELDS,
            'investor', 'buyer' => self::STABLE_INVESTOR_FIELDS,
            default => [],
        };
    }

    private function mergeNonNull(array $existing, array $incoming): array
    {
        foreach ($incoming as $field => $value) {
            if ($value !== null) {
                $existing[$field] = $value;
            }
        }

        return $existing;
    }

    private function pendingByField(mixed $pending, array $guardedFields): array
    {
        if (! is_array($pending)) {
            return [];
        }

        $result = [];

        foreach ($pending as $item) {
            if (! is_array($item)) {
                continue;
            }

            $field = trim((string) ($item['field'] ?? ''));

            if (! in_array($field, $guardedFields, true)) {
                continue;
            }

            $result[$field] = $item;
        }

        return $result;
    }

    private function orderedPending(array $pending, array $guardedFields): array
    {
        $result = [];

        foreach ($guardedFields as $field) {
            if (isset($pending[$field])) {
                $result[] = $pending[$field];
            }
        }

        return $result;
    }

    private function hasExplicitCorrectionMarker(string $message): bool
    {
        $normalized = $this->normalizeText($message);

        return preg_match(
            '/\b(hayir|yanlis|duzelt|duzeltiyorum|aslinda|pardon|degil|kastettim|dogrusu|guncelle|guncelliyorum)\b/u',
            $normalized
        ) === 1;
    }

    private function hasExplicitInvestorChangeMarker(string $message): bool
    {
        $normalized = $this->normalizeText($message);

        return preg_match(
            '/\b(artik|bundan sonra|degisti|degistirdim|artirdim|arttirdim|yukselttim|dusurdum|azalttim|cikardim|indirdim|genislettim|daralttim|sadece)\b/u',
            $normalized
        ) === 1;
    }

    private function normalizeText(string $message): string
    {
        return mb_strtolower(strtr($message, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));
    }

    private function equivalent(mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.0001;
        }

        if (is_bool($left) || is_bool($right)) {
            return $left === $right;
        }

        $normalize = static fn (mixed $value): string => mb_strtolower(
            trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '')
        );

        return $normalize($left) === $normalize($right);
    }

    private function safeValue(string $field, mixed $value): string|float|int|bool|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = trim((string) $value);

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 80) : $text;
    }

    private function confirmationQuestion(array $conflict, string $profileType): string
    {
        $field = (string) ($conflict['field'] ?? '');
        $current = $this->displayValue($field, $conflict['current_value'] ?? null);
        $proposed = $this->displayValue($field, $conflict['proposed_value'] ?? null);
        $label = $this->fieldLabel($field, $profileType);
        $subject = in_array($profileType, ['investor', 'buyer'], true)
            ? 'yatırım kriterini'
            : 'taşınmaz bilgisini';

        return "{$label} bilgisini netleştirelim: daha önce {$current}, şimdi {$proposed} bilgisi geçti. Güncel {$subject} hangisi?";
    }

    private function fieldLabel(string $field, string $profileType): string
    {
        return match ($field) {
            'block_no' => 'ada numarası',
            'parcel_no' => 'parsel numarası',
            'city' => in_array($profileType, ['investor', 'buyer'], true) ? 'hedef il' : 'il',
            'district' => in_array($profileType, ['investor', 'buyer'], true) ? 'hedef ilçe' : 'ilçe',
            'neighborhood' => 'mahalle',
            'property_type' => 'taşınmaz türü',
            'area_sqm' => 'taşınmaz alanı',
            'area_min_sqm' => 'minimum alan kriteri',
            'area_max_sqm' => 'maksimum alan kriteri',
            'title_deed_type' => 'tapu türü',
            'zoning_status' => 'imar durumu',
            'is_shared_title' => 'hisseli tapu durumu',
            'budget_min' => 'minimum bütçe',
            'budget_max' => 'maksimum bütçe',
            'financing' => 'finansman tercihi',
            'investment_goal' => 'yatırım hedefi',
            'risk_preference' => 'risk tercihi',
            'timeline' => 'alım zamanlaması',
            'location_flexibility' => 'lokasyon esnekliği',
            'accepts_shared_title' => 'hisseli tapu tercihi',
            'target_discount_percent' => 'hedef iskonto',
            default => 'bilgi',
        };
    }

    private function displayValue(string $field, mixed $value): string
    {
        if (in_array($field, ['area_sqm', 'area_min_sqm', 'area_max_sqm'], true) && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').' m²';
        }

        if (in_array($field, ['budget_min', 'budget_max'], true) && is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.').' TL';
        }

        if ($field === 'target_discount_percent' && is_numeric($value)) {
            return '%'.rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        }

        if (in_array($field, ['is_shared_title', 'accepts_shared_title'], true) && is_bool($value)) {
            return $value ? 'evet' : 'hayır';
        }

        $text = trim((string) $value);

        return $text === '' ? 'belirsiz' : $text;
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }
}
