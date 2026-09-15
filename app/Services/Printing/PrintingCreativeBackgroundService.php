<?php

namespace App\Services\Printing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PrintingCreativeBackgroundService
{
    public function enabled(): bool
    {
        return (bool) config('matbaa.enabled')
            && filled(config('matbaa.api_key'))
            && (bool) config('matbaa.creative_backgrounds', true);
    }

    public function generate(array $brief, ?array $referenceAsset = null): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $prompt = $this->prompt($brief);
            $model = (string) config('matbaa.image_model', 'gpt-image-2.5-sunburst');
            $quality = (string) config('matbaa.image_quality', 'medium');

            $response = $referenceAsset !== null
                ? $this->editFromReference($referenceAsset, $model, $quality, $prompt)
                : $this->generateFromPrompt($model, $quality, $prompt);

            if (! $response->successful()) {
                Log::warning('PRINTING CREATIVE BACKGROUND API FAILED', [
                    'status' => $response->status(),
                    'model' => $model,
                    'has_reference' => $referenceAsset !== null,
                ]);
                return null;
            }

            $encoded = trim((string) data_get($response->json(), 'data.0.b64_json', ''));
            return $encoded !== '' ? $encoded : null;
        } catch (Throwable $exception) {
            Log::warning('PRINTING CREATIVE BACKGROUND FAILED', [
                'message' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    private function generateFromPrompt(string $model, string $quality, string $prompt)
    {
        return $this->client()->post('https://api.openai.com/v1/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => $quality,
            'n' => 1,
        ]);
    }

    private function editFromReference(array $referenceAsset, string $model, string $quality, string $prompt)
    {
        $encoded = trim((string) ($referenceAsset['base64'] ?? ''));
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }
        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '') {
            return $this->generateFromPrompt($model, $quality, $prompt);
        }

        $mime = strtolower(trim((string) ($referenceAsset['mime'] ?? 'image/jpeg')));
        $extension = $mime === 'image/png' ? 'png' : 'jpg';

        return $this->client()
            ->asMultipart()
            ->attach('image[]', $bytes, 'reference.'.$extension, ['Content-Type' => $mime])
            ->post('https://api.openai.com/v1/images/edits', [
                'model' => $model,
                'prompt' => $prompt,
                'size' => '1536x1024',
                'quality' => $quality,
            ]);
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('matbaa.api_key'))
            ->acceptJson()
            ->timeout(180);
    }

    private function prompt(array $brief): string
    {
        $brand = trim((string) ($brief['brand_name'] ?? 'brand'));
        $style = trim((string) ($brief['style'] ?? 'modern'));
        $sector = trim((string) ($brief['sector'] ?? ''));
        $colors = trim((string) ($brief['colors'] ?? ''));
        $referenceTone = trim((string) ($brief['reference_tone'] ?? ''));

        $details = array_filter([
            "visual direction: {$style}",
            $sector !== '' ? "industry context: {$sector}" : null,
            $colors !== '' ? "preferred palette: {$colors}" : null,
            $referenceTone !== '' ? "reference tone: {$referenceTone}" : null,
        ]);

        return 'Create a premium flat graphic background for a professional business card. '
            .'No text, no letters, no numbers, no logos, no mockup, no photographed card. '
            .'The final typography will be overlaid programmatically, so preserve generous negative space and clean hierarchy. '
            .'Use restrained print-ready visual language, elegant geometry, subtle depth, and commercially credible design. '
            .'Do not reproduce identifiable objects or branding from a reference image; only borrow its broad palette, mood, and visual rhythm. '
            .'Brand context: '.$brand.'. '.implode('; ', $details).'.';
    }
}
