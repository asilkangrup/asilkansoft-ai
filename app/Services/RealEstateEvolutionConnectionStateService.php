<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class RealEstateEvolutionConnectionStateService
{
    public function state(): string
    {
        $url = rtrim(trim((string) config('evolution.url')), '/');
        $apiKey = trim((string) config('evolution.api_key'));

        if ($url === '' || $apiKey === '') {
            return 'unconfigured';
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->connectTimeout(2)
                ->timeout(4)
                ->get(
                    $url.'/instance/connectionState/'.
                    RealEstateIsolationService::INSTANCE
                );

            if (! $response->successful()) {
                return 'unreachable';
            }

            $state = strtolower(trim((string) (
                data_get($response->json(), 'instance.state')
                ?? data_get($response->json(), 'state')
                ?? data_get($response->json(), 'instance.connectionStatus')
                ?? ''
            )));

            if ($state === '') {
                return 'invalid_response';
            }

            return $state;
        } catch (Throwable) {
            return 'unreachable';
        }
    }
}
