<?php

namespace Tests\Feature;

use App\Services\RealEstateValuationResearchOutputGuardService;
use App\Services\RealEstateValuationService;
use ReflectionClass;
use Tests\TestCase;

class RealEstateCustomerPricingPolicyTest extends TestCase
{
    public function test_lower_executable_band_becomes_realistic_sale_and_investor_is_capped(): void
    {
        $service = app(RealEstateValuationService::class);
        $method = (new ReflectionClass($service))->getMethod('normalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'market_min' => 4_200_000,
            'market_max' => 5_250_000,
            'realistic_sale_min' => 3_950_000,
            'realistic_sale_max' => 4_650_000,
            'quick_sale_min' => 3_450_000,
            'quick_sale_max' => 3_950_000,
            'investor_buy_min' => 3_000_000,
            'investor_buy_max' => 3_720_000,
            'confidence_score' => 70,
            'sources' => ['https://example.test/listing'],
            'comparables' => [],
        ]);

        $this->assertSame(3_450_000.0, $result['realistic_sale_min']);
        $this->assertSame(3_950_000.0, $result['realistic_sale_max']);
        $this->assertSame(3_000_000.0, $result['investor_buy_min']);
        $this->assertSame(3_160_000.0, $result['investor_buy_max']);
        $this->assertLessThanOrEqual(
            $result['realistic_sale_max'] * 0.80,
            $result['investor_buy_max']
        );
    }

    public function test_output_guard_preserves_realistic_sale_fields(): void
    {
        $guard = app(RealEstateValuationResearchOutputGuardService::class);

        $result = $guard->buildSanitizedValuation([
            'market_min' => 4_200_000,
            'market_max' => 5_250_000,
            'realistic_sale_min' => 3_450_000,
            'realistic_sale_max' => 3_950_000,
            'quick_sale_min' => 3_450_000,
            'quick_sale_max' => 3_950_000,
            'investor_buy_min' => 3_000_000,
            'investor_buy_max' => 3_160_000,
            'confidence_score' => 70,
            'comparables' => [],
            'sources' => [],
        ], [
            'property_type' => 'arsa',
            'city' => 'İstanbul',
            'district' => 'Silivri',
        ]);

        $this->assertSame(3_450_000.0, $result['realistic_sale_min']);
        $this->assertSame(3_950_000.0, $result['realistic_sale_max']);
    }

    public function test_descriptive_comparable_property_type_keeps_same_core_type(): void
    {
        $guard = app(RealEstateValuationResearchOutputGuardService::class);

        $result = $guard->buildSanitizedValuation([
            'comparables' => [[
                'url' => 'https://example.test/listing',
                'listing_price' => 3_500_000,
                'area_sqm' => 400,
                'location' => 'İstanbul Silivri Büyükçavuşlu',
                'property_type' => 'Konut imarlı arsa',
                'observed_at' => now()->toDateString(),
            ]],
            'sources' => ['https://example.test/listing'],
        ], [
            'property_type' => 'arsa',
            'city' => 'İstanbul',
            'district' => 'Silivri',
            'neighborhood' => 'Büyükçavuşlu',
            'area_sqm' => 400,
        ]);

        $this->assertSame('arsa', $result['comparables'][0]['property_type']);
    }

    public function test_customer_chat_contract_has_only_two_price_levels(): void
    {
        $valuationSource = file_get_contents(app_path('Services/RealEstateValuationService.php'));
        $chatSource = file_get_contents(app_path('Services/RealEstateOpenAIService.php'));

        $this->assertStringContainsString(
            'unset($customerValuation[$internalField])',
            $valuationSource
        );
        $this->assertStringContainsString(
            'normal piyasa satış bandı',
            mb_strtolower($chatSource)
        );
        $this->assertStringContainsString('ASLA gösterme', $chatSource);
        $this->assertStringContainsString('realistic_sale_min', $chatSource);
        $this->assertStringContainsString('investor_buy_min', $chatSource);
    }
}
