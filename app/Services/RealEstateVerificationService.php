<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Str;

class RealEstateVerificationService
{
    private const TAG_PREFIX = 'real_estate:verification:';

    private const MIN_MEDIA_CONFIDENCE = 70;

    public function process(ConversationControl $conversation): ?array
    {
        if (! $this->inScope($conversation)) {
            return null;
        }

        $profile = $this->profileFor($conversation);

        if (! $profile) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $provenance = is_array($data['field_provenance'] ?? null)
            ? $data['field_provenance']
            : [];
        $allFindings = collect(
            is_array($data['media_findings'] ?? null)
                ? $data['media_findings']
                : []
        )->filter(fn ($finding): bool => is_array($finding));
        $findings = $allFindings
            ->filter(fn (array $finding): bool =>
                (int) ($finding['confidence_score'] ?? 0) >= self::MIN_MEDIA_CONFIDENCE
            )
            ->values();
        $lowConfidenceMediaCount = max(
            0,
            $allFindings->count() - $findings->count()
        );

        $corroborated = [];
        $mediaOnly = [];
        $conflicts = [];
        $evidence = [];

        foreach ($this->comparableFields() as $field => $mediaField) {
            $profileValue = $data[$field] ?? null;
            $mediaValues = $findings
                ->pluck($mediaField)
                ->filter(fn ($value): bool => $this->present($value))
                ->values()
                ->all();

            if ($mediaValues === []) {
                continue;
            }

            $evidence[$field] = $this->safeEvidenceValues($mediaValues);

            if (! $this->present($profileValue)) {
                continue;
            }

            $matches = collect($mediaValues)
                ->contains(fn ($mediaValue): bool =>
                    $this->valuesMatch($field, $profileValue, $mediaValue)
                );

            if ($matches) {
                if ($this->fieldIsMediaDerived($field, $provenance)) {
                    // A value copied from media cannot verify itself. Repeated
                    // screenshots/documents are supporting repetition only.
                    $mediaOnly[] = $field;
                    continue;
                }

                $corroborated[] = $field;
                continue;
            }

            $conflicts[] = [
                'field' => $field,
                'profile_value' => $this->safeScalar($profileValue),
                'media_values' => $this->safeEvidenceValues($mediaValues),
                'severity' => $this->conflictSeverity($field),
            ];
        }

        $missing = $this->missingVerificationFields(
            profileType: (string) $profile->profile_type,
            data: $data,
        );
        $criticalConflicts = collect($conflicts)
            ->where('severity', 'critical')
            ->count();
        $highConflicts = collect($conflicts)
            ->where('severity', 'high')
            ->count();

        $riskScore = $this->riskScore(
            profileType: (string) $profile->profile_type,
            evidenceCount: count($evidence),
            corroboratedCount: count(array_unique($corroborated)),
            conflicts: $conflicts,
            missing: $missing,
        );

        $status = match (true) {
            $criticalConflicts > 0 => 'blocked',
            $highConflicts > 0 || $riskScore >= 65 => 'high_risk',
            count($conflicts) > 0 || $riskScore >= 40 => 'review',
            count($evidence) >= 2 && count($corroborated) >= 2 => 'corroborated',
            default => 'unverified',
        };

        $verification = [
            'status' => $status,
            'risk_score' => $riskScore,
            'media_evidence_count' => $findings->count(),
            'low_confidence_media_count' => $lowConfidenceMediaCount,
            'minimum_media_confidence' => self::MIN_MEDIA_CONFIDENCE,
            'evidence_fields' => array_values(array_keys($evidence)),
            'corroborated_fields' => array_values(array_unique($corroborated)),
            'media_only_fields' => array_values(array_unique($mediaOnly)),
            'conflicts' => array_values($conflicts),
            'missing_verification_fields' => array_values($missing),
            'safe_to_match' => in_array($status, ['corroborated', 'review'], true)
                && $riskScore < 55
                && $criticalConflicts === 0
                && count(array_unique($corroborated)) > 0,
            'legal_verification_complete' => false,
            'next_best_action' => $this->nextBestAction(
                status: $status,
                conflicts: $conflicts,
                missing: $missing,
                evidenceCount: count($evidence),
                mediaOnlyCount: count(array_unique($mediaOnly)),
                lowConfidenceMediaCount: $lowConfidenceMediaCount,
            ),
            'updated_at' => now()->toIso8601String(),
        ];

        $data['verification_intelligence'] = $verification;
        $profile->update(['data' => $data]);

        $conversation->update([
            'tags' => $this->verificationTags(
                currentTags: $conversation->etiketler(),
                verification: $verification,
            ),
        ]);

        return $verification;
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! $this->inScope($conversation)) {
            return '';
        }

