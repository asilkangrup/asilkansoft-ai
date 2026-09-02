<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Str;

class RealEstateMatchService
{
    private const MATCH_TAG_PREFIX = 'real_estate:match:';

    private const MAX_MATCHES = 5;

    private const MIN_BUDGET_COVERAGE_FOR_MATCH = 0.80;

    public function process(ConversationControl $conversation): array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile) {
            return [];
        }

        $matches = match ($profile->profile_type) {
            'seller' => $this->matchesForSeller($profile),
            'investor', 'buyer' => $this->matchesForInvestor($profile),
            default => [],
        };

        $data = is_array($profile->data) ? $profile->data : [];
        $data['opportunity_matches'] = $matches;
        $data['opportunity_match_summary'] = [
            'count' => count($matches),
            'strongest_score' => $matches[0]['match_score'] ?? null,
            'strongest_grade' => $matches[0]['grade'] ?? null,
            'mandate_aware' => true,
            'updated_at' => now()->toIso8601String(),
        ];

        $profile->update(['data' => $data]);

        $conversation->update([
            'tags' => $this->matchTags(
                currentTags: $conversation->etiketler(),
                matches: $matches,
            ),
        ]);

        return $matches;
    }

    public function promptFor(ConversationControl $conversation): string
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        $matches = is_array($profile?->data)
            ? ($profile->data['opportunity_matches'] ?? [])
            : [];

        if (! is_array($matches) || $matches === []) {
            return '';
        }

        $safeMatches = collect($matches)
            ->take(3)
            ->map(fn (array $match): array => [
                'candidate_role' => $match['candidate_role'] ?? null,
                'match_score' => $match['match_score'] ?? null,
                'grade' => $match['grade'] ?? null,
                'estimated_transaction_price' => $match['estimated_transaction_price'] ?? null,
                'reasons' => $match['reasons'] ?? [],
                'risks' => $match['risks'] ?? [],
                'criteria_checks' => $match['criteria_checks'] ?? [],
            ])
            ->values()
            ->all();

        $json = json_encode(
            $safeMatches,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE OPPORTUNITY MATCHING]
