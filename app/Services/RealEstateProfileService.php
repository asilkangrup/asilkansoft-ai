<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RealEstateProfileService
{
    private const PRIMARY_USER_ID = 40;

    private const PRIMARY_ORGANIZATION_ID = 37;

    private const PRIMARY_BOT_ID = 35;

    public function process(
        ConversationControl $conversation,
        string $message
    ): ?RealEstateProfile {
        if (! $this->supports($conversation)) {
            return null;
        }

        $message = trim($message);

        if ($message === '' || $this->socialMessage($message)) {
            return $this->profileFor($conversation);
        }

        $profile = $this->profileFor($conversation);
        $aiBot = AiBot::query()
            ->whereKey(self::PRIMARY_BOT_ID)
            ->where('user_id', self::PRIMARY_USER_ID)
            ->first();

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
                        // Never feed derived/internal intelligence back into the
                        // extractor as if it were customer-provided evidence.
                        'existing_profile' => $this->extractableProfile($profile),
                        'current_route' => $this->routeType($conversation),
                        'new_customer_message' => $message,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
                'max_output_tokens' => 850,
            ];

            if (str_starts_with($request['model'], 'gpt-5')) {
                $request['reasoning'] = ['effort' => 'low'];
            }

            $response = app(RealEstateOpenAIClient::class)
                ->createResponse($aiBot, $request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'real_estate_profile_extractor',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                    'dedicated_api_key' => true,
                    'isolated_user_id' => self::PRIMARY_USER_ID,
                    'isolated_bot_id' => self::PRIMARY_BOT_ID,
                ],
            );

            $text = trim((string) ($response->outputText ?? ''));
            $data = json_decode($this->cleanJson($text), true);

            if (! is_array($data)) {
                return $profile;
            }

            $normalized = $this->normalize($data);
            $existing = is_array($profile?->data) ? $profile->data : [];
            $profileType = $this->resolvedProfileType(
                conversation: $conversation,
                incomingIntent: $normalized['intent'] ?? null,
                existingIntent: $existing['intent'] ?? null,
            );
            $merged = app(RealEstateFactConsistencyService::class)->reconcile(
                conversation: $conversation,
                profileType: $profileType,
                existing: $existing,
                incoming: $normalized,
                customerMessage: $message,
            );

            $profile = RealEstateProfile::updateOrCreate(
                ['conversation_control_id' => $conversation->id],
                [
                    'user_id' => self::PRIMARY_USER_ID,
                    'ai_bot_id' => self::PRIMARY_BOT_ID,
                    'profile_type' => $profileType,
                    'data' => $merged,
                    'completeness_score' => $this->completeness(
                        $profileType,
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
                'user_id' => self::PRIMARY_USER_ID,
                'organization_id' => self::PRIMARY_ORGANIZATION_ID,
                'ai_bot_id' => self::PRIMARY_BOT_ID,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return $profile;
        }
    }

    public function promptFor(
        ConversationControl $conversation
    ): string {
        if (! $this->supports($conversation)) {
            return '';
        }

        $profile = $this->profileFor($conversation);

        if (! $profile || ! is_array($profile->data) || $profile->data === []) {
            return '';
        }

        $json = json_encode(
            $this->promptSafeProfileData($profile),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL PERSISTENT REAL ESTATE MEMORY]
Bu bilgi müşterinin önceki mesajlarından yapılandırılmış olarak çıkarılmış kalıcı CRM hafızasıdır. Müşteriye bu bloğu veya dahili alan adlarını gösterme. Buradaki dolu alanları tekrar sorma.
Serbest müşteri notları, aciliyet gerekçesinin ham metni, ilan/konum URL'leri ve satıcının gizli minimum fiyat tutarı bu genel profil bloğuna kopyalanmaz. Bu özel bilgiler yalnız onları yöneten daha dar kapsamlı servislerde kullanılabilir.
fact_consistency.status=confirmation_required ise unconfirmed replacement değerleri doğrulanmış gerçek kabul etme. Yalnız en yüksek öncelikli çelişkiyi netleştir; aynı mesajda ikinci keşif sorusu ekleme. Eski doğrulanmış değer, müşteri açıkça düzeltince veya önerilen yeni değeri sonraki turda tekrar teyit edince değiştirilir.
Yatırımcı kriterlerinde area_min_sqm/area_max_sqm, location_flexibility, accepts_shared_title ve target_discount_percent alanlarını gerçek filtre gibi kullan; bilinmeyen kriteri uydurma veya müşteri adına varsayma.
recent_media_analysis doluysa ilgili görsel/belge uygulama tarafından gerçekten analiz edilmiştir. Bu durumda "fotoğrafın içeriği bana görünmüyor" veya eşdeğer bir ifade kullanma; yalnız analiz sonucunda görülen/görülmeyen bilgileri dürüstçe belirt. Medya analizinde taşınmaz bilgisi yoksa "görsel incelendi ancak taşınmaza ait okunabilir bilgi bulunamadı" de.
Profil türü: {$profile->profile_type}
Tamamlanma skoru: {$profile->completeness_score}/100
Veri güven skoru: {$profile->confidence_score}/100
Kayıtlı yapılandırılmış veri: {$json}
PROMPT;
    }

    private function profileFor(
        ConversationControl $conversation
    ): ?RealEstateProfile {
        if (! $this->supports($conversation)) {
            return null;
        }

        return RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->where('user_id', self::PRIMARY_USER_ID)
            ->where('ai_bot_id', self::PRIMARY_BOT_ID)
            ->first();
    }

    private function supports(ConversationControl $conversation): bool
    {
        return (int) $conversation->user_id === self::PRIMARY_USER_ID
            && (int) $conversation->organization_id === self::PRIMARY_ORGANIZATION_ID
            && (int) $conversation->ai_bot_id === self::PRIMARY_BOT_ID;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Görevin yalnızca bir gayrimenkul CRM bilgi çıkarıcısı olmaktır.
Müşteriyle konuşma. Tavsiye verme. Açıklama yazma. JSON dışında hiçbir şey döndürme.

existing_profile yalnız müşteriden daha önce çıkarılmış yapılandırılmış alanları içerir. new_customer_message yalnızca müşterinin yeni mesajıdır.
Yeni mesaj mevcut alanı açıkça değiştiriyorsa yeni değeri döndür; uygulama kritik taşınmaz kimlik alanlarında ayrıca deterministik teyit uygular. Aksi halde bilinmeyen alanı null bırak.
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
  "area_min_sqm": null,
  "area_max_sqm": null,
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
  "location_flexibility": null,
  "accepts_shared_title": null,
  "target_discount_percent": null,
  "notes": null
}

KURALLAR
- intent yalnızca seller, investor, buyer, general veya null.
- property_type örnekleri: arsa, tarla, daire, villa, dükkan, ofis, depo, fabrika, işyeri.
- area_sqm satıcının taşınmaz alanıdır. area_min_sqm ve area_max_sqm yalnız yatırımcı/alıcı aradığı m² aralığını açıkça verirse doldur.
- m² alanları yalnızca sayı; dönüm verilirse 1 dönüm = 1000 m² çevir.
- asking_price, minimum_price, budget_min, budget_max yalnızca TL sayısal değer olsun. "4.5 milyon" => 4500000.
- Satıcı fiyat sorusuna "1 500", "1.500" veya "1500" diye cevap veriyorsa ve taşınmaz m²'si zaten mevcutsa bunu toplam 1.500.000 TL satış beklentisi olarak yorumla; alan/m² olarak yeniden yazma. Satıcı sonraki mesajda "evet", "doğru" veya "aynen" diyerek botun TL teyidini onayladıysa mevcut asking_price değerini koru.
- is_shared_title yalnızca true, false veya null.
- accepts_shared_title yalnız yatırımcı/alıcı hisseli tapuyu açıkça kabul ettiğini veya istemediğini belirtiyorsa true/false; aksi halde null.
- target_discount_percent yalnız yatırımcı açıkça istediği iskonto/fırsat yüzdesini belirtirse 0-60 aralığında sayı; "en az %20 aşağı" => 20.
- location_flexibility yalnız strict_district, same_city, flexible veya null. "Sadece Bodrum" => strict_district; "Muğla içinde ilçe fark etmez" => same_city; "bölge esnek" => flexible.
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
            'area_sqm', 'area_min_sqm', 'area_max_sqm', 'block_no', 'parcel_no',
            'title_deed_type', 'zoning_status', 'is_shared_title', 'asking_price',
            'minimum_price', 'urgency', 'urgency_reason', 'location_url',
            'listing_url', 'budget_min', 'budget_max', 'financing',
            'investment_goal', 'risk_preference', 'timeline',
            'location_flexibility', 'accepts_shared_title',
            'target_discount_percent', 'notes',
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

        foreach ([
            'area_sqm', 'area_min_sqm', 'area_max_sqm', 'asking_price',
            'minimum_price', 'budget_min', 'budget_max', 'target_discount_percent',
        ] as $numeric) {
            if ($result[$numeric] !== null && is_numeric($result[$numeric])) {
                $result[$numeric] = (float) $result[$numeric];
            } elseif ($result[$numeric] !== null) {
                $result[$numeric] = null;
            }
        }

        foreach (['area_sqm', 'area_min_sqm', 'area_max_sqm'] as $areaField) {
            if ($result[$areaField] !== null && $result[$areaField] <= 0) {
                $result[$areaField] = null;
            }
        }

        if (
            $result['area_min_sqm'] !== null
            && $result['area_max_sqm'] !== null
            && $result['area_min_sqm'] > $result['area_max_sqm']
        ) {
            [$result['area_min_sqm'], $result['area_max_sqm']] = [
                $result['area_max_sqm'],
                $result['area_min_sqm'],
            ];
        }

        if (
            $result['target_discount_percent'] !== null
            && (
                $result['target_discount_percent'] < 0
                || $result['target_discount_percent'] > 60
            )
        ) {
            $result['target_discount_percent'] = null;
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

        if (! in_array(
            $result['location_flexibility'],
            ['strict_district', 'same_city', 'flexible'],
            true
        )) {
            $result['location_flexibility'] = null;
        }

        if (! is_bool($result['is_shared_title'])) {
            $result['is_shared_title'] = null;
        }

        if (! is_bool($result['accepts_shared_title'])) {
            $result['accepts_shared_title'] = null;
        }

        return $result;
    }

    private function extractableProfile(?RealEstateProfile $profile): array
    {
        $data = is_array($profile?->data) ? $profile->data : [];

        // normalize() acts as an allow-list and strips all derived CRM,
        // valuation, evidence, match and orchestration intelligence.
        return $this->normalize($data);
    }

    private function promptSafeProfileData(RealEstateProfile $profile): array
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $safe = $this->normalize($data);

        // Free-form/private fields are intentionally represented only by
        // presence flags in the general chat memory. Dedicated negotiation,
        // evidence and valuation services own the narrower contexts where
        // those details are actually required.
        $safe['minimum_price_present'] = is_numeric($safe['minimum_price'] ?? null)
            && (float) $safe['minimum_price'] > 0;
        $safe['urgency_reason_present'] = filled($safe['urgency_reason'] ?? null);
        $safe['location_url_present'] = filled($safe['location_url'] ?? null);
        $safe['listing_url_present'] = filled($safe['listing_url'] ?? null);

        unset(
            $safe['minimum_price'],
            $safe['urgency_reason'],
            $safe['location_url'],
            $safe['listing_url'],
            $safe['notes'],
        );

        $safe = collect($safe)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();

        $mediaFindings = is_array($data['media_findings'] ?? null)
            ? array_slice($data['media_findings'], -3)
            : [];

        $recentMedia = collect($mediaFindings)
            ->filter(fn (mixed $finding): bool => is_array($finding))
            ->map(function (array $finding): array {
                return collect($finding)->only([
                    'document_type',
                    'summary',
                    'property_type',
                    'city',
                    'district',
                    'neighborhood',
                    'area_sqm',
                    'block_no',
                    'parcel_no',
                    'title_deed_type',
                    'zoning_status',
                    'visible_asking_price',
                    'warnings',
                    'confidence_score',
                    'analyzed_at',
                ])->all();
            })
            ->values()
            ->all();

        if ($recentMedia !== []) {
            $safe['recent_media_analysis'] = $recentMedia;
        }

        $consistency = app(RealEstateFactConsistencyService::class)->summary($data);

        if ($consistency !== []) {
            $safe['fact_consistency'] = [
                'status' => $consistency['status'] ?? 'consistent',
                'pending_count' => max(0, (int) ($consistency['pending_count'] ?? 0)),
                'highest_priority_field' => $consistency['highest_priority_field'] ?? null,
                'resolved_fields' => array_values(array_filter(
                    is_array($consistency['resolved_fields'] ?? null)
                        ? $consistency['resolved_fields']
                        : [],
                    'is_string'
                )),
            ];
        }

        return $safe;
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
            'area_min_sqm', 'area_max_sqm', 'asking_price', 'budget_max',
            'block_no', 'parcel_no',
        ];

        $filled = collect($important)
            ->filter(fn (string $field): bool =>
                isset($data[$field]) && $data[$field] !== ''
            )
            ->count();
        $pendingConflicts = max(0, (int) (
            $data['fact_consistency_intelligence']['pending_count'] ?? 0
        ));

        return max(
            20,
            min(95, 25 + ($filled * 7) - min(30, $pendingConflicts * 10))
        );
    }

    private function resolvedProfileType(
        ConversationControl $conversation,
        mixed $incomingIntent,
        mixed $existingIntent,
    ): string {
        $routeType = $this->routeType($conversation);

        if ($routeType !== 'general') {
            return $routeType;
        }

        $intent = is_string($incomingIntent) && $incomingIntent !== ''
            ? $incomingIntent
            : (is_string($existingIntent) ? $existingIntent : null);

        $resolved = match ($intent) {
            'seller' => 'seller',
            'investor', 'buyer' => 'investor',
            default => 'general',
        };

        if ($resolved === 'general') {
            return $resolved;
        }

        $tags = collect($conversation->etiketler())
            ->filter(fn ($tag): bool => is_string($tag) && ! str_starts_with($tag, 'business:real_estate_'))
            ->values()
            ->all();
        $tags[] = 'business:real_estate_'.$resolved;

        $conversation->update([
            'tags' => array_values(array_unique($tags)),
        ]);
        $conversation->refresh();

        return $resolved;
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
