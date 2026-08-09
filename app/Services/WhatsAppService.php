<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $url;

    protected string $apiKey;

    public function __construct()
    {
        $this->url = rtrim(
            (string) config('evolution.url'),
            '/'
        );

        $this->apiKey = (string) config(
            'evolution.api_key'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EVOLUTION API CLIENT
    |--------------------------------------------------------------------------
    */

    protected function client(): PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP INSTANCE OLUŞTUR
    |--------------------------------------------------------------------------
    */

    public function createInstance(
        string $instanceName
    ): array {
        $instanceName = trim($instanceName);

        if ($instanceName === '') {
            throw new Exception(
                'Instance adı boş olamaz.'
            );
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
            throw new Exception(
                'Evolution API Hatası: ' .
                $response->body()
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | BAĞLANTI DURUMUNU KONTROL ET
    |--------------------------------------------------------------------------
    */

    public function connectionState(
        string $instanceName
    ): string {
        $response = $this->client()->get(
            "{$this->url}/instance/connectionState/{$instanceName}"
        );

        if (! $response->successful()) {
            throw new Exception(
                'Bağlantı durumu alınamadı: ' .
                $response->body()
            );
        }

        $data = $response->json();

        return strtolower(
            (string) (
                data_get($data, 'instance.state')
                ?? data_get($data, 'state')
                ?? data_get(
                    $data,
                    'instance.connectionStatus'
                )
                ?? 'disconnected'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QR KOD AL
    |--------------------------------------------------------------------------
    */

    public function getQrCode(
        string $instanceName
    ): ?string {
        $response = $this->client()->get(
            "{$this->url}/instance/connect/{$instanceName}"
        );

        if (! $response->successful()) {
            throw new Exception(
                'QR kod alınamadı: ' .
                $response->body()
            );
        }

        $data = $response->json();

        $qrCode =
            data_get($data, 'base64')
            ?? data_get($data, 'qrcode.base64')
            ?? data_get($data, 'code')
            ?? data_get($data, 'qrcode.code');

        return is_string($qrCode) && $qrCode !== ''
            ? $qrCode
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | WEBHOOK OTOMATİK AYARLA
    |--------------------------------------------------------------------------
    |
    | Her yeni WhatsApp instance oluşturulduğunda Laravel webhook adresini
    | Evolution API instance'ına otomatik olarak tanımlar.
    |
    */

    public function setWebhook(
        string $instanceName,
        string $webhookUrl
    ): array {
        $instanceName = trim($instanceName);
        $webhookUrl = trim($webhookUrl);

        if ($instanceName === '') {
            throw new Exception(
                'Instance adı boş olamaz.'
            );
        }

        if ($webhookUrl === '') {
            throw new Exception(
                'Webhook URL boş olamaz.'
            );
        }

        $response = $this->client()->post(
            "{$this->url}/webhook/set/{$instanceName}",
            [
                'webhook' => [
                    'enabled' => true,
                    'url' => $webhookUrl,
                    'webhookByEvents' => false,
                    'webhookBase64' => false,
                    'events' => [
                        'MESSAGES_UPSERT',
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            throw new Exception(
                'Webhook ayarlanamadı: ' .
                $response->body()
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP MESAJI GÖNDER
    |--------------------------------------------------------------------------
    */

    public function sendText(
        string $instanceName,
        string $number,
        string $text
    ): array {
        $instanceName = trim($instanceName);

        $number = preg_replace(
            '/\D+/',
            '',
            $number
        );

        $text = trim($text);

        if ($instanceName === '') {
            throw new Exception(
                'Instance adı boş olamaz.'
            );
        }

        if ($number === '') {
            throw new Exception(
                'Telefon numarası boş olamaz.'
            );
        }

        if ($text === '') {
            throw new Exception(
                'Gönderilecek mesaj boş olamaz.'
            );
        }

        $response = $this->client()->post(
            "{$this->url}/message/sendText/{$instanceName}",
            [
                'number' => $number,
                'text' => $text,
            ]
        );

        if (! $response->successful()) {
            throw new Exception(
                'WhatsApp mesajı gönderilemedi: ' .
                $response->body()
            );
        }

        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | GEÇİCİ TEST LOGU
        |--------------------------------------------------------------------------
        |
        | Evolution API'nin gönderilen mesaj için hangi yapıda mesaj ID'si
        | döndürdüğünü tespit etmek amacıyla geçici olarak kullanıyoruz.
        |
        | Test tamamlandıktan sonra bu log kaldırılacak.
        |
        */

        Log::info(
            'Evolution sendText test response',
            [
                'response' => $data,
            ]
        );

        return $data;
    }
}