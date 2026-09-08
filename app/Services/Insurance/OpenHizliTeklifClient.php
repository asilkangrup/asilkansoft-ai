<?php

namespace App\Services\Insurance;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenHizliTeklifClient
{
    public function configured(): bool
    {
        return filled(config('insurance.open.acente_kodu'))
            && filled(config('insurance.open.token'));
    }

    public function connectionSummary(): array
    {
        return [
            'configured' => $this->configured(),
            'base_url' => (string) config('insurance.open.base_url'),
            'acente_kodu_present' => filled(config('insurance.open.acente_kodu')),
            'token_present' => filled(config('insurance.open.token')),
        ];
    }

    public function listUsers(): array
    {
        return $this->post('/api/APIGetUsers');
    }

    public function getTeklif(int $teklifId): array
    {
        return $this->post('/api/APITeklifGetir', ['teklifid' => $teklifId]);
    }

    public function getTeklifDetay(int $teklifId): array
    {
        return $this->post('/api/APITeklifDetayGetir', ['teklifid' => $teklifId]);
    }

    public function getTeklifFiyatlari(int $teklifId): array
    {
        return $this->post('/api/APITeklifFiyatlariniGetir', ['teklifid' => $teklifId]);
    }

    public function getDaskDetay(int $teklifId): array
    {
        return $this->post('/api/ApiDaskDetayGetir', ['teklifid' => $teklifId]);
    }

    public function getKonutDetay(int $teklifId): array
    {
        return $this->post('/api/ApiKonutDetayGetir', ['teklifid' => $teklifId]);
    }

    private function post(string $path, array $query = []): array
    {
        $this->assertConfigured();

        $response = $this->request()->post(
            rtrim((string) config('insurance.open.base_url'), '/').$path.($query ? '?'.http_build_query($query) : ''),
            $this->credentials()
        );

        if (! $response->successful()) {
            throw new RuntimeException('Open Hızlı Teklif servisi HTTP '.$response->status().' döndürdü.');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Open Hızlı Teklif servisi beklenmeyen bir yanıt döndürdü.');
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        return Http::asJson()
            ->acceptJson()
            ->timeout((int) config('insurance.open.timeout', 20))
            ->retry(2, 400, throw: false);
    }

    private function credentials(): array
    {
        return [
            'acenteKodu' => (string) config('insurance.open.acente_kodu'),
            'token' => (string) config('insurance.open.token'),
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Open Hızlı Teklif bağlantısı henüz yapılandırılmadı. Acente Kodu ve Token gerekli.');
        }
    }
}
