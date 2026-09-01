<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Str;

class RealEstateEvidenceReconciliationService
{
    private const MIN_MEDIA_CONFIDENCE = 70;

    /**
     * Only fields that are safe to copy from clearly visible property media
     * into an otherwise blank CRM field are promoted. Asking price stays
     * evidence-only because an old listing screenshot must never silently
     * become the seller's current expectation.
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
     * Safety invariants:
     * - never overwrite an existing canonical value;
     * - promote media only into blank safe fields;
     * - preserve whether a value came from the customer or from media;
     * - media-derived values cannot corroborate themselves;
     * - never treat image/PDF analysis as official legal verification.
     */
    public function process(ConversationControl $conversation): ?array
    {
        if (! $this->isolation->supportsConversation($conversation)) {
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
        $previousSummary = is_array($data['evidence_intelligence'] ?? null)
            ? $data['evidence_intelligence']
            : [];
        $findings = collect(
            is_array($data['media_findings'] ?? null)
                ? $data['media_findings']
                : []
        )
            ->filter(fn ($finding): bool => is_array($finding))
            ->filter(fn (array $finding): bool =>
                (int) ($finding['confidence_score'] ?? 0) >= self::MIN_MEDIA_CONFIDENCE
            )
            // newest evidence wins provenance; older evidence can still reveal
            // conflicts but must not replace the newest source metadata.
            ->reverse()
            ->values();

        $promoted = [];
        $corroborated = [];
        $repeatedMedia = [];
        $conflicts = [];
        $provenance = is_array($data['field_provenance'] ?? null)
            ? $data['field_provenance']
            : [];

        foreach ($findings as $finding) {
            foreach (self::PROMOTABLE_FIELDS as $field => $mediaField) {
                $candidate = $this->sanitizeValue(
                    field: $field,
                    value: $finding[$mediaField] ?? null,
                );

                if (! $this->present($candidate)) {
                    continue;
                }

                $current = $data[$field] ?? null;
                $source = $this->sourceMeta($finding);
                $fieldProvenance = is_array($provenance[$field] ?? null)
                    ? $provenance[$field]
                    : null;

                if (! $this->present($current)) {
                    $data[$field] = $candidate;

                    // Because findings are newest-first, only the first
                    // promotion may establish canonical media provenance.
                    if ($fieldProvenance === null) {
                        $provenance[$field] = array_merge($source, [
                            'status' => 'media_observed_unverified',
                            'observed_value' => $this->safeScalar($candidate),
                        ]);
                    }

                    $promoted[] = $field;
                    continue;
                }

                // If a field was initially media-derived but the canonical
                // value later changed, the media origin no longer describes
                // the current value. Treat the current value as an independent
                // CRM/customer value while retaining the old media origin for
                // auditability.
                if (
                    $this->isMediaDerived($fieldProvenance)
                    && $this->present($fieldProvenance['observed_value'] ?? null)
                    && ! $this->valuesMatch(
                        $field,
                        $current,
                        $fieldProvenance['observed_value'],
                    )
                ) {
                    $provenance[$field] = [
                        'source' => 'customer_profile',
                        'status' => 'canonical_value_changed_after_media',
                        'officially_verified' => false,
                        'observed_at' => now()->toIso8601String(),
                        'previous_media_origin' => $fieldProvenance,
                    ];
                    $fieldProvenance = $provenance[$field];
                }

                if ($this->valuesMatch($field, $current, $candidate)) {
                    if ($this->isMediaDerived($fieldProvenance)) {
                        // A second screenshot/document agreeing with a value
                        // originally copied from media is useful repetition,
                        // not independent customer corroboration.
                        $repeatedMedia[] = $field;
                        continue;
                    }

                    $corroborated[] = $field;
                    $provenance[$field] = $this->withMediaCorroboration(
                        provenance: $fieldProvenance,
                        source: $source,
                    );
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
                $candidate = $this->sanitizeValue(
                    field: $field,
                    value: $finding[$mediaField] ?? null,
                );

                if (! $this->present($candidate)) {
                    continue;
                }

                $current = $data[$field] ?? null;

                if (! $this->present($current)) {
                    // Evidence-only means exactly that: keep the visible price
                    // in media_findings, but do not create canonical asking_price.
                    continue;
                }

                if (! $this->valuesMatch($field, $current, $candidate)) {
                    $conflicts[] = $this->conflict(
                        field: $field,
                        current: $current,
                        candidate: $candidate,
                        finding: $finding,
                    );
                    continue;
                }

                $corroborated[] = $field;
                $provenance[$field] = $this->withMediaCorroboration(
                    provenance: is_array($provenance[$field] ?? null)
                        ? $provenance[$field]
                        : null,
                    source: $this->sourceMeta($finding),
                );
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
                (string) json_encode($conflict['media_value'] ?? null),
            ]))
            ->take(-20)
            ->values()
            ->all();

        $targetConfidenceBoost = min(
            12,
            (count(array_unique($promoted)) * 2)
                + count(array_unique($corroborated))
        );
        $previousConfidenceBoost = max(
            0,
            min(12, (int) ($previousSummary['confidence_boost_applied'] ?? 0))
        );
        $confidenceIncrement = max(
            0,
            $targetConfidenceBoost - $previousConfidenceBoost
        );

        $summary = [
            'high_confidence_media_count' => $findings->count(),
            'promoted_fields' => array_values(array_unique($promoted)),
            'corroborated_fields' => array_values(array_unique($corroborated)),
            'repeated_media_fields' => array_values(array_unique($repeatedMedia)),
            'conflict_fields' => collect($allConflicts)
                ->pluck('field')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'unresolved_conflict_count' => count($allConflicts),
            'confidence_boost_applied' => max(
                $previousConfidenceBoost,
                $targetConfidenceBoost
            ),
            'official_verification_complete' => false,
            'updated_at' => now()->toIso8601String(),
        ];

        $data['field_provenance'] = $provenance;
        $data['evidence_conflicts'] = $allConflicts;
        $data['evidence_intelligence'] = $summary;

        $profile->update([
            'data' => $data,
            'completeness_score' => $this->completeness(
                (string) $profile->profile_type,
                $data,
            ),
            // Do not let repeated processing of the same evidence ratchet the
            // confidence score to 98. Only apply the newly earned delta.
            'confidence_score' => min(
                98,
                (int) $profile->confidence_score + $confidenceIncrement,
            ),
        ]);

        return $summary;
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! $this->isolation->supportsConversation($conversation)) {
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
            'repeated_media_fields' => $evidence['repeated_media_fields'] ?? [],
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
Belge/görselden yüksek güvenle okunup boş CRM alanına taşınan bilgiler müşteri beyanı değil, "görselde görülen ancak resmi olarak doğrulanmamış" bilgidir. Bunları kesin tapu/imar/hukuki gerçek gibi sunma. corroborated_fields mevcut bağımsız CRM/müşteri değeri ile medya bulgusunun uyuştuğunu gösterir; repeated_media_fields ise yalnızca birden fazla medya kaynağının birbirini tekrar ettiğini gösterir ve müşteri teyidi sayılmaz. conflict_fields boş değilse müşteri/CRM bilgisi ile belge/görsel arasında çelişki vardır; çelişki çözülmeden kesin değerleme veya yatırımcı eşleşmesi iddiası kurma. Asking price görselden otomatik güncellenmez.
Kanıt özeti: {$json}
PROMPT;
    }

    private function withMediaCorroboration(
        ?array $provenance,
        array $source,
    ): array {
        $provenance ??= [
            'source' => 'customer_profile',
            'status' => 'customer_profile',
            'officially_verified' => false,
            'observed_at' => now()->toIso8601String(),
        ];

        if (! isset($provenance['source'])) {
            $provenance['source'] = 'customer_profile';
        }

        if (! isset($provenance['officially_verified'])) {
            $provenance['officially_verified'] = false;
        }

        // Findings are processed newest-first. Keep the first/newest media
        // corroboration instead of letting older screenshots replace it.
        if (! isset($provenance['media_corroboration'])) {
            $provenance['media_corroboration'] = $source;
        }

        $provenance['status'] = 'customer_profile_corroborated_by_media';

        return $provenance;
    }

    private function isMediaDerived(?array $provenance): bool
    {
        return is_array($provenance)
            && ($provenance['source'] ?? null) === 'whatsapp_media'
            && in_array(
                $provenance['status'] ?? null,
                ['media_observed_unverified', 'media_corroborated_unverified'],
                true,
            );
    }

    private function sourceMeta(array $finding): array
    {
        return [
            'source' => 'whatsapp_media',
            'message_id' => $this->nullableString($finding['message_id'] ?? null),
            'document_type' => $this->nullableString($finding['document_type'] ?? null),
            'confidence_score' => max(
                0,
                min(100, (int) ($finding['confidence_score'] ?? 0))
            ),
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
            'confidence_score' => max(
                0,
                min(100, (int) ($finding['confidence_score'] ?? 0))
            ),
            'severity' => $this->severity($field),
            'officially_verified' => false,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    private function sanitizeValue(string $field, mixed $value): mixed
    {
        if (! $this->present($value)) {
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

        if ($text === '' || $this->unknownText($text)) {
            return null;
        }

        return Str::limit($text, 180, '');
    }

    private function unknownText(string $value): bool
    {
        $normalized = $this->normalizeText($value);

        return in_array($normalized, [
            'belgede acik degil',
            'belirsiz',
            'bilinmiyor',
            'okunamiyor',
            'tespit edilemedi',
            'gorunmuyor',
            'yok',
        ], true);
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
            ->filter(fn (string $field): bool =>
                $this->present($data[$field] ?? null)
            )
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
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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
