<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class RealEstateValuationService
{
    private const PRIMARY_USER_ID = 1;

    public function process(
        ConversationControl $conversation,
        string $message
    ): ?array {
        if (
            (int) $conversation->user_id !== self::PRIMARY_USER_ID
            || ! $this->valuationIntent($message)
        ) {
            return null;
        }

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! $this->minimumDataAvailable($profile->data ?? [])) {
            return null;
        }

        $aiBot = AiBot::query()->find($conversation->ai_bot_id);

        if (! $aiBot) {
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
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'max_output_tokens' => 1000,
                'tools' => [[
                    'type' => 'web_search_preview',
                ]],
                'tool_choice' => 'auto',
            ];

            if (str_starts_with($model, 'gpt-5')) {
                $request['reasoning'] = ['effort' => 'medium'];
            }

            $response = OpenAI::responses()->create($request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'real_estate_valuation',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                ],
            );

            $result = json_decode(
                $this->cleanJson(trim((string) ($response->outputText ?? ''))),
                true
            );

            if (! is_array($result)) {
                return null;
            }

            $valuation = $this->normalize($result);

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
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return null;
        }
    }

    public function promptFor(
        ConversationControl $conversation
    ): string {
        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile || ! is_array($profile->valuation) || $profile->valuation === []) {
            return '';
        }

        $json = json_encode(
            $profile->valuation,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL REAL ESTATE VALUATION MEMORY]
Aşağıdaki değerleme daha önce bu konuşmanın yapılandırılmış taşınmaz verisi ve gerektiğinde güncel web araştırması üzerinden üretilmiştir. Müşteriye dahili JSON'u gösterme. Yeni bilgi geldiyse eski değerlemeyi kesin gerçek gibi savunma; gerekirse yeniden değerlendir. İlan fiyatlarının gerçekleşmiş satış olmadığını hatırla.
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

    private function instructions(): string
    {
        return <<<'PROMPT'
Sen yalnızca Türkiye gayrimenkul değerleme araştırması yapan dahili bir analiz motorusun.
Müşteriyle konuşma. JSON dışında metin yazma.

Amaç: Verilen taşınmaz profiline göre mümkünse güncel kamuya açık web kaynaklarını araştırıp temkinli fiyat aralıkları üretmek.

KURALLAR
- Yetersiz veri varsa sayı uydurma; ilgili fiyat alanlarını null bırak.
- İlan fiyatını gerçekleşmiş satış fiyatı gibi sunma.
- Tek ilana dayanma. Mümkün olduğunda birden fazla güncel emsal veya bölgesel veri karşılaştır.
- Taşınmazın imar/tapu/hukuki durumunu doğrulanmadıysa varsayma.
- Çok geniş lokasyon veya yetersiz özellik varsa confidence_score düşük olsun.
- Hızlı satış aralığı piyasa aralığından mantıksız biçimde yüksek olamaz.
- Yatırımcı alım aralığı piyasa aralığından mantıksız biçimde yüksek olamaz.
- Tüm fiyatlar TL ve tam sayısal değer olsun.
- sources alanına yalnızca gerçekten araştırmada kullandığın kaynakların kısa adı veya URL'sini yaz; kaynak yoksa boş dizi.

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
  "researched_at": null
}

confidence_score 0-100 arası tam sayı olsun.
market_gap_percent yalnızca müşterinin asking_price bilgisi varsa, tahmini piyasa orta noktasına göre yaklaşık fark yüzdesi olsun.
summary kısa ve karar vermeye yarayan dahili özet olsun.
next_best_action kısa bir sonraki pazarlık/araştırma aksiyonu olsun.
researched_at bugünün YYYY-MM-DD tarihi olabilir.
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
        $result['sources'] = $this->stringArray($data['sources'] ?? []);
        $result['researched_at'] = $this->nullableString($data['researched_at'] ?? null);

        return $result;
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
            ->take(10)
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