        $profile = $this->profileFor($conversation);
        $verification = is_array($profile?->data)
            ? ($profile->data['verification_intelligence'] ?? null)
            : null;

        if (! is_array($verification) || $verification === []) {
            return '';
        }

        $safe = [
            'status' => $verification['status'] ?? null,
            'risk_score' => $verification['risk_score'] ?? null,
            'corroborated_fields' => $verification['corroborated_fields'] ?? [],
            'media_only_fields' => $verification['media_only_fields'] ?? [],
            'low_confidence_media_count' => $verification['low_confidence_media_count'] ?? 0,
            'conflicts' => collect($verification['conflicts'] ?? [])
                ->map(fn ($conflict): array => [
                    'field' => $conflict['field'] ?? null,
                    'severity' => $conflict['severity'] ?? null,
                ])
                ->values()
                ->all(),
            'missing_verification_fields' => $verification['missing_verification_fields'] ?? [],
            'safe_to_match' => (bool) ($verification['safe_to_match'] ?? false),
            'next_best_action' => $verification['next_best_action'] ?? null,
        ];

        $json = json_encode(
            $safe,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE VERIFICATION & RISK]
Bu blok müşteri beyanı ile WhatsApp üzerinden analiz edilen belge/görseller arasındaki dahili tutarlılık kontrolüdür. Bu kontrol resmi tapu, belediye, TAKBİS veya hukuki doğrulama değildir. Müşteriye dahili risk skorunu veya alan adlarını gösterme. media_only_fields yalnızca görsel/belgeden CRM'e alınmış, bağımsız müşteri teyidi olmayan alanlardır; bunları kendi kaynaklarıyla eşleşiyor diye doğrulanmış sayma. Düşük güvenli medya bulguları çatışma, doğrulama veya eşleştirme kararında kullanılmaz; gerekirse daha net belge/görsel iste. Çelişki varsa kesin fiyat/imar/tapu iddiasında bulunma ve eşleştirmeyi aceleye getirme. status blocked/high_risk ise önce çelişkiyi açık, kısa ve profesyonel bir soruyla netleştir. status corroborated olsa bile resmi geçerlilik garantisi verme. legal_verification_complete hiçbir zaman yalnız görsel analizinden true kabul edilmez.
Doğrulama desteği: {$json}
PROMPT;
    }

    private function inScope(ConversationControl $conversation): bool
    {
        return app(RealEstateIsolationService::class)->supportsConversation($conversation);
    }

    private function profileFor(
        ConversationControl $conversation
    ): ?RealEstateProfile {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();
    }

    private function comparableFields(): array
    {
        return [
            'property_type' => 'property_type',
            'city' => 'city',
            'district' => 'district',
            'neighborhood' => 'neighborhood',
            'area_sqm' => 'area_sqm',
            'block_no' => 'block_no',
            'parcel_no' => 'parcel_no',
            'title_deed_type' => 'title_deed_type',
            'zoning_status' => 'zoning_status',
            'asking_price' => 'visible_asking_price',
        ];
    }

    private function fieldIsMediaDerived(string $field, array $provenance): bool
    {
        $meta = $provenance[$field] ?? null;

        if (! is_array($meta)) {
            return false;
        }

        return ($meta['source'] ?? null) === 'whatsapp_media'
            && in_array(
                $meta['status'] ?? null,
                ['media_observed_unverified', 'media_corroborated_unverified'],
                true,
            );
    }

    private function missingVerificationFields(
        string $profileType,
        array $data
    ): array {
        if ($profileType !== 'seller') {
            return [];
        }

        $missing = [];

        if (! $this->present($data['title_deed_type'] ?? null)) {
            $missing[] = 'title_deed_type';
        }

        if (! $this->present($data['zoning_status'] ?? null)) {
            $missing[] = 'zoning_status';
        }

        if (
            ! $this->present($data['location_url'] ?? null)
            && ! (
                $this->present($data['block_no'] ?? null)
                && $this->present($data['parcel_no'] ?? null)
            )
        ) {
            $missing[] = 'location_or_block_parcel';
        }

        return $missing;
    }

    private function riskScore(
        string $profileType,
        int $evidenceCount,
        int $corroboratedCount,
        array $conflicts,
        array $missing,
    ): int {
        $score = $profileType === 'seller' ? 25 : 10;

        if ($evidenceCount === 0 && $profileType === 'seller') {
            $score += 15;
        }

        $severities = collect($conflicts)
            ->pluck('severity')
            ->filter()
            ->values();

        foreach ($severities as $severity) {
            $score += match ($severity) {
                'critical' => 45,
                'high' => 28,
                default => 15,
            };
        }

        $score += min(24, count($missing) * 8);
        $score -= min(20, $corroboratedCount * 5);

        if ($severities->contains('critical')) {
            $score = max($score, 85);
        } elseif ($severities->contains('high')) {
            $score = max($score, 65);
        }

        return max(0, min(100, $score));
    }

    private function conflictSeverity(string $field): string
    {
        return match ($field) {
            'city', 'district', 'block_no', 'parcel_no' => 'critical',
            'area_sqm', 'property_type', 'title_deed_type' => 'high',
            default => 'medium',
        };
    }

    private function valuesMatch(string $field, mixed $left, mixed $right): bool
    {
        if (in_array($field, ['area_sqm', 'asking_price'], true)) {
            $a = is_numeric($left) ? (float) $left : null;
            $b = is_numeric($right) ? (float) $right : null;

            if ($a === null || $b === null || $a <= 0 || $b <= 0) {
                return false;
            }

            $tolerance = $field === 'area_sqm' ? 0.05 : 0.08;

            return abs($a - $b) / max($a, $b) <= $tolerance;
        }

        $a = $this->normalize($left);
        $b = $this->normalize($right);

        return $a !== null && $b !== null && $a === $b;
    }

    private function nextBestAction(
        string $status,
        array $conflicts,
        array $missing,
        int $evidenceCount,
        int $mediaOnlyCount,
        int $lowConfidenceMediaCount,
    ): string {
        if (in_array($status, ['blocked', 'high_risk'], true)) {
            $first = $conflicts[0]['field'] ?? null;

            return $first
                ? 'Belge/görsel ile müşteri beyanı arasındaki '
                    .$this->fieldLabel((string) $first)
                    .' çelişkisini netleştir; doğrulamadan fiyat veya eşleşme kesinliği verme.'
                : 'Yüksek risk sinyalini netleştir; doğrulama tamamlanmadan eşleştirme veya kesin fiyat iddiası yapma.';
        }

        if ($evidenceCount === 0 && $lowConfidenceMediaCount > 0) {
            return 'Gönderilen belge/görsel yeterince net okunamadı. Kritik tapu/parsel bilgisini müşteriden yazılı teyit et veya daha net bir görsel iste.';
        }

        if ($evidenceCount === 0) {
            return 'Satıcıdan mümkünse tapu/parsel veya ilan ekran görüntüsü gibi doğrulayıcı belge/görsel iste; kişisel kimlik bilgilerini isteme.';
        }

        if ($mediaOnlyCount > 0) {
            return 'Belge/görselden alınan kritik taşınmaz bilgilerini müşteriye kısa biçimde teyit ettir; görselin kendi verisini doğruladığını varsayma.';
        }

        if ($missing !== []) {
            return 'Eksik tapu/imar/konum doğrulama bilgisini doğal akışta tamamla; görsel analizi resmi doğrulama olarak sunma.';
        }

        if ($status === 'corroborated') {
            return 'Beyan ve görünen belge bilgileri tutarlı. Resmi tapu/imar kontrolü gerektiğini koruyarak değerleme ve uygun yatırımcı eşleştirmesine ilerle.';
        }

        return 'Mevcut belge bulgularını kısa biçimde teyit et ve resmi doğrulama gerektiren alanları tamamla.';
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'property_type' => 'taşınmaz türü',
            'city' => 'il',
            'district' => 'ilçe',
            'neighborhood' => 'mahalle',
            'area_sqm' => 'm²',
            'block_no' => 'ada',
            'parcel_no' => 'parsel',
            'title_deed_type' => 'tapu niteliği',
            'zoning_status' => 'imar durumu',
            'asking_price' => 'istenen fiyat',
            default => 'bilgi',
        };
    }

    private function verificationTags(
        array $currentTags,
        array $verification
    ): array {
        $tags = collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::TAG_PREFIX)
            )
            ->values();

        $tags->push(
            self::TAG_PREFIX.($verification['status'] ?? 'unverified')
        );

        if ((bool) ($verification['safe_to_match'] ?? false)) {
            $tags->push(self::TAG_PREFIX.'safe_to_match');
        }

        return $tags->unique()->values()->all();
    }

    private function safeEvidenceValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $this->safeScalar($value))
            ->filter(fn ($value): bool => $value !== null)
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    private function safeScalar(mixed $value): string|float|int|bool|null
    {
        if (! is_scalar($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === ''
                ? null
                : Str::limit($value, 180, '');
        }

        return $value;
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

        $value = Str::lower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function present(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ! is_string($value) || trim($value) !== '';
    }
}
