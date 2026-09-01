<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Str;

class RealEstateEvidenceReconciliationService
{
    private const MIN_MEDIA_CONFIDENCE = 70;

    /**
     * Only fields that are safe to copy from a clearly visible property
     * document/screenshot into an otherwise empty CRM field are promoted.
     * Asking price is intentionally evidence-only because an old listing
     * screenshot must not silently become the seller's current expectation.
     */
    private const PROMOTABLE_FIELDS = [
        'property_type' => 'property_type',
        'city' => 'city',
        'district' => 'district',
        'neighborhood' => 'neighborhood',
        'area_sqm' => 'area_sqm',
        'block_no' => 'block_no',
        'parcel_no' => 'parcel_no',
        'title_deed_type' => 'title_deed_type',
        'zoning_status' => 'zoning_status',
    ];

    private const EVIDENCE_ONLY_FIELDS = [
        'asking_price' => 'visible_asking_price',
    ];

    public function __construct(
        private readonly RealEstateIsolationService $isolation,
    ) {
    }

    /**
     * Reconcile high-confidence media findings with canonical CRM memory.
     *
     * Rules:
     * - never overwrite an existing customer/profile value;
     * - safely promote high-confidence media data only into blank fields;
     * - record corroboration and conflicts with provenance;
     * - never treat image/PDF analysis as official legal verification.
     */
    public function process(ConversationControl $conversation): ?array
    {
        if (! $this->isolation->conversationIsIsolated($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->first();

        if (! $profile) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $findings = collect(
            is_array($data['media_findings'] ?? null)
                ? $data['media_findings']
                : []
        )
            ->filter(fn ($finding): bool => is_array($finding))
            ->filter(fn (array $finding): bool =>
                (int) ($finding['confidence_score'] ?? 0) >= self::MIN_MEDIA_CONFIDENCE
            )
            ->reverse()
            ->values();

        $promoted = [];
        $corroborated = [];
        $conflicts = [];
        $provenance = is_array($data['field_provenance'] ?? null)
            ? $data['field_provenance']
            : [];

        foreach ($findings as $finding) {
            foreach (self::PROMOTABLE_FIELDS as $field => $mediaField) {
                $candidate = $this->sanitizeValue($field, $finding[$mediaField] ?? null);

                if (! $this->present($candidate)) {
                    continue;
                }

                $current = $data[$field] ?? null;
                $source = $this->sourceMeta($finding);

                if (! $this->present($current)) {
                    $data[$field] = $candidate;
                    $provenance[$field] = array_merge($source, [
                        'status' => 'media_observed_unverified',
                    ]);
                    $promoted[] = $field;
                    continue;
                }

                if ($this->valuesMatch($field, $current, $candidate)) {
                    if (! in_array($field, $corroborated, true)) {
                        $corroborated[] = $field;
                    }

                    $provenance[$field] = array_merge($source, [
                        'status' => 'media_corroborated_unverified',
                    ]);
                    continue;
                }

                $conflicts[] = $this->conflict(
                    field: $field,
                    current: $current,
                    candidate: $candidate,
                    finding: $finding,
                );
            }

            foreach (self::EVIDENCE_ONLY_FIELDS as $field => $mediaField) {
                $candidate = $this->sanitizeValue($field, $finding[$mediaField] ?? null);

                if (! $this->present($candidate)) {
                    continue;
                }

                $current = $data[$field] ?? null;

                if ($this->present($current) && ! $this->valuesMatch($field, $current, $candidate)) {
                    $conflicts[] = $this->conflict(
                        field: $field,
                        current: $current,
                        candidate: $candidate,
                        finding: $finding,
                    );
                } elseif ($this->present($current)) {
                    $corroborated[] = $field;
                }
            }
        }

        $existingConflicts = is_array($data['evidence_conflicts'] ?? null)
            ? $data['evidence_conflicts']
            : [];

        $allConflicts = collect(array_merge($existingConflicts, $conflicts))
            ->filter(fn ($conflict): bool => is_array($conflict))
            ->unique(fn (array $conflict): string => implode('|', [
                (string) ($conflict['field'] ?? ''),
                (string) ($conflict['message_id'] ?? ''),
                json_encode($conflict['media_value'] ?? null),
            ]))
            ->take(-20)
            ->values()
            ->all();

        $summary = [
            'high_confidence_media_count' => $findings->count(),
            'promoted_fields' => array_values(array_unique($promoted)),
            'corroborated_fields' => array_values(array_unique($corroborated)),
            'conflict_fields' => collect($allConflicts)
                ->pluck('field')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'unresolved_conflict_count' => count($allConflicts),
            'official_verification_complete' => false,
            'updated_at' => now()->toIso8601String(),
        ];

        $data['field_provenance'] = $provenance;
        $data['evidence_conflicts'] = $allConflicts;
        $data['evidence_intelligence'] = $summary;

        $confidenceBoost = min(
            12,
            (count(array_unique($promoted)) * 2)
                + count(array_unique($corroborated))
        );

        $profile->update([
            'data' => $data,
            'completeness_score' => $this->completeness(
                (string) $profile->profile_type,
                $data,
            ),
            'confidence_score' => min(
                98,
                max((int) $profile->confidence_score, 25) + $confidenceBoost,
            ),
        ]);

        return $summary;
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! $this->isolation->conversationIsIsolated($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->first();

        $evidence = is_array($profile?->data)
            ? ($profile->data['evidence_intelligence'] ?? null)
            : null;

        if (! is_array($evidence) || $evidence === []) {
            return '';
        }

        $safe = [
            'promoted_fields' => $evidence['promoted_fields'] ?? [],
            'corroborated_fields' => $evidence['corroborated_fields'] ?? [],
            'conflict_fields' => $evidence['conflict_fields'] ?? [],
            'unresolved_conflict_count' => $evidence['unresolved_conflict_count'] ?? 0,
            'official_verification_complete' => false,
        ];

        $json = json_encode(
            $safe,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE EVIDENCE PROVENANCE]
Belge/görselden yüksek güvenle okunup boş CRM alanına taşınan bilgiler müşteri beyanı değil, "görselde görülen ancak resmi olarak doğrulanmamış" bilgidir. Bunları kesin tapu/imar/hukuki gerçek gibi sunma. conflict_fields boş değilse müşterinin beyanı ile belge/görsel arasında çelişki vardır; çelişki çözülmeden kesin değerleme veya yatırımcı eşleşmesi iddiası kurma. Asking price görselden otomatik güncellenmez.
Kanıt özeti: {$json}
PROMPT;
    }

    private function sourceMeta(array $finding): array
    {
        return [
            'source' => 'whatsapp_media',
            'message_id' => $this->nullableString($finding['message_id'] ?? null),
            'document_type' => $this->nullableString($finding['document_type'] ?? null),
            'confidence_score' => max(0, min(100, (int) ($finding['confidence_score'] ?? 0))),
            'observed_at' => $this->nullableString($finding['analyzed_at'] ?? null)
                ?: now()->toIso8601String(),
            'officially_verified' => false,
        ];
    }

    private function conflict(
        string $field,
        mixed $current,
        mixed $candidate,
        array $finding,
    ): array {
        return [
            'field' => $field,
            'profile_value' => $this->safeScalar($current),
            'media_value' => $this->safeScalar($candidate),
            'message_id' => $this->nullableString($finding['message_id'] ?? null),
            'document_type' => $this->nullableString($finding['document_type'] ?? null),
            'confidence_score' => max(0, min(100, (int) ($finding['confidence_score'] ?? 0))),
            'severity' => $this->severity($field),
            'officially_verified' => false,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    private function sanitizeValue(string $field, mixed $value): mixed
    {
        if (!$this->present($value)) {
            return null;
        }

        if (in_array($field, ['area_sqm', 'asking_price'], true)) {
            if (! is_numeric($value)) {
                return null;
            }

            $numeric = (float) $value;

            return $numeric > 0 ? $numeric : null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, 180, '');
    }

    private function valuesMatch(string $field, mixed $left, mixed $right): bool
    {
        if (in_array($field, ['area_sqm', 'asking_price'], true)) {
            if (! is_numeric($left) || ! is_numeric($right)) {
                return false;
            }

            $a = (float) $left;
            $b = (float) $right;

            if ($a <= 0 || $b <= 0) {
                return false;
            }

            $tolerance = $field === 'area_sqm' ? 0.03 : 0.05;

            return abs($a - $b) / max($a, $b) <= $tolerance;
        }

        $a = $this->normalizeText($left);
        $b = $this->normalizeText($right);

        return $a !== null && $b !== null && $a === $b;
    }

    private function completeness(string $profileType, array $data): int
    {
        $fields = match ($profileType) {
            'seller' => [
                'property_type', 'city', 'district', 'area_sqm',
                'asking_price', 'urgency',
            ],
            'investor', 'buyer' => [
                'city', 'district', 'property_type', 'budget_max',
                'investment_goal', 'timeline',
            ],
            default => ['intent', 'city', 'property_type'],
        };

        $filled = collect($fields)
            ->filter(fn (string $field): bool => $this->present($data[$field] ?? null))
            ->count();

        return (int) round(($filled / max(1, count($fields))) * 100);
    }

    private function severity(string $field): string
    {
        return match ($field) {
            'city', 'district', 'block_no', 'parcel_no' => 'critical',
            'property_type', 'area_sqm', 'title_deed_type', 'zoning_status' => 'high',
            default => 'medium',
        };
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = Str::lower(strtr($value, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]));

        return trim(preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '');
    }

    private function present(mixed $value): bool
    {
        return $value !== null
            && (! is_string($value) || trim($value) !== '');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 180, '');
    }

    private function safeScalar(mixed $value): string|float|int|bool|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : Str::limit($value, 180, '');
        }

        return $value;
    }
}
