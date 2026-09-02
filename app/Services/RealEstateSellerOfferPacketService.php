<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateSellerOfferPacketService
{
    private const DATA_KEY = 'seller_offer_packet_intelligence';

    private const TAG_PREFIX = 'real_estate:offer_packet:';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $summary = $this->build($data);
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        if ($this->comparable($existing) === $summary) {
            $this->syncConversationState($profile, $existing ?: $summary);

            return $existing ?: $summary;
        }

        $stored = [
            ...$summary,
            'updated_at' => now()->toIso8601String(),
        ];
        $data[self::DATA_KEY] = $stored;

        $profile->data = $data;
        $profile->saveQuietly();
        $this->syncConversationState($profile, $stored);

        return $stored;
    }

    public function summaryForProfile(RealEstateProfile $profile): array
    {
        if (! $this->supports($profile)) {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $existing = is_array($data[self::DATA_KEY] ?? null)
            ? $data[self::DATA_KEY]
            : [];

        return $existing !== [] ? $existing : $this->build($data);
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->where('profile_type', 'seller')
            ->first();

        if (! $profile) {
            return '';
        }

        $summary = $this->summaryForProfile($profile);

        if ($summary === []) {
            return '';
        }

        $json = json_encode(
            $summary,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE SELLER OFFER PACKET]
Bu blok satıcı dosyasının yatırımcıdan ön teklif toplamaya ne kadar hazır olduğunu gösteren PII içermeyen dahili CRM özetidir. Müşteriye alan adlarını, skoru veya bu bloğun kendisini gösterme. recommended_next_request doluysa müşterinin daha önce verdiği bilgiyi tekrar istemeden yalnız en kritik eksik bilgiyi doğal biçimde iste. ready_for_investor_offer=true ise satıcıyı fiyat yükseltme stratejileriyle yorma; isterse hızlı nakit için yatırımcı tekliflerini toplamaya geçilebileceğini kısa söyle. title_ownership.relation doluysa tapunun kimin üzerine olduğu daha önce kaydedilmiştir; aynı bilgiyi tekrar sorma. Bu kayıt resmi tapu doğrulaması değildir ve legal_verification=false ise müşteriye doğrulanmış tapu sahibi gibi sunma. Piyasanın kötü olduğu, kimsenin alım yapmadığı veya hazır/kesin alıcı bulunduğu gibi doğrulanmamış iddialar kurma. Tapu görseli istenirse kişisel bilgilerin kapatılabileceğini belirt. Otomatik follow-up planlama.
Satıcı teklif dosyası: {$json}
PROMPT;
    }

    private function build(array $data): array
    {
        $media = $this->mediaSummary($data);
        $titleOwnership = $this->titleOwnershipSummary($data);

        $checks = [
            'property_type' => $this->filled($data, 'property_type'),
            'city' => $this->filled($data, 'city'),
            'location' => $this->filled($data, 'district')
                || $this->filled($data, 'neighborhood')
                || $this->filled($data, 'location_url'),
            'area_sqm' => $this->positive($data['area_sqm'] ?? null),
            'asking_price' => $this->positive($data['asking_price'] ?? null),
            'property_identity' => $this->filled($data, 'location_url')
                || (
                    $this->filled($data, 'block_no')
                    && $this->filled($data, 'parcel_no')
                )
                || $media['has_parcel_evidence'],
            'zoning_context' => $this->filled($data, 'zoning_status'),
            'title_deed_context' => $this->filled($data, 'title_deed_type')
                || $media['has_title_deed_evidence'],
            'title_owner_context' => $titleOwnership['known'],
            'property_photo' => $media['has_property_photo'],
            'listing_reference' => $this->filled($data, 'listing_url')
                || $media['has_listing_evidence'],
        ];

        $weights = [
            'property_type' => 10,
            'city' => 8,
            'location' => 8,
            'area_sqm' => 12,
            'asking_price' => 14,
            'property_identity' => 14,
            'zoning_context' => 10,
            'title_deed_context' => 10,
            'property_photo' => 14,
        ];

        $score = 0;
        foreach ($weights as $criterion => $weight) {
            if ($checks[$criterion] ?? false) {
                $score += $weight;
            }
        }

        $criticalOrder = [
            'property_type',
            'city',
            'location',
            'area_sqm',
            'asking_price',
            'property_identity',
            'property_photo',
        ];
        $supportingOrder = [
            'zoning_context',
            'title_deed_context',
            'title_owner_context',
            'listing_reference',
        ];

        $missingCritical = array_values(array_filter(
            $criticalOrder,
            fn (string $key): bool => ! ($checks[$key] ?? false)
        ));
        $missingSupporting = array_values(array_filter(
            $supportingOrder,
            fn (string $key): bool => ! ($checks[$key] ?? false)
        ));

        $coreReady = $checks['property_type']
            && $checks['city']
            && $checks['location']
            && $checks['area_sqm']
            && $checks['asking_price'];
        $readyForInvestorOffer = $coreReady
            && $checks['property_identity']
            && $checks['property_photo'];

        $status = match (true) {
            $readyForInvestorOffer && $score >= 85 => 'ready',
            $score >= 65 => 'nearly_ready',
            $score >= 35 => 'building',
            default => 'early',
        };

        return [
            'readiness_score' => min(100, $score),
            'status' => $status,
            'core_ready' => $coreReady,
            'ready_for_investor_offer' => $readyForInvestorOffer,
            'criteria_presence' => $checks,
            'missing_critical_for_offer' => $missingCritical,
            'missing_supporting_context' => $missingSupporting,
            'media_evidence' => $media,
            'title_ownership' => $titleOwnership,
            'recommended_next_request' => $this->recommendedNextRequest(
                $missingCritical,
                $missingSupporting
            ),
            'conversation_posture' => $readyForInvestorOffer
                ? 'invite_preliminary_investor_offers'
                : 'complete_offer_packet',
            'guardrails' => [
                'no_unsupported_market_claims' => true,
                'no_fake_buyer_or_offer' => true,
                'do_not_coach_seller_to_maximize_asking_price' => true,
                'title_deed_personal_data_may_be_redacted' => true,
                'title_owner_relation_is_not_legal_verification' => true,
                'follow_up_scheduling_allowed' => false,
            ],
        ];
    }

    private function titleOwnershipSummary(array $data): array
    {
        $ownership = is_array($data['title_ownership'] ?? null)
            ? $data['title_ownership']
            : [];
        $relation = strtolower(trim((string) ($ownership['relation'] ?? 'unknown')));
        $allowed = ['seller', 'spouse', 'relative', 'company', 'other_person'];

        if (! in_array($relation, $allowed, true)) {
            $relation = 'unknown';
        }

        return [
            'known' => $relation !== 'unknown',
            'relation' => $relation,
            'source' => trim((string) ($ownership['source'] ?? '')) ?: null,
            'confirmed_by_operator' => (bool) ($ownership['confirmed_by_operator'] ?? false),
            'legal_verification' => (bool) ($ownership['legal_verification'] ?? false),
        ];
    }

    private function mediaSummary(array $data): array
    {
        $findings = is_array($data['media_findings'] ?? null)
            ? $data['media_findings']
            : [];

        $categories = [];
        $titleDeed = false;
        $parcel = false;
        $listing = false;
        $propertyPhoto = false;

        foreach ($findings as $finding) {
            if (! is_array($finding)) {
                continue;
            }

            $category = $this->mediaCategory($finding);
            if ($category !== null) {
                $categories[] = $category;
            }

            $titleDeed = $titleDeed || $category === 'title_deed';
            $parcel = $parcel
                || in_array($category, ['title_deed', 'parcel_document'], true)
                || (
                    $this->filled($finding, 'block_no')
                    && $this->filled($finding, 'parcel_no')
                );
            $listing = $listing || $category === 'listing';
            $propertyPhoto = $propertyPhoto || $category === 'property_photo';
        }

        return [
            'analyzed_media_count' => count(array_filter($findings, 'is_array')),
            'categories' => array_values(array_unique($categories)),
            'has_title_deed_evidence' => $titleDeed,
            'has_parcel_evidence' => $parcel,
            'has_listing_evidence' => $listing,
            'has_property_photo' => $propertyPhoto,
        ];
    }

    private function mediaCategory(array $finding): ?string
    {
        $explicit = strtolower(trim((string) ($finding['media_category'] ?? '')));
        $allowed = [
            'title_deed', 'parcel_document', 'listing', 'property_photo',
            'location_map', 'other',
        ];

        if (in_array($explicit, $allowed, true)) {
            return $explicit;
        }

        $documentType = mb_strtolower(trim((string) ($finding['document_type'] ?? '')));

        if ($documentType === '') {
            return null;
        }

        if (str_contains($documentType, 'tapu')) {
            return 'title_deed';
        }
        if (str_contains($documentType, 'parsel') || str_contains($documentType, 'kadastro')) {
            return 'parcel_document';
        }
        if (str_contains($documentType, 'ilan')) {
            return 'listing';
        }
        if (str_contains($documentType, 'konum') || str_contains($documentType, 'harita')) {
            return 'location_map';
        }

        return 'other';
    }

    private function recommendedNextRequest(
        array $missingCritical,
        array $missingSupporting,
    ): ?string {
        $next = $missingCritical[0] ?? $missingSupporting[0] ?? null;

        return match ($next) {
            'property_type' => 'Taşınmazın arsa, tarla, daire veya başka hangi tür olduğunu paylaşır mısınız?',
            'city', 'location' => 'İl, ilçe/mahalle veya konum linkinden birini paylaşır mısınız?',
            'area_sqm' => 'Net ya da yaklaşık m² bilgisini paylaşır mısınız?',
            'asking_price' => 'Şu an düşündüğünüz satış fiyatı nedir?',
            'property_identity' => 'Konum linkini ya da varsa ada/parsel bilgisini gönderir misiniz?',
            'property_photo' => 'Taşınmazın birkaç güncel fotoğrafını gönderebilir misiniz?',
            'zoning_context' => 'Varsa imar bilgisini veya ilgili belgeyi paylaşabilir misiniz?',
            'title_deed_context' => 'Varsa tapu görselini kişisel bilgileri kapatarak gönderebilir misiniz?',
            'title_owner_context' => 'Tapu şu anda sizin üzerinize mi, yoksa eşiniz/yakınınız ya da başka bir kişi/şirket üzerine mi?',
            'listing_reference' => 'Varsa ilan linkini veya ilan görselini de paylaşabilir misiniz?',
            default => null,
        };
    }

    private function syncConversationState(RealEstateProfile $profile, array $summary): void
    {
        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return;
        }

        $status = trim((string) ($summary['status'] ?? 'early')) ?: 'early';
        $tags = collect($conversation->etiketler())
            ->filter(fn (string $tag): bool => ! str_starts_with($tag, self::TAG_PREFIX))
            ->push(self::TAG_PREFIX.$status)
            ->unique()
            ->values()
            ->all();

        $conversation->forceFill([
            'tags' => $tags,
            // This isolated product never schedules autonomous customer follow-ups.
            'next_follow_up_at' => null,
        ])->saveQuietly();
    }

    private function comparable(array $summary): array
    {
        unset($summary['updated_at']);

        return $summary;
    }

    private function filled(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return false;
        }

        return ! is_string($data[$key]) || trim($data[$key]) !== '';
    }

    private function positive(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }

    private function supports(RealEstateProfile $profile): bool
    {
        if (
            $profile->profile_type !== 'seller'
            || (int) $profile->user_id !== RealEstateIsolationService::USER_ID
            || (int) $profile->ai_bot_id !== RealEstateIsolationService::BOT_ID
        ) {
            return false;
        }

        $conversation = $profile->conversation()->first();

        return app(RealEstateIsolationService::class)
            ->supportsConversation($conversation);
    }
}
