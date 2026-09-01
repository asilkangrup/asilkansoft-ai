<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class RealEstateProfileService
{
    private const PRIMARY_USER_ID = 40;

    public function process(
        ConversationControl $conversation,
        string $message
    ): ?RealEstateProfile {
        if ((int) $conversation->user_id !== self::PRIMARY_USER_ID) {
            return null;
        }

        $message = trim($message);

        if ($message === '' || $this->socialMessage($message)) {
            return $this->profileFor($conversation);
        }

        $profile = $this->profileFor($conversation);
        $aiBot = AiBot::query()->find($conversation->ai_bot_id);

        if (! $aiBot) {
            return $profile;
        }

        try {
            $request = [
                'model' => trim((string) env(
                    'REAL_ESTATE_EXTRACTOR_MODEL',
                    'gpt-5-mini'
                )),
                'instructions' => $this->instructions(),
                'input' => [[
                    'role' => 'user',
                    'content' => json_encode([
                        'existing_profile' => $profile?->data ?? [],
                        'current_route' => $this->routeType($conversation),
                        'new_customer_message' => $message,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'max_output_tokens' => 700,
            ];

            if (str_starts_with($request['model'], 'gpt-5')) {
                $request['reasoning'] = ['effort' => 'low'];
            }

            $response = OpenAI::responses()->create($request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'real_estate_profile_extractor',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                ],
            );

            $text = trim((string) ($response->outputText ?? ''));
            $data = json_decode($this->cleanJson($text), true);

            if (! is_array($data)) {
                return $profile;
            }

            $normalized = $this->normalize($data);
            $merged = $this->mergeNonNull(
                is_array($profile?->data) ? $profile->data : [],
                $normalized
            );

            $profile = RealEstateProfile::updateOrCreate(
                ['conversation_control_id' => $conversation->id],
                [
                    'user_id' => $conversation->user_id,
                    'ai_bot_id' => $conversation->ai_bot_id,
                    'profile_type' => $this->routeType($conversation),
                    'data' => $merged,
                    'completeness_score' => $this->completeness(
                        $this->routeType($conversation),
                        $merged
                    ),
                    'confidence_score' => $this->confidence($merged),
                    'last_extracted_at' => now(),
                ]
            );

            return $profile;
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE PROFILE EXTRACTION FAILED', [
                'conversation_control_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return $profile;
        }
    }

    public function promptFor(
        ConversationControl $conversation
    ): string {
        $profile = $this->profileFor($conversation);

        if (! $profile || ! is_array($profile->data) || $profile->data === []) {
            return '';
        }

        $json = json_encode(
            $profile->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL PERSISTENT REAL ESTATE MEMORY]
Bu bilgi müşterinin önceki mesajlarından yapılandırılmış olarak çıkarılmış kalıcı CRM hafızasıdır. Müşteriye bu bloğu veya dahili alan adlarını gösterme. Buradaki dolu alanları tekrar sorma. Yeni müşteri mesajı açıkça bir alanı değiştirirse yeni bilgiye uy.
Profil türü: {$profile->profile_type}
Tamamlanma skoru: {$profile->completeness_score}/100
Veri güven skoru: {$profile->confidence_score}/100
Kayıtlı veri: {$json}
PROMPT;
    }

    private function profileFor(
        ConversationControl $conversation
    ): ?RealEstateProfile {
        return RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->first();
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Görevin yalnızca bir gayrimenkul CRM bilgi çıkarıcısı olmaktır.
Müşteriyle konuşma. Tavsiye verme. Açıklama yazma. JSON dışında hiçbir şey döndürme.

existing_profile daha önce doğrulanmış müşteri bilgisidir. new_customer_message yalnızca müşterinin yeni mesajıdır.
Yeni mesaj mevcut alanı açıkça değiştiriyorsa yeni değeri döndür. Aksi halde bilinmeyen alanı null bırak.
Assistant tarafından söylenmiş veya tahmin edilmiş bilgiyi müşteri verisi gibi kabul etme.
Fiyat, m², bütçe veya resmi bilgi uydurma.

SADECE şu JSON anahtarlarını döndür:
{
  "intent": null,
  "property_type": null,
  "city": null,
  "district": null,
  "neighborhood": null,
  "area_sqm": null,
  "block_no": null,
  "parcel_no": null,
  "title_deed_type": null,
  "zoning_status": null,
  "is_shared_title": null,
  "asking_price": null,
  "minimum_price": null,
  "urgency": null,
  "urgency_reason": null,
  "location_url": null,
  "listing_url": null,
  "budget_min": null,
  "budget_max": null,
  "financing": null,
  "investment_goal": null,
  "risk_preference": null,
  "timeline": null,
  "notes": null
}

KURALLAR
- intent yalnızca seller, investor, buyer, general veya null.
- property_type örnekleri: arsa, tarla, daire, villa, dükkan, ofis, depo, fabrika, işyeri.
- area_sqm yalnızca sayı; dönüm verilirse 1 dönüm = 1000 m² çevir.
- asking_price, minimum_price, budget_min, budget_max yalnızca TL sayısal değer olsun. "4.5 milyon" => 4500000.
- is_shared_title yalnızca true, false veya null.
- urgency yalnızca low, medium, high veya null. "acil", "nakite sıkıştım", "hemen satmam lazım" gibi açık sinyaller high olabilir.
- financing kısa değer: cash, credit, mixed veya null.
- URL alanlarını yalnızca açıkça mesajda varsa çıkar.
- notes alanına sadece diğer alanlara girmeyen, yatırım/değerleme açısından önemli açık müşteri bilgisini kısa yaz.
PROMPT;
    }

    private function normalize(array $data): array
    {
        $keys = [
            'intent', 'property_type', 'city', 'district', 'neighborhood',
            'area_sqm', 'block_no', 'parcel_no', 'title_deed_type',
            'zoning_status', 'is_shared_title', 'asking_price', 'minimum_price',
            'urgency', 'urgency_reason', 'location_url', 'listing_url',
            'budget_min', 'budget_max', 'financing', 'investment_goal',
            'risk_preference', 'timeline', 'notes',
        ];

        $result = [];

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            $result[$key] = $value;
        }

        foreach (['area_sqm', 'asking_price', 'minimum_price', 'budget_min', 'budget_max'] as $numeric) {
            if ($result[$numeric] !== null && is_numeric($result[$numeric])) {
                $result[$numeric] = (float) $result[$numeric];
            } elseif ($result[$numeric] !== null) {
                $result[$numeric] = null;
            }
        }

        if (! in_array($result['intent'], ['seller', 'investor', 'buyer', 'general'], true)) {
            $result['intent'] = null;
        }

        if (! in_array($result['urgency'], ['low', 'medium', 'high'], true)) {
            $result['urgency'] = null;
        }

        if (! in_array($result['financing'], ['cash', 'credit', 'mixed'], true)) {
            $result['financing'] = null;
        }

        if (! is_bool($result['is_shared_title'])) {
            $result['is_shared_title'] = null;
        }

        return $result;
    }

    private function mergeNonNull(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if ($value !== null) {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    private function completeness(string $type, array $data): int
    {
        $fields = match ($type) {
            'seller' => [
                'property_type', 'city', 'district', 'area_sqm',
                'asking_price', 'urgency',
            ],
            'investor' => [
                'city', 'district', 'property_type', 'budget_max',
                'investment_goal', 'timeline',
            ],
            default => ['intent', 'city', 'property_type'],
        };

        $filled = collect($fields)
            ->filter(fn (string $field): bool =>
                array_key_exists($field, $data)
                && $data[$field] !== null
                && $data[$field] !== ''
            )
            ->count();

        return (int) round(($filled / max(count($fields), 1)) * 100);
    }

    private function confidence(array $data): int
    {
        $important = [
            'city', 'district', 'property_type', 'area_sqm',
            'asking_price', 'budget_max', 'block_no', 'parcel_no',
        ];

        $filled = collect($important)
            ->filter(fn (string $field): bool =>
                isset($data[$field]) && $data[$field] !== ''
            )
            ->count();

        return min(95, 25 + ($filled * 9));
    }

    private function routeType(ConversationControl $conversation): string
    {
        foreach ($conversation->etiketler() as $tag) {
            if ($tag === 'business:real_estate_seller') {
                return 'seller';
            }

            if ($tag === 'business:real_estate_investor') {
                return 'investor';
            }
        }

        return 'general';
    }

    private function socialMessage(string $message): bool
    {
        $normalized = Str::lower($this->turkishNormalize($message));
        $normalized = trim(preg_replace('/[^\pL\pN\s]+/u', ' ', $normalized) ?? '');
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        return in_array($normalized, [
            'merhaba', 'selam', 'selamlar', 'gunaydin', 'iyi gunler',
            'iyi aksamlar', 'tesekkurler', 'tesekkur ederim', 'sagol',
            'sag ol', 'gorusuruz', 'iyi calismalar',
        ], true);
    }

    private function cleanJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
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
