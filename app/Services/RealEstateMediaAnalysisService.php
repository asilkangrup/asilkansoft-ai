<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealEstateMediaAnalysisService
{
    private const PRIMARY_USER_ID = 40;

    public function process(
        ConversationControl $conversation,
        string $instanceName,
        array $mediaContext
    ): ?array {
        if ((int) $conversation->user_id !== self::PRIMARY_USER_ID) {
            return null;
        }

        $type = strtolower(trim((string) ($mediaContext['type'] ?? '')));
        $mime = strtolower(trim((string) ($mediaContext['mime_type'] ?? '')));

        $supported = $type === 'image'
            || ($type === 'document' && $mime === 'application/pdf');

        if (! $supported) {
            return null;
        }

        $messageEnvelope = $mediaContext['message_envelope'] ?? null;

        if (! is_array($messageEnvelope) || $messageEnvelope === []) {
            return null;
        }

        try {
            $base64 = app(EvolutionMediaService::class)->downloadBase64(
                instanceName: $instanceName,
                messageEnvelope: $messageEnvelope,
            );

            $aiBot = AiBot::query()->find($conversation->ai_bot_id);

            if (! $aiBot) {
                return null;
            }

            $content = [[
                'type' => 'input_text',
                'text' => $this->analysisPrompt(
                    caption: (string) ($mediaContext['caption'] ?? ''),
                    filename: (string) ($mediaContext['filename'] ?? '')
                ),
            ]];

            if ($type === 'image') {
                $imageMime = $mime !== '' ? $mime : 'image/jpeg';
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => "data:{$imageMime};base64,{$base64}",
                    'detail' => 'high',
                ];
            } else {
                $content[] = [
                    'type' => 'input_file',
                    'filename' => trim((string) ($mediaContext['filename'] ?? 'belge.pdf')) ?: 'belge.pdf',
                    'file_data' => $base64,
                ];
            }

            $model = trim((string) env(
                'REAL_ESTATE_MEDIA_MODEL',
                'gpt-5.4'
            ));

            $request = [
                'model' => $model,
                'instructions' => $this->instructions(),
                'input' => [[
                    'role' => 'user',
                    'content' => $content,
                ]],
                'max_output_tokens' => 1000,
            ];

            if (str_starts_with($model, 'gpt-5')) {
                $request['reasoning'] = ['effort' => 'medium'];
            }

            $response = app(RealEstateOpenAIClient::class)
                ->createResponse($aiBot, $request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'real_estate_media_analysis',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                    'message_type' => $type,
                    'mime_type' => $mime,
                    'dedicated_api_key' => true,
                ],
            );

            $decoded = json_decode(
                $this->cleanJson(trim((string) ($response->outputText ?? ''))),
                true
            );

            if (! is_array($decoded)) {
                return null;
            }

            $analysis = $this->normalize($decoded, $mediaContext);
            $this->persist($conversation, $analysis);

            return $analysis;
        } catch (Throwable $exception) {
            Log::warning('REAL ESTATE MEDIA ANALYSIS FAILED', [
                'conversation_control_id' => $conversation->id,
                'type' => $type,
                'mime_type' => $mime,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return null;
        }
    }

    private function persist(
        ConversationControl $conversation,
        array $analysis
    ): void {
        $profile = RealEstateProfile::firstOrCreate(
            ['conversation_control_id' => $conversation->id],
            [
                'user_id' => $conversation->user_id,
                'ai_bot_id' => $conversation->ai_bot_id,
                'profile_type' => 'general',
                'data' => [],
                'valuation' => [],
                'completeness_score' => 0,
                'confidence_score' => 0,
            ]
        );

        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null)
            ? $data['media_findings']
            : [];

        $findings[] = $analysis;
        $data['media_findings'] = array_slice($findings, -8);

        $profile->update([
            'data' => $data,
            'confidence_score' => max(
                (int) $profile->confidence_score,
                (int) ($analysis['confidence_score'] ?? 0)
            ),
        ]);
    }

    private function analysisPrompt(string $caption, string $filename): string
    {
        return 'Dosya adı: '.($filename !== '' ? $filename : 'bilinmiyor')
            ."\nWhatsApp açıklaması: ".($caption !== '' ? $caption : 'yok')
            ."\nBu medya bir gayrimenkul görüşmesinde gönderildi. Belgedeki/görseldeki yalnızca açıkça görülebilen bilgileri çıkar.";
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sen yalnızca gayrimenkul görsel ve belge analiz motorusun.
Müşteriyle konuşma. JSON dışında hiçbir metin yazma.

Bir tapu, parsel ekran görüntüsü, ilan ekran görüntüsü, konum görseli veya başka gayrimenkul belgesi olabilir.
Yalnızca gerçekten okunabilen/görülebilen bilgiyi çıkar. Tahmin etme.
Belirsiz alanı null bırak.
Bir belgenin resmi/geçerli olduğunu yalnız görüntüden garanti etme.
T.C. kimlik numarası, seri no gibi gereksiz kişisel kimlik bilgilerini çıkarmaya çalışma veya saklama.

SADECE şu JSON yapısını döndür:
{
  "document_type": null,
  "summary": null,
  "property_type": null,
  "city": null,
  "district": null,
  "neighborhood": null,
  "area_sqm": null,
  "block_no": null,
  "parcel_no": null,
  "title_deed_type": null,
  "zoning_status": null,
  "owner_share": null,
  "visible_asking_price": null,
  "listing_title": null,
  "warnings": [],
  "confidence_score": 0
}

confidence_score 0-100 arası olsun.
summary en fazla 3 kısa cümle olsun.
warnings yalnızca gerçekten önemli belirsizlik/riskleri içersin.
PROMPT;
    }

    private function normalize(array $data, array $mediaContext): array
    {
        $numeric = function (mixed $value): ?float {
            return is_numeric($value) ? (float) $value : null;
        };

        return [
            'message_id' => trim((string) ($mediaContext['message_id'] ?? '')) ?: null,
            'filename' => trim((string) ($mediaContext['filename'] ?? '')) ?: null,
            'mime_type' => trim((string) ($mediaContext['mime_type'] ?? '')) ?: null,
            'document_type' => $this->nullable($data['document_type'] ?? null),
            'summary' => $this->nullable($data['summary'] ?? null),
            'property_type' => $this->nullable($data['property_type'] ?? null),
            'city' => $this->nullable($data['city'] ?? null),
            'district' => $this->nullable($data['district'] ?? null),
            'neighborhood' => $this->nullable($data['neighborhood'] ?? null),
            'area_sqm' => $numeric($data['area_sqm'] ?? null),
            'block_no' => $this->nullable($data['block_no'] ?? null),
            'parcel_no' => $this->nullable($data['parcel_no'] ?? null),
            'title_deed_type' => $this->nullable($data['title_deed_type'] ?? null),
            'zoning_status' => $this->nullable($data['zoning_status'] ?? null),
            'owner_share' => $this->nullable($data['owner_share'] ?? null),
            'visible_asking_price' => $numeric($data['visible_asking_price'] ?? null),
            'listing_title' => $this->nullable($data['listing_title'] ?? null),
            'warnings' => $this->strings($data['warnings'] ?? []),
            'confidence_score' => max(0, min(100, (int) ($data['confidence_score'] ?? 0))),
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_scalar($item))
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
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
}
