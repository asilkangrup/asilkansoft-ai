<?php

namespace Tests\Unit;

use App\Jobs\ProcessRealEstateMediaBatch;
use App\Services\RealEstateMediaAnalysisService;
use App\Services\RealEstateValuationService;
use ReflectionMethod;
use Tests\TestCase;

class RealEstateMediaFactPromotionTest extends TestCase
{
    public function test_reliable_parcel_document_facts_fill_missing_crm_fields(): void
    {
        $method = new ReflectionMethod(
            RealEstateMediaAnalysisService::class,
            'promoteObservedFacts'
        );
        $method->setAccessible(true);

        $result = $method->invoke(new RealEstateMediaAnalysisService, [], [
            'message_id' => 'media-1',
            'media_category' => 'parcel_document',
            'confidence_score' => 91,
            'property_type' => 'tarla',
            'city' => 'Nevşehir',
            'district' => 'Derinkuyu',
            'neighborhood' => 'Fatih',
            'area_sqm' => 9712,
            'block_no' => '392',
            'parcel_no' => '160',
        ]);

        $this->assertSame(9712, $result['area_sqm']);
        $this->assertSame('392', $result['block_no']);
        $this->assertSame('160', $result['parcel_no']);
        $this->assertSame('Nevşehir', $result['city']);
        $this->assertTrue(
            $result['media_fact_sources'][0]['observed_not_legally_verified']
        );
    }

    public function test_media_facts_never_overwrite_existing_customer_fact(): void
    {
        $method = new ReflectionMethod(
            RealEstateMediaAnalysisService::class,
            'promoteObservedFacts'
        );
        $method->setAccessible(true);

        $result = $method->invoke(new RealEstateMediaAnalysisService, [
            'area_sqm' => 5000,
        ], [
            'media_category' => 'parcel_document',
            'confidence_score' => 95,
            'area_sqm' => 9712,
        ]);

        $this->assertSame(5000, $result['area_sqm']);
    }

    public function test_low_confidence_media_is_not_promoted(): void
    {
        $method = new ReflectionMethod(
            RealEstateMediaAnalysisService::class,
            'promoteObservedFacts'
        );
        $method->setAccessible(true);

        $result = $method->invoke(new RealEstateMediaAnalysisService, [], [
            'media_category' => 'parcel_document',
            'confidence_score' => 45,
            'area_sqm' => 9712,
        ]);

        $this->assertArrayNotHasKey('area_sqm', $result);
    }

    public function test_photo_burst_uses_representative_analysis_and_keeps_retry_payloads(): void
    {
        $source = file_get_contents(app_path(
            'Jobs/ProcessRealEstateMediaBatch.php'
        ));
        $memory = file_get_contents(app_path('Services/MemoryService.php'));
        $openAi = file_get_contents(app_path(
            'Services/RealEstateOpenAIService.php'
        ));

        $this->assertStringContainsString(
            '$representativeImageIndexes',
            $source
        );
        $this->assertStringContainsString(
            ': [$imageIndexes[0]]',
            $source
        );
        $this->assertStringNotContainsString(
            'floor((count($imageIndexes)',
            $source
        );
        $this->assertStringContainsString(
            "'payloads' => array_slice(\$payloads, \$index)",
            $source
        );
        $this->assertStringContainsString(
            "'skip_analysis'",
            $memory
        );
        $this->assertStringNotContainsString(
            'Mesajınızı tekrar gönderirseniz',
            $openAi
        );
        $this->assertStringContainsString(
            '$this->schedulePendingBatch($scheduledKey);',
            $source
        );
        $this->assertStringContainsString(
            'self::dispatch($this->cacheKey, $generation)',
            $source
        );
        $this->assertGreaterThan(
            strpos($source, 'foreach ($payloads as $index => $payload)'),
            strpos($source, '$this->schedulePendingBatch($scheduledKey);')
        );
    }

    public function test_seller_auto_research_no_longer_requires_asking_price(): void
    {
        $source = file_get_contents(app_path(
            'Services/RealEstateValuationService.php'
        ));
        $controller = file_get_contents(app_path(
            'Http/Controllers/RealEstateWhatsAppWebhookController.php'
        ));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "\$profile->profile_type === 'seller'",
            $source
        );
        $this->assertStringNotContainsString(
            "&& filled(\$data['asking_price'] ?? null)",
            $source
        );
        $this->assertStringContainsString(
            '->delay(now()->addSeconds(10))',
            $controller
        );
    }
}
