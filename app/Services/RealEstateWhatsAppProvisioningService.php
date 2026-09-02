<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class RealEstateWhatsAppProvisioningService
{
    public function __construct(
        private readonly RealEstateWebhookAuthService $authService
    ) {
    }

    public function configureWebhook(string $instanceName): array
    {
        $instanceName = trim($instanceName);

        if ($instanceName !== RealEstateIsolationService::INSTANCE) {
            throw new Exception(
                'Emlak AI yalnızca '.RealEstateIsolationService::INSTANCE
                .' Evolution instance üzerinde provision edilebilir.'
            );
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '') {
            throw new Exception('Emlak AI uygulama URL yapılandırması eksik.');
        }

        $webhookUrl = $appUrl.'/api/real-estate/whatsapp/webhook';
        $secret = $this->authService->secret();

        if ($secret === null) {
            throw new Exception('Emlak AI webhook gizli anahtarı yapılandırılmamış.');
        }

        $baseUrl = rtrim((string) config('evolution.url'), '/');
        $apiKey = trim((string) config('evolution.api_key'));

        if ($baseUrl === '' || $apiKey === '') {
            throw new Exception('Evolution API yapılandırması eksik.');
        }

        $response = Http::withHeaders([
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(60)->post(
            "{$baseUrl}/webhook/set/{$instanceName}",
            [
                'webhook' => [
                    'enabled' => true,
                    'url' => $webhookUrl,
                    'headers' => [
                        'jwt_key' => $secret,
                    ],
                    'byEvents' => false,
                    'base64' => false,
                    'events' => [
                        'MESSAGES_UPSERT',
                        'MESSAGES_UPDATE',
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            throw new Exception(
                'Emlak AI güvenli webhook ayarlanamadı: '.$response->body()
            );
        }

        return $response->json();
    }
}
