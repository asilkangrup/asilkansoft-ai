<?php

namespace Tests\Feature;

use App\Services\Insurance\OpenHizliTeklifClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenHizliTeklifClientTest extends TestCase
{
    public function test_it_posts_credentials_and_teklif_id_to_documented_endpoint(): void
    {
        config()->set('insurance.open.base_url', 'https://webservis.openyazilim.com');
        config()->set('insurance.open.acente_kodu', 'ACENTE-1');
        config()->set('insurance.open.token', 'token-value');

        Http::fake([
            'https://webservis.openyazilim.com/api/APITeklifGetir*' => Http::response(['teklifid' => 123], 200),
        ]);

        $result = app(OpenHizliTeklifClient::class)->getTeklif(123);

        $this->assertSame(123, $result['teklifid']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/api/APITeklifGetir?teklifid=123')
                && $request['acenteKodu'] === 'ACENTE-1'
                && $request['token'] === 'token-value';
        });
    }

    public function test_it_does_not_claim_to_be_configured_without_credentials(): void
    {
        config()->set('insurance.open.acente_kodu', null);
        config()->set('insurance.open.token', null);

        $this->assertFalse(app(OpenHizliTeklifClient::class)->configured());
    }
}
