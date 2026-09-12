<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $url;
    protected string $apiKey;

    public function __construct()
    {
        $this->url = rtrim((string) config('evolution.url'), '/');
        $this->apiKey = (string) config('evolution.api_key');
    }

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(180);
    }

    protected function normalizeNumber(string $number): string
    {
        $number = trim($number);

        if (
            str_ends_with($number, '@s.whatsapp.net')
            || str_ends_with($number, '@g.us')
            || preg_match('/^[0-9]+@lid$/', $number)
        ) {
            return $number;
        }

        return preg_replace('/\D+/', '', $number) ?? '';
    }

    public function createInstance(string $instanceName): array
    {
        $instanceName = trim($instanceName);

        if ($instanceName === '') {
            throw new Exception('Instance adı boş olamaz.');
        }

        $response = $this->client()->post(
            "{$this->url}/instance/create",
            [
                'instanceName' => $instanceName,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => true,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('Evolution API Hatası: '.$response->body());
        }

        return $response->json();
    }

    public function connectionState(string $instanceName): string
    {
        $response = $this->client()->get(
            "{$this->url}/instance/connectionState/{$instanceName}"
        );

        if (! $response->successful()) {
            throw new Exception('Bağlantı durumu alınamadı: '.$response->body());
        }

        $data = $response->json();

        return strtolower((string) (
            data_get($data, 'instance.state')
            ?? data_get($data, 'state')
            ?? data_get($data, 'instance.connectionStatus')
            ?? 'disconnected'
        ));
    }

    public function getQrCode(string $instanceName): ?string
    {
        $response = $this->client()->get(
            "{$this->url}/instance/connect/{$instanceName}"
        );

        if (! $response->successful()) {
            throw new Exception('QR kod alınamadı: '.$response->body());
        }

        $data = $response->json();

        $qrCode =
            data_get($data, 'base64')
            ?? data_get($data, 'qrcode.base64')
            ?? data_get($data, 'code')
            ?? data_get($data, 'qrcode.code');

        return is_string($qrCode) && $qrCode !== '' ? $qrCode : null;
    }

    public function setWebhook(string $instanceName, string $webhookUrl): array
    {
        $response = $this->client()->post(
            "{$this->url}/webhook/set/{$instanceName}",
            [
                'webhook' => [
                    'enabled' => true,
                    'url' => trim($webhookUrl),
                    'webhookByEvents' => false,
                    'webhookBase64' => false,
                    'events' => [
                        'MESSAGES_UPSERT',
                        'MESSAGES_UPDATE',
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            throw new Exception('Webhook ayarlanamadı: '.$response->body());
        }

        return $response->json();
    }

    public function sendText(string $instanceName, string $number, string $text): array
    {
        $instanceName = trim($instanceName);
        $number = $this->normalizeNumber($number);
        $text = trim($text);

        if ($instanceName === '') {
            throw new Exception('Instance adı boş olamaz.');
        }

        if ($number === '') {
            throw new Exception('Telefon numarası boş olamaz.');
        }

        if ($text === '') {
            throw new Exception('Gönderilecek mesaj boş olamaz.');
        }

        $response = $this->client()->post(
            "{$this->url}/message/sendText/{$instanceName}",
            [
                'number' => $number,
                'options' => [
                    'delay' => 0,
                    'presence' => 'composing',
                ],
                'textMessage' => [
                    'text' => $text,
                ],
                'text' => $text,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('WhatsApp mesajı gönderilemedi: '.$response->body());
        }

        return $response->json();
    }

    public function sendMedia(
        string $instanceName,
        string $number,
        string $media,
        string $mediaType,
        string $fileName = '',
        string $caption = '',
        string $mimetype = ''
    ): array {
        $instanceName = trim($instanceName);
        $number = $this->normalizeNumber($number);
        $media = trim($media);
        $mediaType = strtolower(trim($mediaType));

        if ($instanceName === '' || $number === '' || $media === '') {
            throw new Exception('Medya gönderimi için gerekli bilgiler eksik.');
        }

        if (! in_array($mediaType, ['image', 'video', 'document'], true)) {
            throw new Exception('Geçersiz medya türü.');
        }

        $mimetype = $mimetype !== ''
            ? $mimetype
            : match ($mediaType) {
                'image' => 'image/jpeg',
                'video' => 'video/mp4',
                default => 'application/octet-stream',
            };

        $fileName = $fileName !== ''
            ? $fileName
            : match ($mediaType) {
                'image' => 'image.jpg',
                'video' => 'video.mp4',
                default => 'document',
            };

        $response = $this->client()->post(
            "{$this->url}/message/sendMedia/{$instanceName}",
            [
                'number' => $number,
                'mediatype' => $mediaType,
                'mimetype' => $mimetype,
                'media' => $media,
                'fileName' => $fileName,
                'caption' => $caption,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('WhatsApp medya mesajı gönderilemedi: '.$response->body());
        }

        return $response->json();
    }

    public function sendImage(
        string $instanceName,
        string $number,
        string $media,
        string $fileName = 'image.jpg',
        string $caption = '',
        string $mimetype = 'image/jpeg'
    ): array {
        return $this->sendMedia(
            $instanceName,
            $number,
            $media,
            'image',
            $fileName,
            $caption,
            $mimetype
        );
    }

    public function sendVideo(
        string $instanceName,
        string $number,
        string $media,
        string $fileName = 'video.mp4',
        string $caption = '',
        string $mimetype = 'video/mp4'
    ): array {
        return $this->sendMedia(
            $instanceName,
            $number,
            $media,
            'video',
            $fileName,
            $caption,
            $mimetype
        );
    }

    public function sendDocument(
        string $instanceName,
        string $number,
        string $media,
        string $fileName,
        string $caption = '',
        string $mimetype = 'application/octet-stream'
    ): array {
        return $this->sendMedia(
            $instanceName,
            $number,
            $media,
            'document',
            $fileName,
            $caption,
            $mimetype
        );
    }

    public function sendAudio(
        string $instanceName,
        string $number,
        string $audio
    ): array {
        $instanceName = trim($instanceName);
        $number = $this->normalizeNumber($number);
        $audio = trim($audio);

        if ($instanceName === '' || $number === '' || $audio === '') {
            throw new Exception('Ses gönderimi için gerekli bilgiler eksik.');
        }

        $response = $this->client()->post(
            "{$this->url}/message/sendWhatsAppAudio/{$instanceName}",
            [
                'number' => $number,
                'audio' => $audio,
                'encoding' => true,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('WhatsApp ses mesajı gönderilemedi: '.$response->body());
        }

        return $response->json();
    }

    public function sendGroupText(
        string $instanceName,
        string $groupJid,
        string $text
    ): array {
        $instanceName = trim($instanceName);
        $groupJid = trim($groupJid);
        $text = trim($text);

        if ($instanceName === '' || $groupJid === '' || $text === '') {
            throw new Exception('Grup mesajı için gerekli bilgiler eksik.');
        }

        if (! str_ends_with($groupJid, '@g.us')) {
            throw new Exception('Geçersiz WhatsApp grup ID.');
        }

        $response = $this->client()->post(
            "{$this->url}/message/sendText/{$instanceName}",
            [
                'number' => $groupJid,
                'options' => [
                    'delay' => 0,
                    'presence' => 'composing',
                ],
                'textMessage' => [
                    'text' => $text,
                ],
                'text' => $text,
            ]
        );

        if (! $response->successful()) {
            throw new Exception('WhatsApp grubuna mesaj gönderilemedi: '.$response->body());
        }

        return $response->json();
    }

    public function fetchGroups(string $instanceName): array
    {
        $response = $this->client()->get(
            "{$this->url}/group/fetchAllGroups/{$instanceName}",
            ['getParticipants' => 'false']
        );

        if (! $response->successful()) {
            throw new Exception('WhatsApp grupları alınamadı: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        return collect($data)
            ->filter(function ($group) {
                $id = data_get($group, 'id');

                return is_string($id) && str_ends_with($id, '@g.us');
            })
            ->map(function ($group) {
                return [
                    'id' => data_get($group, 'id'),
                    'name' => data_get($group, 'subject')
                        ?? data_get($group, 'name')
                        ?? 'İsimsiz Grup',
                ];
            })
            ->values()
            ->all();
    }
}