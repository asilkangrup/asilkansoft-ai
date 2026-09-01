<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class EvolutionMediaService
{
    private string $url;
    private string $apiKey;

    public function __construct()
    {
        $this->url = rtrim((string) config('evolution.url'), '/');
        $this->apiKey = (string) config('evolution.api_key');
    }

    public function downloadBase64(
        string $instanceName,
        array $messageEnvelope
    ): string {
        $this->assertConfigured();

        $response = $this->requestBase64(
            instanceName: $instanceName,
            messageEnvelope: $messageEnvelope,
        );

        $base64 = $response->successful()
            ? $this->extractBase64($response->json())
            : null;

        if ($base64 !== null) {
            return $base64;
        }

        $messageId = trim((string) data_get($messageEnvelope, 'key.id', ''));

        if ($messageId !== '') {
            $fullMessage = $this->findMessageById(
                instanceName: $instanceName,
                messageId: $messageId,
            );

            if ($fullMessage !== null) {
                $retry = $this->requestBase64(
                    instanceName: $instanceName,
                    messageEnvelope: $fullMessage,
                );

                if ($retry->successful()) {
                    $base64 = $this->extractBase64($retry->json());

                    if ($base64 !== null) {
                        return $base64;
                    }
                }
            }
        }

        throw new Exception(
            'WhatsApp medyası Evolution API üzerinden çözülemedi.'
        );
    }

    private function requestBase64(
        string $instanceName,
        array $messageEnvelope
    ) {
        return $this->client()->post(
            "{$this->url}/chat/getBase64FromMediaMessage/{$instanceName}",
            [
                'message' => $messageEnvelope,
                'convertToMp4' => false,
            ]
        );
    }

    private function findMessageById(
        string $instanceName,
        string $messageId
    ): ?array {
        $response = $this->client()->post(
            "{$this->url}/chat/findMessages/{$instanceName}",
            [
                'where' => [
                    'key' => [
                        'id' => $messageId,
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        $candidates = [];

        if (is_array($payload) && array_is_list($payload)) {
            $candidates = $payload;
        } else {
            foreach ([
                'messages.records',
                'records',
                'data',
                'messages',
            ] as $path) {
                $value = data_get($payload, $path);

                if (is_array($value) && array_is_list($value)) {
                    $candidates = $value;
                    break;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (
                is_array($candidate)
                && (string) data_get($candidate, 'key.id', '') === $messageId
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractBase64(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $base64 = data_get($payload, 'base64')
            ?? data_get($payload, 'data.base64')
            ?? data_get($payload, 'media.base64')
            ?? data_get($payload, 'data');

        if (! is_string($base64) || trim($base64) === '') {
            return null;
        }

        $base64 = trim($base64);

        if (str_contains($base64, ';base64,')) {
            $base64 = explode(';base64,', $base64, 2)[1] ?? '';
        }

        return $base64 !== '' ? $base64 : null;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(180);
    }

    private function assertConfigured(): void
    {
        if ($this->url === '' || $this->apiKey === '') {
            throw new Exception('Evolution API ayarları eksik.');
        }
    }
}
