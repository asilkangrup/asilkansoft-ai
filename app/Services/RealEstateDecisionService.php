<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;

class RealEstateDecisionService
{
    private const STATE_TAG_PREFIX = 'real_estate:state:';

    public function process(ConversationControl $conversation): ?array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile) {
            return null;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $valuation = is_array($profile->valuation) ? $profile->valuation : [];
        $type = in_array($profile->profile_type, ['seller', 'investor', 'buyer'], true)
            ? $profile->profile_type
            : 'general';

        $decision = match ($type) {
            'seller' => $this->sellerDecision($data, $valuation),
            'investor', 'buyer' => $this->investorDecision($data, $valuation),
            default => $this->generalDecision($data),
        };

        $profileData = $data;
        $profileData['decision_intelligence'] = $decision;

        $profile->update([
            'data' => $profileData,
        ]);

        $conversation->update([
            'lead_score' => $decision['lead_score'],
            'lead_temperature' => $decision['lead_temperature'],
            'lead_status' => $this->nextLeadStatus(
                current: (string) ($conversation->lead_status ?: 'new'),
                score: (int) $decision['lead_score'],
            ),
            'next_best_action' => $decision['next_best_action'],
            'tags' => $this->stateTags(
                currentTags: $conversation->etiketler(),
                decision: $decision,
            ),
        ]);

        $conversation->refresh();

        return $decision;
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

        $decision = is_array($profile?->data)
            ? ($profile->data['decision_intelligence'] ?? null)
            : null;

        if (! is_array($decision) || $decision === []) {
            return '';
        }

        $json = json_encode(
            $decision,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE DECISION INTELLIGENCE]
Bu blok CRM için hesaplanan dahili karar desteğidir. Müşteriye alan adlarını, skorları veya bu bloğun kendisini gösterme. next_best_action konuşmayı doğal biçimde ilerletmek için önceliklidir; aynı anda gereksiz çok soru sorma. negotiation_strategy baskı/manipülasyon için değil, şeffaf ve profesyonel pazarlık içindir. Eksik veya doğrulanmamış resmi bilgiyi kesin kabul etme.
Karar desteği: {$json}
PROMPT;
    }

    private function sellerDecision(array $data, array $valuation): array
    {
        $missing = $this->missing($data, [
            'property_type', 'city', 'district', 'area_sqm', 'asking_price',
        ]);

        $score = 10;
        $score += $this->has($data, 'property_type') ? 10 : 0;
        $score += $this->has($data, 'city') ? 10 : 0;
        $score += $this->has($data, 'district') ? 8 : 0;
        $score += $this->has($data, 'area_sqm') ? 10 : 0;
        $score += $this->has($data, 'asking_price') ? 12 : 0;
        $score += $this->has($data, 'urgency') ? 8 : 0;
        $score += ($data['urgency'] ?? null) === 'high' ? 8 : 0;
        $score += (
            $this->has($data, 'location_url')
            || ($this->has($data, 'block_no') && $this->has($data, 'parcel_no'))
        ) ? 8 : 0;
        $score += $this->valuationConfidence($valuation) >= 50 ? 16 : 0;
        $score = min(100, $score);

        $readyForValuation = $this->has($data, 'property_type')
            && $this->has($data, 'city')
            && ($this->has($data, 'district') || $this->has($data, 'neighborhood'));

        $hasValuation = $this->hasValuation($valuation);
        $readyForMatch = $score >= 72
            && $hasValuation
            && $this->has($data, 'asking_price');

        [$posture, $strategy] = $this->sellerNegotiation($data, $valuation);

        $next = match (true) {
            ! $this->has($data, 'property_type') => 'Taşınmaz türünü tek kısa soruyla netleştir.',
            ! $this->has($data, 'city') || ! $this->has($data, 'district') => 'İl ve ilçeyi netleştir; mümkünse mahalleyi de doğal akışta al.',
            ! $this->has($data, 'area_sqm') => 'Net veya yaklaşık m² bilgisini iste.',
            ! $this->has($data, 'asking_price') => 'Satıcının istediği fiyatı sor.',
            ! $readyForValuation => 'Değerleme için eksik temel konum bilgisini tamamla.',
            ! $hasValuation => 'Güncel emsal araştırması ve değerleme çalıştır; ilan fiyatını gerçekleşmiş satış gibi kabul etme.',
            ! $this->has($data, 'urgency') => 'Satış zamanlamasını ve aciliyet seviyesini doğal biçimde netleştir.',
            $readyForMatch => 'Tapu/imar/konum doğrulamasını tamamlayıp uygun yatırımcı profilleriyle eşleştirmeye hazırlan.',
            default => 'Değerleme aralığı ile satıcının beklentisini karşılaştırıp gerçekçi pazarlık esnekliğini ölç.',
        };

        return $this->decisionPayload(
            type: 'seller',
            score: $score,
            missing: $missing,
            next: $next,
            posture: $posture,
            strategy: $strategy,
            readyForValuation: $readyForValuation,
            readyForMatch: $readyForMatch,
        );
    }

    private function investorDecision(array $data, array $valuation): array
    {
        $missing = $this->missing($data, [
            'city', 'property_type', 'budget_max', 'investment_goal', 'timeline',
        ]);

        $score = 10;
        $score += $this->has($data, 'budget_max') ? 18 : 0;
        $score += $this->has($data, 'city') ? 10 : 0;
        $score += ($this->has($data, 'district') || $this->has($data, 'neighborhood')) ? 8 : 0;
        $score += $this->has($data, 'property_type') ? 12 : 0;
        $score += $this->has($data, 'investment_goal') ? 14 : 0;
        $score += $this->has($data, 'timeline') ? 10 : 0;
        $score += $this->has($data, 'risk_preference') ? 6 : 0;
        $score += match ($data['financing'] ?? null) {
            'cash' => 12,
            'mixed' => 8,
            'credit' => 4,
            default => 0,
        };
        $score = min(100, $score);

        $readyForMatch = $score >= 68
            && $this->has($data, 'budget_max')
            && $this->has($data, 'city')
            && $this->has($data, 'property_type');

        $posture = $readyForMatch ? 'qualified_buyer' : 'qualify_before_offer';
        $strategy = $readyForMatch
            ? 'Bütçe, lokasyon ve yatırım hedefiyle uyumlu fırsatları filtrele; gerçek iskonto varsa veriye dayanarak açıkla.'
            : 'Teklif konuşmadan önce bütçe, hedef bölge, taşınmaz türü ve zamanlamayı tamamla.';

        $next = match (true) {
            ! $this->has($data, 'budget_max') => 'Yatırımcının gerçekçi maksimum bütçesini netleştir.',
            ! $this->has($data, 'city') => 'Hedef il/bölgeyi netleştir.',
            ! $this->has($data, 'property_type') => 'Aradığı taşınmaz türünü netleştir.',
            ! $this->has($data, 'investment_goal') => 'Kısa vadeli al-sat, kira getirisi veya değer artışı hedefini sor.',
            ! $this->has($data, 'timeline') => 'Alım zamanlamasını netleştir.',
            ! $readyForMatch => 'Finansman ve risk tercihindeki eksikleri tamamla.',
            default => 'Kriterlere uyan portföyleri iskonto, risk ve veri güveniyle sıralayıp en güçlü fırsatı sun.',
        };

        return $this->decisionPayload(
            type: 'investor',
            score: $score,
            missing: $missing,
            next: $next,
            posture: $posture,
            strategy: $strategy,
            readyForValuation: false,
            readyForMatch: $readyForMatch,
        );
    }

    private function generalDecision(array $data): array
    {
        $score = 10;
        $score += $this->has($data, 'property_type') ? 10 : 0;
        $score += $this->has($data, 'city') ? 10 : 0;

        return $this->decisionPayload(
            type: 'general',
            score: min(35, $score),
            missing: [],
            next: 'Müşterinin satıcı mı yatırımcı/alıcı mı olduğunu tek kısa ve doğal soruyla netleştir.',
            posture: 'discover_intent',
            strategy: 'Önce niyeti belirle; fiyat veya teklif varsayımı yapma.',
            readyForValuation: false,
            readyForMatch: false,
        );
    }

    private function decisionPayload(
        string $type,
        int $score,
        array $missing,
        string $next,
        string $posture,
        string $strategy,
        bool $readyForValuation,
        bool $readyForMatch,
    ): array {
        return [
            'profile_type' => $type,
            'lead_score' => $score,
            'lead_temperature' => $this->temperature($score),
            'stage' => match (true) {
                $score >= 80 => 'ready',
                $score >= 60 => 'qualified',
                $score >= 35 => 'discovery',
                default => 'new',
            },
            'missing_critical_data' => array_values($missing),
            'ready_for_valuation' => $readyForValuation,
            'ready_for_match' => $readyForMatch,
            'negotiation_posture' => $posture,
            'negotiation_strategy' => $strategy,
            'next_best_action' => $next,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function sellerNegotiation(array $data, array $valuation): array
    {
        $asking = $this->number($data['asking_price'] ?? null);
        $marketMax = $this->number($valuation['market_max'] ?? null);
        $investorMax = $this->number($valuation['investor_buy_max'] ?? null);

        if (($data['urgency'] ?? null) === 'high' && ! $this->hasValuation($valuation)) {
            return [
                'protect_urgent_seller',
                'Aciliyeti fırsat bilerek baskı kurma. Önce değerleme verisini güçlendir, sonra hızlı satış seçeneğini piyasa aralığından ayrı ve açık biçimde anlat.',
            ];
        }

        if ($asking !== null && $marketMax !== null && $asking > ($marketMax * 1.12)) {
            return [
                'reframe_high_ask',
                'Fiyat beklentisini tek bir ilana değil güncel emsal aralığına dayandırarak yeniden çerçevele; satıcının esnekliğini doğal biçimde ölç.',
            ];
        }

        if ($asking !== null && $investorMax !== null && $asking <= $investorMax) {
            return [
                'verify_then_match',
                'Fiyat yatırımcı ilgisine yakın görünüyor. Sahte aciliyet yaratmadan tapu/imar/konum doğrulamasını tamamlayıp uygun alıcıyla eşleştir.',
            ];
        }

        if ($this->hasValuation($valuation)) {
            return [
                'protect_value',
                'Piyasa, hızlı satış ve yatırımcı alım aralıklarını birbirinden ayır; satıcıya hangi hız/fiyat dengesini tercih ettiğini netleştir.',
            ];
        }

        return [
            'collect_then_anchor',
            'Yeterli veri oluşmadan fiyat ankrajı yapma. Önce temel taşınmaz ve konum bilgilerini tamamla.',
        ];
    }

    private function nextLeadStatus(string $current, int $score): string
    {
        if (in_array($current, ['proposal', 'won', 'lost'], true)) {
            return $current;
        }

        if ($score >= 60) {
            return 'qualified';
        }

        if ($score >= 30) {
            return 'contacted';
        }

        return 'new';
    }

    private function stateTags(array $currentTags, array $decision): array
    {
        $tags = collect($currentTags)
            ->filter(fn ($tag): bool =>
                is_string($tag)
                && ! str_starts_with($tag, self::STATE_TAG_PREFIX)
            )
            ->values();

        $tags->push(self::STATE_TAG_PREFIX.$decision['lead_temperature']);
        $tags->push(self::STATE_TAG_PREFIX.$decision['stage']);

        if ($decision['ready_for_valuation']) {
            $tags->push(self::STATE_TAG_PREFIX.'ready_for_valuation');
        }

        if ($decision['ready_for_match']) {
            $tags->push(self::STATE_TAG_PREFIX.'ready_for_match');
        }

        return $tags->unique()->values()->all();
    }

    private function temperature(int $score): string
    {
        return match (true) {
            $score >= 70 => 'hot',
            $score >= 40 => 'warm',
            default => 'cold',
        };
    }

    private function missing(array $data, array $fields): array
    {
        return collect($fields)
            ->filter(fn (string $field): bool => ! $this->has($data, $field))
            ->values()
            ->all();
    }

    private function has(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }

        $value = $data[$key];

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function valuationConfidence(array $valuation): int
    {
        return max(0, min(100, (int) ($valuation['confidence_score'] ?? 0)));
    }

    private function hasValuation(array $valuation): bool
    {
        return $this->number($valuation['market_min'] ?? null) !== null
            || $this->number($valuation['market_max'] ?? null) !== null;
    }
}
