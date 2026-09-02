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

        if ($profileType !== 'seller') {
            return $this->mergeNonNull($existing, $incoming);
        }

        $result = $existing;
        $previous = is_array($existing[self::DATA_KEY] ?? null)
            ? $existing[self::DATA_KEY]
            : [];
        $pending = $this->pendingByField($previous['pending_conflicts'] ?? []);
        $resolvedFields = [];
        $explicitCorrection = $this->hasExplicitCorrectionMarker($customerMessage);

        foreach ($incoming as $field => $value) {
            if ($value === null) {
                continue;
            }

            if (! in_array($field, self::STABLE_SELLER_FIELDS, true)) {
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

            if ($explicitCorrection || $pendingProposalConfirmed) {
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

        $orderedPending = $this->orderedPending($pending);
        $primary = $orderedPending[0] ?? null;
        $intelligence = [
            'status' => $primary ? 'confirmation_required' : 'consistent',
            'pending_count' => count($orderedPending),
            'highest_priority_field' => $primary['field'] ?? null,
            'confirmation_question' => $primary
                ? $this->confirmationQuestion($primary)
                : null,
            'pending_conflicts' => $orderedPending,
            'resolved_fields' => array_values(array_unique($resolvedFields)),
            'guardrails' => [
                'unconfirmed_property_identity_may_be_used_for_valuation' => false,
                'unconfirmed_property_identity_may_be_used_for_matching' => false,
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

    private function mergeNonNull(array $existing, array $incoming): array
    {
        foreach ($incoming as $field => $value) {
            if ($value !== null) {
                $existing[$field] = $value;
            }
        }

        return $existing;
    }

    private function pendingByField(mixed $pending): array
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

            if (! in_array($field, self::STABLE_SELLER_FIELDS, true)) {
                continue;
            }

            $result[$field] = $item;
        }

        return $result;
    }

    private function orderedPending(array $pending): array
    {
        $result = [];

        foreach (self::STABLE_SELLER_FIELDS as $field) {
            if (isset($pending[$field])) {
                $result[] = $pending[$field];
            }
        }

        return $result;
    }

    private function hasExplicitCorrectionMarker(string $message): bool
    {
        $normalized = mb_strtolower(strtr($message, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        return preg_match(
            '/\b(hayir|yanlis|duzelt|duzeltiyorum|aslinda|pardon|degil|kastettim|dogrusu|guncelle|guncelliyorum)\b/u',
            $normalized
        ) === 1;
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

    private function confirmationQuestion(array $conflict): string
    {
        $field = (string) ($conflict['field'] ?? '');
        $current = $this->displayValue($field, $conflict['current_value'] ?? null);
        $proposed = $this->displayValue($field, $conflict['proposed_value'] ?? null);
        $label = match ($field) {
            'block_no' => 'ada numarası',
            'parcel_no' => 'parsel numarası',
            'city' => 'il',
            'district' => 'ilçe',
            'neighborhood' => 'mahalle',
            'property_type' => 'taşınmaz türü',
            'area_sqm' => 'taşınmaz alanı',
            'title_deed_type' => 'tapu türü',
            'zoning_status' => 'imar durumu',
            'is_shared_title' => 'hisseli tapu durumu',
            default => 'taşınmaz bilgisi',
        };

        return "{$label} bilgisini netleştirelim: daha önce {$current}, şimdi {$proposed} bilgisi geçti. Hangisi doğru?";
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($field === 'area_sqm' && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').' m²';
        }

        if ($field === 'is_shared_title' && is_bool($value)) {
            return $value ? 'hisseli' : 'hisseli değil';
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
