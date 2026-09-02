<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\RealEstatePrivateMedia;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

    public function persistPrivateInboundMedia(
        ChatMessage $message,
        string $instanceName,
        array $mediaContext
    ): bool {
        if (
            (int) $message->user_id !== RealEstateIsolationService::USER_ID
            || (int) $message->organization_id !== RealEstateIsolationService::ORGANIZATION_ID
            || (int) $message->ai_bot_id !== RealEstateIsolationService::BOT_ID
            || ! in_array($message->message_type, ['image', 'document'], true)
        ) {
            return false;
        }

        $mime = strtolower(trim((string) ($mediaContext['mime_type'] ?? $message->media_mime_type)));
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (! isset($extensions[$mime])) {
            return false;
        }

        $base64 = $this->downloadBase64(
            instanceName: $instanceName,
            messageEnvelope: is_array($mediaContext['message_envelope'] ?? null)
                ? $mediaContext['message_envelope']
                : [],
        );
        $bytes = base64_decode($base64, true);

        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 15 * 1024 * 1024) {
            throw new Exception('WhatsApp medyası güvenli saklama sınırını aşıyor veya çözülemedi.');
        }

        $path = 'real-estate-inbound/'.$message->id.'/original.'.$extensions[$mime];

        RealEstatePrivateMedia::query()->updateOrCreate(
            ['chat_message_id' => $message->id],
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'real_estate_profile_id' => null,
                'media_key' => null,
                'mime_type' => $mime,
                'size' => strlen($bytes),
                'content_base64' => base64_encode($bytes),
            ]
        );

        // The local file is only a disposable serving cache. The database
        // copy above is the durable source so a Coolify redeploy cannot
        // silently break the CRM gallery.
        Storage::disk('local')->put($path, $bytes);

        $message->forceFill([
            'media_url' => 'private:'.$path,
            'media_mime_type' => $mime,
            'media_size' => strlen($bytes),
        ])->saveQuietly();

        return true;
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
