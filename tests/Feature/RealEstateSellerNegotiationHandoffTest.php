<?php

namespace Tests\Feature;

use App\Services\RealEstateValuationResearchOutputGuardService;
use App\Services\RealEstateValuationService;
use ReflectionClass;
use Tests\TestCase;

class RealEstateSellerNegotiationHandoffTest extends TestCase
{
    public function test_executable_research_band_drives_customer_and_investor_levels(): void
    {
        $service = app(RealEstateValuationService::class);
        $method = (new ReflectionClass($service))->getMethod('normalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'market_min' => 500_000,
            'market_max' => 1_550_000,
            'realistic_sale_min' => 650_000,
            'realistic_sale_max' => 1_100_000,
            'quick_sale_min' => 480_000,
            'quick_sale_max' => 900_000,
            'investor_buy_min' => 430_000,
            'investor_buy_max' => 920_000,
            'confidence_score' => 56,
            'sources' => ['https://example.test/comparable'],
            'comparables' => [],
        ]);

        $this->assertSame(480_000.0, $result['realistic_sale_min']);
        $this->assertSame(900_000.0, $result['realistic_sale_max']);
        $this->assertSame(720_000.0, $result['investor_buy_max']);
        $this->assertLessThanOrEqual(
            $result['realistic_sale_max'] * 0.80,
            $result['investor_buy_max']
        );
    }

    public function test_output_guard_keeps_realistic_levels_for_chat_handoff(): void
    {
        $guard = app(RealEstateValuationResearchOutputGuardService::class);

        $result = $guard->buildSanitizedValuation([
            'realistic_sale_min' => 480_000,
            'realistic_sale_max' => 900_000,
            'quick_sale_min' => 480_000,
            'quick_sale_max' => 900_000,
            'investor_buy_min' => 430_000,
            'investor_buy_max' => 720_000,
            'comparables' => [],
            'sources' => [],
        ], [
            'property_type' => 'tarla',
            'city' => 'Bilecik',
            'district' => 'Yenipazar',
            'neighborhood' => 'Yumaklı Köyü',
            'area_sqm' => 4000,
        ]);

        $this->assertSame(480_000.0, $result['realistic_sale_min']);
        $this->assertSame(900_000.0, $result['realistic_sale_max']);
    }

    public function test_fresh_research_is_deterministically_connected_to_negotiation(): void
    {
        $inbound = file_get_contents(
            app_path('Services/RealEstateWhatsAppInboundService.php')
        );
        $valuation = file_get_contents(
            app_path('Services/RealEstateValuationService.php')
        );
        $extractor = file_get_contents(
            app_path('Services/RealEstateProfileService.php')
        );

        $this->assertIsString($inbound);
        $this->assertStringContainsString(
            'deterministicSellerValuationNegotiationAnswer',
            $inbound
        );
        $this->assertStringContainsString(
            'Hızlı ve gerçek bir yatırımcı teklifi için',
            $inbound
        );
        $this->assertStringContainsString(
            'confirmedSellerAskingPrice',
            $inbound
        );
        $this->assertStringContainsString(
            'Bu araştırma tamamlanmıştır',
            $valuation
        );
        $this->assertStringContainsString(
            '1.500.000 TL satış beklentisi',
            $extractor
        );
        $this->assertStringNotContainsString(
            'satıcının gizli minimum',
            mb_strtolower($inbound)
        );
    }

    public function test_company_information_is_exact_and_isolated_in_inbound_flow(): void
    {
        $service = app(\App\Services\RealEstateWhatsAppInboundService::class);
        $method = (new ReflectionClass($service))
            ->getMethod('deterministicCompanyInformationAnswer');
        $method->setAccessible(true);

        $answer = $method->invoke(
            $service,
            'Komisyonunuz nedir, yeriniz nerede ve tüm Türkiye çalışıyor musunuz?'
        );

        $this->assertSame(
            'Alıcıdan %2, satıcıdan da %2 hizmet komisyonu alıyoruz. '
            .'Merkezimiz İstanbul Bahçeşehir’de. '
            .'Tüm Türkiye genelinde hizmet veriyoruz.',
            $answer
        );
    }

    public function test_parcel_identity_message_connects_fresh_research_to_negotiation(): void
    {
        $inbound = file_get_contents(
            app_path('Services/RealEstateWhatsAppInboundService.php')
        );

        $this->assertIsString($inbound);
        $this->assertStringContainsString(
            "str_contains(\$normalized, 'ada')",
            $inbound
        );
        $this->assertStringContainsString(
            "str_contains(\$normalized, 'parsel')",
            $inbound
        );
        $this->assertStringContainsString(
            'deterministicCompanyInformationAnswer',
            $inbound
        );
    }
}
