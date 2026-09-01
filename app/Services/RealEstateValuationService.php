<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RealEstateValuationService
{
    public function process(
        ConversationControl $conversation,
        string $message
    ): ?array {
        $isolation = app(RealEstateIsolationService::class);

        if (
            ! $isolation->supportsConversation($conversation)
            || ! $this->valuationIntent($message)
        ) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! $this->minimumDataAvailable($profile->data ?? [])) {
            return null;
        }

        $freshnessService = app(RealEstateValuationFreshnessService::class);
        $freshness = $freshnessService->refreshMetadata($profile);

        if (
            ($freshness['usable_for_decision'] ?? false)
            && ! $this->forceRefreshIntent($message)
        ) {
            return is_array($profile->fresh()->valuation)
                ? $profile->fresh()->valuation
                : null;
        }

        $aiBot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->first();

        if (! $isolation->supportsProductionBot($aiBot)) {
            return null;
        }

        try {
            $model = trim((string) env(
                'REAL_ESTATE_VALUATION_MODEL',
                'gpt-5.4'
            ));

            $request = [
                'model' => $model,
                'instructions' => $this->instructions(),
                'input' => [[
                    'role' => 'user',
                    'content' => json_encode([
                        'property_profile' => $profile->data ?? [],
                        'customer_question' => trim($message),
                        'previous_valuation' => $profile->valuation ?? [],
                        'previous_freshness' => $freshness,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'max_output_tokens' => 1500,
                'tools' => [[
                    'type' => 'web_search_preview',
                ]],
                'tool_choice' => 'auto',
            ];

            if (str_starts_with($model, 'gpt-5')) {
                $request['reasoning'] = ['effort' => 'medium'];
            }

            $response = app(RealEstateOpenAIClient::class)
                ->createResponse($aiBot, $request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'real_estate_valuation',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                    'dedicated_api_key' => true,
                    'forced_refresh' => $this->forceRefreshIntent($message),
                    'previous_freshness_status' => $freshness['status'] ?? null,
                ],
            );

            $result = json_decode(
                $this->cleanJson(trim((string) ($response->outputText ?? ''))),
                true
            );

            if (! is_array($result)) {
                return null;
            }

            $valuation = $freshnessService->stamp(
                profile: $profile,
                valuation: $this->normalize($result),
            );

            $profile->update([
                'valuation' => $valuation,
                'confidence_score' => max(
                    (int) $profile->confidence_score,
                    (int) ($valuation['confidence_score'] ?? 0)
                ),
            ]);

            return $valuation;
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE VALUATION FAILED', [
                'conversation_control_id' => $conversation->id,
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return null;
        }
    }

    public function promptFor(
        ConversationControl $conversation
    ): string {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return '';
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! is_array($profile->valuation) || $profile->valuation === []) {
            return '';
        }

        $freshness = app(RealEstateValuationFreshnessService::class)->assess($profile);

        if (! ($freshness['usable_for_decision'] ?? false)) {
            $reasonJson = json_encode(
                $freshness['reasons'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            return <<<PROMPT
[INTERNAL STALE REAL ESTATE VALUATION]
Önceki bir değerleme kaydı var ancak artık güncel karar desteği için güvenilir kabul edilmemelidir. Eski fiyat aralıklarını müşteriye güncel gerçek gibi aktarma, eşleşme veya pazarlık ankrajı olarak kullanma. Yeni değerleme isteniyorsa güncel emsal araştırmasını yeniden çalıştır.
Geçersizlik nedenleri: {$reasonJson}
PROMPT;
        }

        $json = json_encode(
            $profile->valuation,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL FRESH REAL ESTATE VALUATION MEMORY]
Aşağıdaki değerleme bu taşınmazın güncel yapılandırılmış verisiyle eşleşen, süre kontrollü araştırma kaydıdır. Müşteriye dahili JSON'u veya freshness alanlarını gösterme. İlan/emsal fiyatlarının gerçekleşmiş satış fiyatı olmadığını açıkça ayır. Kaynak ve emsal kalitesi düşükse kesinlik dilini azalt. Yeni temel taşınmaz bilgisi gelirse bu değerlemeyi otomatik olarak eski kabul et.
Değerleme: {$json}
PROMPT;
    }

    private function minimumDataAvailable(array $data): bool
    {
        return filled($data['city'] ?? null)
            && filled($data['property_type'] ?? null)
            && (
                filled($data['district'] ?? null)
                || filled($data['neighborhood'] ?? null)
            );
    }

    private function valuationIntent(string $message): bool
    {
        $normalized = Str::lower($this->turkishNormalize($message));

        foreach ([
            'kac para eder', 'ne kadar eder', 'degeri ne', 'degeri nedir',
            'kaca gider', 'kaca satilir', 'kaca satariz', 'emsal',
            'piyasa fiyati', 'hizli satis', 'yatirimci kaca', 'kaca alir',
            'fiyat bic', 'fiyatlama', 'degerleme',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function forceRefreshIntent(string $message): bool
    {
        $normalized = Str::lower($this->turkishNormalize($message));

        foreach ([
            'guncel', 'bugun', 'yeniden', 'tekrar arastir', 'tekrar bak',
            'son durum', 'simdi ne kadar', 'su an ne kadar', 'yeni emsal',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sen yalnızca Türkiye gayrimenkul değerleme araştırması yapan dahili bir analiz motorusun.
Müşteriyle konuşma. JSON dışında metin yazma.

Amaç: Verilen taşınmaz profiline göre güncel kamuya açık web kaynaklarını araştırıp temkinli fiyat aralıkları ve doğrulanabilir emsal özeti üretmek.

KURALLAR
- Yetersiz veri varsa sayı uydurma; ilgili fiyat alanlarını null bırak.
- Web araştırması yapmadan güncel piyasa fiyatı üretme.
- İlan fiyatını gerçekleşmiş satış fiyatı gibi sunma. comparables içindeki listing_price yalnızca ilan/istenen fiyatıdır.
- Tek ilana dayanma. Mümkün olduğunda en az 2, tercihen 3+ güncel ve benzer emsal karşılaştır.
- Taşınmazın imar/tapu/hukuki durumunu doğrulanmadıysa varsayma.
- Çok geniş lokasyon, az emsal veya yetersiz özellik varsa confidence_score düşük olsun.
- Hızlı satış aralığı piyasa aralığından mantıksız biçimde yüksek olamaz.
- Yatırımcı alım aralığı piyasa aralığından mantıksız biçimde yüksek olamaz.
- Tüm fiyatlar TL ve sayısal değer olsun.
- sources alanına yalnızca gerçekten araştırmada kullandığın URL veya kaynak adını yaz; kaynak kullanmadıysan boş dizi.
- comparables alanına yalnızca gerçekten web araştırmasında gördüğün emsalleri ekle. URL, fiyat veya m² uydurma.
- observed_at için kaynak sayfasında tarih açıkça görünüyorsa YYYY-MM-DD yaz, görünmüyorsa null bırak.
- Aynı ilanı/URL'yi birden fazla emsal gibi çoğaltma.

SADECE şu JSON yapısını döndür:
{
  "market_min": null,
  "market_max": null,
  "quick_sale_min": null,
  "quick_sale_max": null,
  "investor_buy_min": null,
  "investor_buy_max": null,
  "confidence_score": 0,
  "market_gap_percent": null,
  "summary": null,
  "next_best_action": null,
  "missing_data": [],
  "sources": [],
  "comparables": [
    {
      "source": null,
      "url": null,
      "listing_price": null,
      "area_sqm": null,
      "location": null,
      "property_type": null,
      "observed_at": null
    }
  ]
}

confidence_score 0-100 arası tam sayı olsun.
market_gap_percent yalnızca müşterinin asking_price bilgisi varsa tahmini piyasa orta noktasına göre yaklaşık fark yüzdesi olsun.
summary kısa ve karar vermeye yarayan dahili özet olsun.
next_best_action kısa bir sonraki pazarlık/araştırma aksiyonu olsun.
PROMPT;
    }

    private function normalize(array $data): array
    {
        $numericFields = [
            'market_min', 'market_max', 'quick_sale_min', 'quick_sale_max',
            'investor_buy_min', 'investor_buy_max', 'market_gap_percent',
        ];

        $result = [];

        foreach ($numericFields as $field) {
            $value = $data[$field] ?? null;
            $result[$field] = is_numeric($value) ? (float) $value : null;
        }

        $result['confidence_score'] = max(
            0,
            min(100, (int) ($data['confidence_score'] ?? 0))
        );
        $result['summary'] = $this->nullableString($data['summary'] ?? null);
        $result['next_best_action'] = $this->nullableString($data['next_best_action'] ?? null);
        $result['missing_data'] = $this->stringArray($data['missing_data'] ?? []);
        $result['comparables'] = $this->comparableArray($data['comparables'] ?? []);

        $sources = $this->stringArray($data['sources'] ?? []);

        foreach ($result['comparables'] as $comparable) {
            if (filled($comparable['url'] ?? null)) {
                $sources[] = $comparable['url'];
            }
        }

        $result['sources'] = array_values(array_unique($sources));
        $result['comparable_stats'] = $this->comparableStats($result['comparables']);
        $result['research_basis'] = $result['sources'] === []
            ? 'no_verified_sources'
            : 'web_search';

        return $result;
    }

    private function comparableArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach (array_slice($value, 0, 8) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $listingPrice = is_numeric($item['listing_price'] ?? null)
                ? (float) $item['listing_price']
                : null;
            $area = is_numeric($item['area_sqm'] ?? null)
                ? (float) $item['area_sqm']
                : null;
            $unitPrice = $listingPrice !== null
                && $listingPrice > 0
                && $area !== null
                && $area > 0
                    ? round($listingPrice / $area, 2)
                    : null;

            $comparable = [
                'source' => $this->nullableString($item['source'] ?? null),
                'url' => $this->nullableString($item['url'] ?? null),
                'listing_price' => $listingPrice,
                'area_sqm' => $area,
                'unit_price_sqm' => $unitPrice,
                'location' => $this->nullableString($item['location'] ?? null),
                'property_type' => $this->nullableString($item['property_type'] ?? null),
                'observed_at' => $this->nullableString($item['observed_at'] ?? null),
                'price_basis' => 'asking',
            ];

            if (
                $comparable['url'] === null
                && $comparable['listing_price'] === null
                && $comparable['location'] === null
            ) {
                continue;
            }

            $result[] = $comparable;
        }

        return collect($result)
            ->unique(fn (array $item): string =>
                (string) ($item['url'] ?? '')
                .'|'.(string) ($item['listing_price'] ?? '')
                .'|'.(string) ($item['location'] ?? '')
            )
            ->values()
            ->all();
    }

    private function comparableStats(array $comparables): array
    {
        $unitPrices = collect($comparables)
            ->pluck('unit_price_sqm')
            ->filter(fn ($value): bool => is_numeric($value) && (float) $value > 0)
            ->map(fn ($value): float => (float) $value)
            ->sort()
            ->values();

        if ($unitPrices->isEmpty()) {
            return [
                'count' => count($comparables),
                'priced_per_sqm_count' => 0,
                'unit_price_min' => null,
                'unit_price_median' => null,
                'unit_price_max' => null,
                'price_basis' => 'asking',
            ];
        }

        $count = $unitPrices->count();
        $middle = intdiv($count, 2);
        $median = $count % 2 === 1
            ? $unitPrices[$middle]
            : (($unitPrices[$middle - 1] + $unitPrices[$middle]) / 2);

        return [
            'count' => count($comparables),
            'priced_per_sqm_count' => $count,
            'unit_price_min' => round((float) $unitPrices->first(), 2),
            'unit_price_median' => round((float) $median, 2),
            'unit_price_max' => round((float) $unitPrices->last(), 2),
            'price_basis' => 'asking',
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function stringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_scalar($item))
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function cleanJson(string $text): string
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text)) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        return trim($text);
    }

    private function turkishNormalize(string $text): string
    {
        return strtr($text, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);
    }
}
