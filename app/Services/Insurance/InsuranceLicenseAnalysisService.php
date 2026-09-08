<?php

namespace App\Services\Insurance;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\InsuranceCase;
use App\Models\InsuranceEvent;
use App\Services\AiUsageService;
use App\Services\EvolutionMediaService;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class InsuranceLicenseAnalysisService
{
    public function analyze(
        AiBot $aiBot,
        ConversationControl $conversation,
        string $instanceName,
        array $messageEnvelope,
        string $messageType,
        ?string $mimeType = null,
        ?string $caption = null,
    ): array {
        if (! in_array($messageType, ['image', 'document'], true)) {
            throw new RuntimeException('Ruhsat analizi yalnız görsel veya belge mesajlarında çalışır.');
        }

        $mime = strtolower(trim((string) $mimeType));
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Bu dosya türü ruhsat analizi için desteklenmiyor.');
        }

        $base64 = app(EvolutionMediaService::class)->downloadBase64(
            instanceName: $instanceName,
            messageEnvelope: $messageEnvelope,
        );

        $binary = base64_decode($base64, true);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Ruhsat medyası çözülemedi.');
        }

        if (strlen($binary) > 10 * 1024 * 1024) {
            throw new RuntimeException('Ruhsat dosyası 10 MB sınırını aşıyor.');
        }
        unset($binary);

        $content = [[
            'type' => 'input_text',
            'text' => 'Bu belge bir araç ruhsatı olabilir. Yalnızca araç ve poliçe teklifinde gereken alanları çıkar. Kişi adı, T.C. kimlik numarası, adres gibi kişisel alanları kesinlikle döndürme. Görünmeyen alanı tahmin etme. Caption: '.mb_substr(trim((string) $caption), 0, 300),
        ]];

        if (str_starts_with($mime, 'image/')) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => "data:{$mime};base64,{$base64}",
                'detail' => 'high',
            ];
        } else {
            $content[] = [
                'type' => 'input_file',
                'filename' => 'arac-ruhsati.pdf',
                'file_data' => $base64,
            ];
        }

        $model = trim((string) ($aiBot->openai_model ?: env('INSURANCE_MEDIA_MODEL', 'gpt-5-mini')));

        $request = [
            'model' => $model,
            'instructions' => $this->instructions(),
            'input' => [[
                'role' => 'user',
                'content' => $content,
            ]],
            'max_output_tokens' => 700,
        ];

        if (str_starts_with($model, 'gpt-5')) {
            $request['reasoning'] = ['effort' => 'low'];
        }

        try {
            $response = OpenAI::responses()->create($request);

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'insurance_license_analysis',
                aiBot: $aiBot,
                meta: [
                    'conversation_control_id' => $conversation->id,
                    'mime_type' => $mime,
                ],
            );

            $decoded = json_decode($this->cleanJson((string) ($response->outputText ?? '')), true);
            if (! is_array($decoded)) {
                throw new RuntimeException('Ruhsat analiz cevabı geçerli JSON değil.');
            }

            return $this->normalize($decoded);
        } catch (Throwable $e) {
            Log::warning('INSURANCE LICENSE ANALYSIS FAILED', [
                'ai_bot_id' => $aiBot->id,
                'conversation_control_id' => $conversation->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function persistToCase(
        AiBot $aiBot,
        ConversationControl $conversation,
        array $analysis,
    ): InsuranceCase {
        $case = InsuranceCase::query()
            ->where('conversation_control_id', $conversation->id)
            ->active()
            ->latest()
            ->first();

        $payload = [
            'organization_id' => $conversation->organization_id,
            'customer_name' => $conversation->customer_name,
            'phone' => $conversation->whatsapp_number,
            'policy_type' => 'TRAFIK',
            'plate' => $analysis['plate'],
            'license_number' => $analysis['license_number'],
            'motor_number' => $analysis['motor_number'],
            'chassis_number' => $analysis['chassis_number'],
            'vehicle_brand' => $analysis['brand'],
            'vehicle_model' => $analysis['model'],
            'vehicle_year' => $analysis['year'],
            'source_channel' => 'whatsapp',
            'source_reference' => $conversation->session_id,
        ];

        if (! $case) {
            $case = app(InsuranceWorkflowService::class)->createCase($payload, null);
            $case->forceFill([
                'user_id' => $aiBot->user_id,
                'conversation_control_id' => $conversation->id,
                'assigned_user_id' => null,
            ])->save();
        } else {
            $updates = [];
            foreach ([
                'plate' => 'plate',
                'license_number' => 'license_number',
                'motor_number' => 'motor_number',
                'chassis_number' => 'chassis_number',
                'brand' => 'vehicle_brand',
                'model' => 'vehicle_model',
                'year' => 'vehicle_year',
            ] as $source => $target) {
                if (filled($analysis[$source] ?? null)) {
                    $updates[$target] = $analysis[$source];
                }
            }

            $updates['status'] = collect(['plate','motor_number','chassis_number','license_number'])
                ->contains(fn ($field) => filled($updates[$field] ?? $case->{$field}))
                    ? 'ready_for_open'
                    : 'waiting_vehicle';
            $updates['integration_status'] = app(OpenHizliTeklifClient::class)->configured() ? 'ready' : 'credentials_pending';
            $case->forceFill($updates)->save();
        }

        $data = is_array($case->data) ? $case->data : [];
        $data['latest_license_analysis'] = $analysis;
        $case->forceFill(['data' => $data])->save();

        InsuranceEvent::create([
            'insurance_case_id' => $case->id,
            'type' => 'license_analyzed',
            'title' => 'Ruhsat bilgileri analiz edildi',
            'meta' => [
                'confidence' => $analysis['confidence'],
                'document_type' => $analysis['document_type'],
                'warnings' => $analysis['warnings'],
            ],
        ]);

        return $case->refresh();
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Sen sigorta acentesi için araç ruhsatı veri çıkarım motorusun.
Yalnız belgeden açıkça okunabilen araç bilgilerini çıkar.
Kişi adı, T.C. kimlik numarası, adres, doğum tarihi ve benzeri kişisel bilgileri ASLA çıktıya ekleme.
Belirsiz karakterleri tahmin etme; alan okunmuyorsa null döndür.

Yalnız şu JSON şemasını döndür, başka metin yazma:
{
  "document_type": "vehicle_license|other|unclear",
  "plate": null,
  "license_number": null,
  "motor_number": null,
  "chassis_number": null,
  "brand": null,
  "model": null,
  "year": null,
  "usage_type": null,
  "confidence": 0,
  "warnings": []
}
confidence 0-100 arası tam sayı olsun.
PROMPT;
    }

    private function normalize(array $data): array
    {
        $value = fn (string $key): ?string => filled($data[$key] ?? null)
            ? trim((string) $data[$key])
            : null;

        $year = is_numeric($data['year'] ?? null) ? (int) $data['year'] : null;
        if ($year !== null && ($year < 1900 || $year > (int) date('Y') + 1)) {
            $year = null;
        }

        return [
            'document_type' => in_array(($data['document_type'] ?? null), ['vehicle_license','other','unclear'], true)
                ? $data['document_type']
                : 'unclear',
            'plate' => $value('plate') ? strtoupper((string) preg_replace('/\s+/', '', $value('plate'))) : null,
            'license_number' => $value('license_number'),
            'motor_number' => $value('motor_number'),
            'chassis_number' => $value('chassis_number'),
            'brand' => $value('brand'),
            'model' => $value('model'),
            'year' => $year,
            'usage_type' => $value('usage_type'),
            'confidence' => max(0, min(100, (int) ($data['confidence'] ?? 0))),
            'warnings' => collect($data['warnings'] ?? [])->filter(fn ($v) => is_scalar($v))->map(fn ($v) => mb_substr((string) $v,0,180))->values()->all(),
        ];
    }

    private function cleanJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        return trim($text);
    }
}
