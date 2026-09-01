<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Str;

class RealEstateEvidenceQualityService
{
    private const MIN_DOCUMENTARY_CONFIDENCE = 65;

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
    ];

    public function assess(RealEstateProfile $profile, bool $persist = false): array
    {
        if (! $this->supports($profile)) {
            return $this->outOfScope();
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $findings = collect(
            is_array($data['media_findings'] ?? null)
                ? $data['media_findings']
                : []
        )->filter(fn ($finding): bool => is_array($finding));

        $documentaryFindings = [];
        $supportingFindings = [];
        $unknownFindings = [];

        foreach ($findings as $finding) {
            $classification = $this->classify($finding);

            if ($classification === 'documentary') {
                $documentaryFindings[] = $finding;
            } elseif ($classification === 'supporting') {
                $supportingFindings[] = $finding;
            } else {
                $unknownFindings[] = $finding;
            }
        }

        $documentaryFields = $this->fieldsFrom(
            collect($documentaryFindings)
                ->filter(fn (array $finding): bool =>
                    (int) ($finding['confidence_score'] ?? 0)
                        >= self::MIN_DOCUMENTARY_CONFIDENCE
                )
                ->all()
        );
        $supportingFields = $this->fieldsFrom([
            ...$supportingFindings,
            ...$unknownFindings,
        ]);

        $identitySignals = $this->identitySignals($documentaryFields);
        $qualifiedDocumentaryCount = collect($documentaryFindings)
            ->filter(fn (array $finding): bool =>
                (int) ($finding['confidence_score'] ?? 0)
                    >= self::MIN_DOCUMENTARY_CONFIDENCE
            )
            ->count();

        $sufficientForMatching = $profile->profile_type !== 'seller'
            || (
                $qualifiedDocumentaryCount >= 1
                && count($documentaryFields) >= 2
                && $identitySignals !== []
            );

        $status = match (true) {
            $findings->isEmpty() => 'none',
            $qualifiedDocumentaryCount >= 1 && $sufficientForMatching => 'documentary_support',
            count($documentaryFindings) > 0 => 'weak_documentary',
            count($supportingFindings) > 0 => 'supporting_only',
            default => 'unknown',
        };

        $reasons = $this->reasonCodes(
            profileType: (string) $profile->profile_type,
            findingsCount: $findings->count(),
            documentaryCount: count($documentaryFindings),
            qualifiedDocumentaryCount: $qualifiedDocumentaryCount,
            documentaryFields: $documentaryFields,
            identitySignals: $identitySignals,
        );

        $result = [
            'status' => $status,
            'media_evidence_count' => $findings->count(),
            'documentary_evidence_count' => count($documentaryFindings),
            'qualified_documentary_evidence_count' => $qualifiedDocumentaryCount,
            'supporting_evidence_count' => count($supportingFindings),
            'unknown_evidence_count' => count($unknownFindings),
            'documentary_fields' => $documentaryFields,
            'supporting_fields' => $supportingFields,
            'identity_signals' => $identitySignals,
            'minimum_documentary_confidence' => self::MIN_DOCUMENTARY_CONFIDENCE,
            'sufficient_for_matching' => $sufficientForMatching,
            'official_verification_complete' => false,
            'reason_codes' => $reasons,
            'next_best_action' => $this->nextBestAction(
                profileType: (string) $profile->profile_type,
                status: $status,
                sufficientForMatching: $sufficientForMatching,
            ),
            'updated_at' => now()->toIso8601String(),
        ];

        if ($persist) {
            $data['evidence_quality_intelligence'] = $result;
            $profile->update(['data' => $data]);
        }

        return $result;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        return (int) $profile->user_id === RealEstateIsolationService::USER_ID
            && (int) $profile->ai_bot_id === RealEstateIsolationService::BOT_ID;
    }

    private function classify(array $finding): string
    {
        $documentType = $this->normalize($finding['document_type'] ?? null);

        if ($documentType === null) {
            return 'unknown';
        }

        foreach ([
            'tapu',
            'title deed',
            'tapu senedi',
            'parsel',
            'kadastro',
            'imar',
            'belediye',
            'e devlet',
            'edevlet',
            'takbis',
        ] as $signal) {
            if (str_contains($documentType, $signal)) {
                return 'documentary';
            }
        }

        foreach ([
            'ilan',
            'listing',
            'screenshot',
            'ekran goruntusu',
            'foto',
            'photo',
            'harita',
            'map',
            'konum',
        ] as $signal) {
            if (str_contains($documentType, $signal)) {
                return 'supporting';
            }
        }

        return 'unknown';
    }

    private function fieldsFrom(array $findings): array
    {
        $fields = [];

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            foreach (self::EVIDENCE_FIELDS as $field) {
                if ($this->present($finding[$field] ?? null)) {
                    $fields[] = $field;
                }
            }
        }

        return array_values(array_unique($fields));
    }

    private function identitySignals(array $fields): array
    {
        $signals = [];

        if (
            in_array('block_no', $fields, true)
            && in_array('parcel_no', $fields, true)
        ) {
            $signals[] = 'block_parcel';
        }

        if (
            in_array('district', $fields, true)
            && in_array('area_sqm', $fields, true)
            && (
                in_array('title_deed_type', $fields, true)
                || in_array('zoning_status', $fields, true)
                || in_array('property_type', $fields, true)
            )
        ) {
            $signals[] = 'location_area_characteristic';
        }

        return $signals;
    }

    private function reasonCodes(
        string $profileType,
        int $findingsCount,
        int $documentaryCount,
        int $qualifiedDocumentaryCount,
        array $documentaryFields,
        array $identitySignals,
    ): array {
        if ($profileType !== 'seller') {
            return [];
        }

        $reasons = [];

        if ($findingsCount === 0) {
            $reasons[] = 'no_media_evidence';
        }

        if ($findingsCount > 0 && $documentaryCount === 0) {
            $reasons[] = 'supporting_media_only';
        }

        if ($documentaryCount > 0 && $qualifiedDocumentaryCount === 0) {
            $reasons[] = 'documentary_confidence_too_low';
        }

        if ($qualifiedDocumentaryCount > 0 && count($documentaryFields) < 2) {
            $reasons[] = 'insufficient_documentary_fields';
        }

        if ($qualifiedDocumentaryCount > 0 && $identitySignals === []) {
            $reasons[] = 'missing_documentary_identity_signal';
        }

        return array_values(array_unique($reasons));
    }

    private function nextBestAction(
        string $profileType,
        string $status,
        bool $sufficientForMatching,
    ): string {
        if ($profileType !== 'seller' || $sufficientForMatching) {
            return 'Mevcut belge desteğini resmi doğrulama yerine kullanma; tapu/imar kontrollerini işlem öncesinde ayrıca teyit et.';
        }

        return match ($status) {
            'none' => 'Yatırımcı eşleştirmesinden önce satıcıdan mümkünse tapu, parsel veya imar belgesi/görseli iste; kişisel kimlik bilgilerini isteme.',
            'supporting_only', 'unknown' => 'İlan veya genel görsel tek başına taşınmaz doğrulaması değildir. Eşleştirmeden önce ada/parsel, tapu veya imar bilgisini destekleyen belge/görsel iste.',
            'weak_documentary' => 'Belge görüntüsündeki taşınmaz tanımlayıcıları yeterince net değil. Ada/parsel veya konum + m² + taşınmaz niteliğini okunabilir şekilde teyit et.',
            default => 'Taşınmaz belge desteğini tamamla; yeterli belge sinyali olmadan yatırımcı eşleştirmesini hazır fırsat gibi sunma.',
        };
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

    private function outOfScope(): array
    {
        return [
            'status' => 'out_of_scope',
            'media_evidence_count' => 0,
            'documentary_evidence_count' => 0,
            'qualified_documentary_evidence_count' => 0,
            'supporting_evidence_count' => 0,
            'unknown_evidence_count' => 0,
            'documentary_fields' => [],
            'supporting_fields' => [],
            'identity_signals' => [],
            'minimum_documentary_confidence' => self::MIN_DOCUMENTARY_CONFIDENCE,
            'sufficient_for_matching' => false,
            'official_verification_complete' => false,
            'reason_codes' => ['out_of_scope'],
            'next_best_action' => '',
            'updated_at' => null,
        ];
    }
}
