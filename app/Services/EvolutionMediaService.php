<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class EvolutionMediaService
{
    public function downloadBase64(
        string $instanceName,
        array $messageEnvelope
    ): string {
        $url = rtrim((string) config('evolution.url'), '/');
        $apiKey = (string) config('evolution.api_key');

        if ($url === '' || $apiKey === '') {
            throw new Exception('Evolution API ayarları eksik.');
        }

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(180)
            ->post(
                "{$url}/chat/getBase64FromMediaMessage/{$instanceName}",
                [
                    'message' => $messageEnvelope,
                    'convertToMp4' => false,
                ]
            );

        if (! $response->successful()) {
            throw new Exception(
                'WhatsApp medyası indirilemedi: '.$response->body()
            );
        }

        $payload = $response->json();

        $base64 = data_get($payload, 'base64')
            ?? data_get($payload, 'data.base64')
            ?? data_get($payload, 'media.base64')
            ?? data_get($payload, 'data');

        if (! is_string($base64) || trim($base64) === '') {
            throw new Exception('Evolution API medya base64 verisi döndürmedi.');
        }

        $base64 = trim($base64);

        if (str_contains($base64, ';base64,')) {
            $base64 = explode(';base64,', $base64, 2)[1] ?? '';
        }

        if ($base64 === '') {
            throw new Exception('WhatsApp medya içeriği boş.');
        }

        return $base64;
    }
}
