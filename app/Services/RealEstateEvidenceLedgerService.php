<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateEvidenceEvent;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RealEstateEvidenceLedgerService
{
    private const ANALYSIS_VERSION = 'v1';

    private const EVIDENCE_FIELDS = [
        'property_type',
        'city',
        'district',
        'neighborhood',
        'area_sqm',
        'block_no',
        'parcel_no',
        'title_deed_type',
        'zoning_status',
        'owner_share',
        'visible_asking_price',
        'listing_title',
    ];

    public function record(
        ConversationControl $conversation,
        RealEstateProfile $profile,
        array $analysis
    ): ?RealEstateEvidenceEvent {
        if (! Schema::hasTable('real_estate_evidence_events')) {
            return null;
        }

        $isolation = app(RealEstateIsolationService::class);

        if (
            ! $isolation->supportsConversation($conversation)
            || (int) $profile->conversation_control_id !== (int) $conversation->id
            || (int) $profile->user_id !== RealEstateIsolationService::USER_ID
            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID
        ) {
            return null;
        }

        $messageId = trim((string) ($analysis['message_id'] ?? ''));

        if ($messageId === '') {
            return null;
        }

        $fieldKeys = $this->fieldKeys($analysis);
        $provenanceClass = $this->classify($analysis);
        $identitySignals = $this->identitySignals($fieldKeys);
        $documentType = $this->safeLabel($analysis['document_type'] ?? null, 120);
        $sourceType = $this->sourceType($analysis);
        $mimeType = $this->safeMime($analysis['mime_type'] ?? null);
        $confidence = max(0, min(100, (int) ($analysis['confidence_score'] ?? 0)));
        $warningCount = is_array($analysis['warnings'] ?? null)
            ? min(100, count($analysis['warnings']))
            : 0;
        $fingerprint = $this->contentFingerprint($analysis, $fieldKeys);
        $eventKey = hash('sha256', implode('|', [
            RealEstateIsolationService::USER_ID,
            RealEstateIsolationService::ORGANIZATION_ID,
            RealEstateIsolationService::BOT_ID,
            (int) $conversation->id,
            $messageId,
            self::ANALYSIS_VERSION,
        ]));

        return RealEstateEvidenceEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'conversation_control_id' => $conversation->id,
                'real_estate_profile_id' => $profile->id,
                // Raw Evolution/WhatsApp IDs are deliberately not persisted here.
                'message_id_hash' => hash('sha256', $messageId),
                'source_type' => $sourceType,
                'mime_type' => $mimeType,
                'document_type' => $documentType,
                'provenance_class' => $provenanceClass,
                'confidence_score' => $confidence,
                // Only field names and evidence metadata are stored. Extracted
                // values, summaries, filenames, captions and media bytes stay out.
                'field_keys' => $fieldKeys,
                'identity_signals' => $identitySignals,
                'warning_count' => $warningCount,
                'content_fingerprint' => $fingerprint,
                'analysis_version' => self::ANALYSIS_VERSION,
                'recorded_at' => now(),
            ]
        );
    }

    public function telemetry24h(): array
    {
        if (! Schema::hasTable('real_estate_evidence_events')) {
            return $this->emptyTelemetry();
        }

        $base = RealEstateEvidenceEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('recorded_at', '>=', now()->subDay());

        return [
            'events' => (clone $base)->count(),
            'documentary' => (clone $base)
                ->where('provenance_class', 'documentary')
                ->count(),
            'supporting' => (clone $base)
                ->where('provenance_class', 'supporting')
                ->count(),
            'unknown' => (clone $base)
                ->where('provenance_class', 'unknown')
                ->count(),
            'qualified_documentary' => (clone $base)
                ->where('provenance_class', 'documentary')
                ->where('confidence_score', '>=', 65)
                ->count(),
            'unique_profiles' => (clone $base)
                ->distinct('real_estate_profile_id')
                ->count('real_estate_profile_id'),
        ];
    }

    private function fieldKeys(array $analysis): array
    {
        $fields = [];

        foreach (self::EVIDENCE_FIELDS as $field) {
            if ($this->present($analysis[$field] ?? null)) {
                $fields[] = $field;
            }
        }

        sort($fields);

        return array_values(array_unique($fields));
    }

    private function identitySignals(array $fieldKeys): array
    {
        $signals = [];

        if (
            in_array('block_no', $fieldKeys, true)
            && in_array('parcel_no', $fieldKeys, true)
        ) {
            $signals[] = 'block_parcel';
        }

        if (
            in_array('district', $fieldKeys, true)
            && in_array('area_sqm', $fieldKeys, true)
            && (
                in_array('property_type', $fieldKeys, true)
                || in_array('title_deed_type', $fieldKeys, true)
                || in_array('zoning_status', $fieldKeys, true)
            )
        ) {
            $signals[] = 'location_area_characteristic';
        }

        return $signals;
    }

    private function classify(array $analysis): string
    {
        $documentType = $this->normalize($analysis['document_type'] ?? null);

        if ($documentType === null) {
            return 'unknown';
        }

        foreach ([
            'ilan', 'listing', 'screenshot', 'ekran goruntusu', 'foto', 'photo',
            'harita', 'map', 'konum',
        ] as $signal) {
            if (str_contains($documentType, $signal)) {
                return 'supporting';
            }
        }

        foreach ([
            'tapu', 'title deed', 'tapu senedi', 'parsel', 'kadastro', 'imar',
            'belediye', 'e devlet', 'edevlet', 'takbis',
        ] as $signal) {
            if (str_contains($documentType, $signal)) {
                return 'documentary';
            }
        }

        return 'unknown';
    }

    private function sourceType(array $analysis): string
    {
        $mime = strtolower(trim((string) ($analysis['mime_type'] ?? '')));

        if ($mime === 'application/pdf') {
            return 'document';
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        return 'unknown';
    }

    private function safeMime(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $mime = strtolower(trim((string) $value));

        if ($mime === '') {
            return null;
        }

        return preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#', $mime) === 1
            ? mb_substr($mime, 0, 120)
            : null;
    }

    private function safeLabel(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $label = trim((string) $value);

        if ($label === '') {
            return null;
        }

        // Keep this field categorical only; strip line breaks/control chars and
        // obvious contact-like text before it reaches the observability ledger.
        $label = preg_replace('/[\r\n\t]+/u', ' ', $label) ?? $label;
        $label = preg_replace('/\b[\w.%+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', '[redacted]', $label) ?? $label;
        $label = preg_replace('/(?<!\d)(?:\+?90\s*)?(?:0?5\d{2})(?:[\s().-]*\d){7}(?!\d)/u', '[redacted]', $label) ?? $label;
        $label = trim(preg_replace('/\s{2,}/u', ' ', $label) ?? $label);

        return $label === '' ? null : mb_substr($label, 0, $maxLength);
    }

    private function contentFingerprint(array $analysis, array $fieldKeys): string
    {
        $material = [
            'document_type' => $this->normalize($analysis['document_type'] ?? null),
            'field_keys' => $fieldKeys,
            'confidence_score' => max(0, min(100, (int) ($analysis['confidence_score'] ?? 0))),
        ];

        foreach ($fieldKeys as $field) {
            $value = $analysis[$field] ?? null;
            $material['values'][$field] = is_scalar($value)
                ? $this->normalize($value)
                : null;
        }

        return hash(
            'sha256',
            json_encode($material, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
        );
    }

    private function present(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_scalar($value);
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = strtr($value, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);

        return Str::lower($value);
    }

    private function emptyTelemetry(): array
    {
        return [
            'events' => 0,
            'documentary' => 0,
            'supporting' => 0,
            'unknown' => 0,
            'qualified_documentary' => 0,
            'unique_profiles' => 0,
        ];
    }
}