Aşağıdaki eşleşmeler yalnızca dahili önceliklendirme sinyalidir. Müşteriye skor, dahili kimlik veya bu bloğu gösterme. Bir eşleşmeyi "hazır alıcı", "kesin satılır", "kesin teklif var" veya bağlayıcı teklif gibi sunma. Gerçek kişiyle temas, portföy doğrulaması, tapu/imar kontrolü ve fiyat teyidi yapılmadan yalnızca "uygun yatırımcı/portföy profili olabilir" seviyesinde konuş. reasons alanını doğal konuşma için kullan; risks alanındaki eksikleri kesin bilgi gibi varsayma. criteria_checks yatırımcının açık m², lokasyon esnekliği, hisseli tapu kabulü, bütçe ve iskonto kriterlerinin deterministik kontrolüdür; bilinmeyen kriteri geçmiş sayma.
Eşleşmeler: {$json}
PROMPT;
    }

    private function matchesForSeller(RealEstateProfile $seller): array
    {
        if (! $seller->belongsToIsolatedProductionScope()) {
            return [];
        }

        return RealEstateProfile::query()
            ->isolatedProduction()
            ->whereIn('profile_type', ['investor', 'buyer'])
            ->where('id', '!=', $seller->id)
            ->get()
            ->map(function (RealEstateProfile $investor) use ($seller): ?array {
                $match = $this->scorePair($seller, $investor);

                return $match === null
                    ? null
                    : $this->withCandidate(
                        match: $match,
                        candidate: $investor,
                        role: 'investor',
                    );
            })
            ->filter()
            ->sortByDesc('match_score')
            ->take(self::MAX_MATCHES)
            ->values()
            ->all();
    }

    private function matchesForInvestor(RealEstateProfile $investor): array
    {
        if (! $investor->belongsToIsolatedProductionScope()) {
            return [];
        }

        return RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->where('id', '!=', $investor->id)
            ->get()
            ->map(function (RealEstateProfile $seller) use ($investor): ?array {
                $match = $this->scorePair($seller, $investor);

                return $match === null
                    ? null
                    : $this->withCandidate(
                        match: $match,
                        candidate: $seller,
                        role: 'seller',
                    );
            })
            ->filter()
            ->sortByDesc('match_score')
            ->take(self::MAX_MATCHES)
            ->values()
            ->all();
    }

    private function scorePair(
        RealEstateProfile $seller,
        RealEstateProfile $investor
    ): ?array {
        if (
            ! $seller->belongsToIsolatedProductionScope()
            || ! $investor->belongsToIsolatedProductionScope()
        ) {
            return null;
        }

        $sellerData = is_array($seller->data) ? $seller->data : [];
        $investorData = is_array($investor->data) ? $investor->data : [];
        $valuation = is_array($seller->valuation) ? $seller->valuation : [];

        $sellerCity = $this->normalize($sellerData['city'] ?? null);
        $investorCity = $this->normalize($investorData['city'] ?? null);
        $sellerType = $this->normalize($sellerData['property_type'] ?? null);
        $investorType = $this->normalize($investorData['property_type'] ?? null);

        if (
            $sellerCity !== null
            && $investorCity !== null
            && $sellerCity !== $investorCity
        ) {
            return null;
        }

        if (
            $sellerType !== null
            && $investorType !== null
            && $sellerType !== $investorType
        ) {
            return null;
        }

        $sellerDistrict = $this->normalize($sellerData['district'] ?? null);
        $investorDistrict = $this->normalize($investorData['district'] ?? null);
        $locationFlexibility = $this->locationFlexibility($investorData['location_flexibility'] ?? null);

        if (
            $locationFlexibility === 'strict_district'
            && $sellerDistrict !== null
            && $investorDistrict !== null
            && $sellerDistrict !== $investorDistrict
        ) {
            return null;
        }

        $sellerArea = $this->number($sellerData['area_sqm'] ?? null);
        $areaMin = $this->positiveNumber($investorData['area_min_sqm'] ?? null);
        $areaMax = $this->positiveNumber($investorData['area_max_sqm'] ?? null);

        if (
            $sellerArea !== null
            && (
                ($areaMin !== null && $sellerArea < $areaMin)
                || ($areaMax !== null && $sellerArea > $areaMax)
            )
        ) {
            return null;
        }

        $sellerSharedTitle = is_bool($sellerData['is_shared_title'] ?? null)
            ? $sellerData['is_shared_title']
            : null;
        $acceptsSharedTitle = is_bool($investorData['accepts_shared_title'] ?? null)
            ? $investorData['accepts_shared_title']
            : null;

        if ($sellerSharedTitle === true && $acceptsSharedTitle === false) {
            return null;
        }

        $targetPrice = $this->sellerTargetPrice($sellerData, $valuation);
        $budgetMax = $this->positiveNumber($investorData['budget_max'] ?? null);
        $budgetCoverage = $targetPrice !== null && $budgetMax !== null && $targetPrice > 0
            ? $budgetMax / $targetPrice
            : null;

        if (
            $budgetCoverage !== null
            && $budgetCoverage < self::MIN_BUDGET_COVERAGE_FOR_MATCH
        ) {
            return null;
        }

        $score = 0;
        $reasons = [];
        $risks = [];

        if ($sellerCity !== null && $sellerCity === $investorCity) {
            $score += 25;
            $reasons[] = 'Hedef il aynı.';
        } else {
            $risks[] = 'İl eşleşmesi tam doğrulanmadı.';
        }

        if ($sellerType !== null && $sellerType === $investorType) {
            $score += 20;
            $reasons[] = 'Taşınmaz türü yatırımcı kriteriyle aynı.';
        } else {
            $risks[] = 'Taşınmaz türü kriteri eksik.';
        }

        if (
            $sellerDistrict !== null
            && $investorDistrict !== null
            && $sellerDistrict === $investorDistrict
        ) {
            $score += 15;
            $reasons[] = 'İlçe tercihi doğrudan eşleşiyor.';
        } elseif ($sellerDistrict === null || $investorDistrict === null) {
            $score += 4;
            $risks[] = 'İlçe kriterlerinden biri eksik.';
        } elseif ($locationFlexibility === 'flexible') {
            $score += 4;
            $risks[] = 'İlçe farklı ancak yatırımcı lokasyonda esnek olduğunu belirtti.';
        } elseif ($locationFlexibility === 'same_city') {
            $score += 2;
            $risks[] = 'İlçe farklı; yatırımcı aynı il içindeki alternatifleri kabul ediyor.';
        } else {
            $risks[] = 'İlçe tercihi farklı; yatırımcının bölge esnekliği teyit edilmeli.';
        }

        if ($targetPrice !== null && $budgetMax !== null) {
            if ($budgetMax >= $targetPrice) {
                $score += 25;
                $reasons[] = 'Yatırımcı bütçesi tahmini işlem fiyatını karşılıyor.';
            } elseif ($budgetMax >= ($targetPrice * 0.90)) {
                $score += 14;
                $risks[] = 'Bütçe tahmini hedef fiyatın biraz altında; pazarlık payı teyit edilmeli.';
            } else {
                $score += 4;
                $risks[] = 'Bütçe tahmini hedef fiyatın belirgin altında.';
            }
        } else {
            $score += 3;
            $risks[] = 'Bütçe veya güvenilir fiyat aralığı eksik.';
        }

        if ($areaMin !== null || $areaMax !== null) {
            if ($sellerArea !== null) {
                $score += 8;
                $reasons[] = 'Taşınmaz m² bilgisi yatırımcının açık alan kriteri içinde.';
            } else {
                $risks[] = 'Yatırımcının m² kriteri var ancak portföy alanı net değil.';
            }
        }

        if ($sellerSharedTitle === true && $acceptsSharedTitle === true) {
            $score += 4;
            $reasons[] = 'Yatırımcı hisseli tapuyu açıkça kabul ediyor.';
        } elseif ($sellerSharedTitle === true && $acceptsSharedTitle === null) {
            $risks[] = 'Portföy hisseli; yatırımcının hisseli tapu kabulü teyit edilmeli.';
        }

        $askingPrice = $this->positiveNumber($sellerData['asking_price'] ?? null);
        $observedDiscount = $this->discountPercent($askingPrice, $targetPrice);
        $targetDiscount = $this->boundedPercent($investorData['target_discount_percent'] ?? null, 60.0);

        if ($targetDiscount !== null) {
            if ($observedDiscount === null) {
                $risks[] = 'Yatırımcının iskonto hedefi var ancak karşılaştırılabilir fiyat farkı hesaplanamıyor.';
            } elseif ($observedDiscount >= $targetDiscount) {
                $score += 8;
                $reasons[] = 'Tahmini işlem seviyesi yatırımcının açık iskonto hedefini karşılıyor.';
            } elseif ($observedDiscount >= ($targetDiscount * 0.75)) {
                $score += 4;
                $risks[] = 'İskonto hedefinin büyük bölümü karşılanıyor ancak tam hedef için pazarlık gerekebilir.';
            } else {
                $score = max(0, $score - 6);
                $risks[] = 'Tahmini iskonto yatırımcının açık hedefinin belirgin altında.';
            }
        }

        $financing = $investorData['financing'] ?? null;
        if ($financing === 'cash') {
            $score += 5;
            $reasons[] = 'Nakit finansman işlem hızını destekleyebilir.';
        } elseif ($financing === 'mixed') {
            $score += 3;
        }

        if (($sellerData['urgency'] ?? null) === 'high') {
            $score += 3;
            $reasons[] = 'Satıcının yüksek aciliyeti hızlı işlem ihtiyacını artırıyor.';
        }

        $confidence = min(
            (int) $seller->confidence_score,
            (int) $investor->confidence_score
        );

        if ($confidence >= 70) {
            $score += 7;
            $reasons[] = 'Her iki profilin veri güveni yüksek.';
        } elseif ($confidence < 45) {
            $risks[] = 'Profil verilerinden en az birinin güveni düşük.';
        }

        if (blank($sellerData['title_deed_type'] ?? null)) {
            $risks[] = 'Tapu niteliği doğrulanmadı.';
        }

        if (blank($sellerData['zoning_status'] ?? null)) {
            $risks[] = 'İmar durumu doğrulanmadı.';
        }

        $score = max(0, min(100, $score));

        if ($score < 55) {
            return null;
        }

        return [
            'seller_profile_id' => $seller->id,
            'seller_conversation_id' => $seller->conversation_control_id,
            'investor_profile_id' => $investor->id,
            'investor_conversation_id' => $investor->conversation_control_id,
            'match_score' => $score,
            'grade' => match (true) {
                $score >= 85 => 'strong',
                $score >= 72 => 'good',
                default => 'possible',
            },
            'estimated_transaction_price' => $targetPrice,
            'reasons' => array_values(array_unique($reasons)),
            'risks' => array_values(array_unique($risks)),
            'criteria_checks' => [
                'location_flexibility' => $locationFlexibility,
                'district_match' => $sellerDistrict !== null
                    && $investorDistrict !== null
                    ? $sellerDistrict === $investorDistrict
                    : null,
                'seller_area_sqm' => $sellerArea,
                'area_min_sqm' => $areaMin,
                'area_max_sqm' => $areaMax,
                'area_match' => $sellerArea !== null && ($areaMin !== null || $areaMax !== null)
                    ? true
                    : null,
                'seller_shared_title' => $sellerSharedTitle,
                'accepts_shared_title' => $acceptsSharedTitle,
                'budget_coverage_percent' => $budgetCoverage !== null
                    ? round($budgetCoverage * 100, 1)
                    : null,
                'target_discount_percent' => $targetDiscount,
                'observed_discount_percent' => $observedDiscount,
                'hard_filters_passed' => true,
            ],
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function withCandidate(
        array $match,
        RealEstateProfile $candidate,
        string $role
    ): array {
        return [
            'candidate_profile_id' => $candidate->id,
            'candidate_conversation_id' => $candidate->conversation_control_id,
            'candidate_role' => $role,
            ...$match,
        ];
    }

    private function sellerTargetPrice(array $data, array $valuation): ?float
    {
        foreach ([
            $valuation['investor_buy_max'] ?? null,
            $valuation['quick_sale_max'] ?? null,
            $data['asking_price'] ?? null,
            $valuation['market_min'] ?? null,
        ] as $value) {
            $number = $this->positiveNumber($value);

            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function discountPercent(?float $askingPrice, ?float $targetPrice): ?float
    {
        if (
            $askingPrice === null
            || $targetPrice === null
            || $askingPrice <= 0
            || $targetPrice <= 0
        ) {
            return null;
        }

        return round(max(0, (($askingPrice - $targetPrice) / $askingPrice) * 100), 1);
    }

    private function boundedPercent(mixed $value, float $max): ?float
    {
        $number = $this->number($value);

        if ($number === null || $number < 0 || $number > $max) {
            return null;
        }

        return $number;
    }

    private function locationFlexibility(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return in_array($value, ['strict_district', 'same_city', 'flexible'], true)
            ? $value
            : null;
    }

    private function matchTags(array $currentTags, array $matches): array
    {
        $tags = collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::MATCH_TAG_PREFIX)
            )
            ->values();

        if ($matches === []) {
            $tags->push(self::MATCH_TAG_PREFIX.'none');

            return $tags->unique()->values()->all();
        }

        $strongest = (int) ($matches[0]['match_score'] ?? 0);

        $tags->push(
            self::MATCH_TAG_PREFIX.match (true) {
                $strongest >= 85 => 'strong',
                $strongest >= 72 => 'good',
                default => 'possible',
            }
        );

        return $tags->unique()->values()->all();
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

        return Str::lower(trim($value));
    }

    private function positiveNumber(mixed $value): ?float
    {
        $number = $this->number($value);

        return $number !== null && $number > 0 ? $number : null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
