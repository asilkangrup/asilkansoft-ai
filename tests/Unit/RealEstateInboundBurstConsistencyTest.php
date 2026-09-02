<?php

namespace Tests\Unit;

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Services\RealEstateMediaAnalysisService;
use ReflectionMethod;
use Tests\TestCase;

class RealEstateInboundBurstConsistencyTest extends TestCase
{
    public function test_lid_and_phone_jids_resolve_to_the_same_burst_contact(): void
    {
        $method = new ReflectionMethod(
            RealEstateWhatsAppWebhookController::class,
            'canonicalContactKey'
        );
        $method->setAccessible(true);
        $controller = new RealEstateWhatsAppWebhookController;

        $lidPayload = [
            'data' => [
                'key' => [
                    'remoteJid' => '123456789012345@lid',
                    'remoteJidAlt' => '905301234567@s.whatsapp.net',
                ],
            ],
        ];
        $phonePayload = [
            'data' => [
                'key' => [
                    'remoteJid' => '905301234567@s.whatsapp.net',
                ],
            ],
        ];

        $this->assertSame(
            $method->invoke($controller, $lidPayload),
            $method->invoke($controller, $phonePayload)
        );
    }

    public function test_webhook_marks_latest_arrival_before_queued_processing(): void
    {
        $source = file_get_contents(app_path(
            'Http/Controllers/RealEstateWhatsAppWebhookController.php'
        ));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "'real-estate-latest-inbound:'",
            $source
        );
        $this->assertStringContainsString(
            "Cache::put(",
            $source
        );
    }

    public function test_media_analysis_requires_full_image_location_scan(): void
    {
        $method = new ReflectionMethod(
            RealEstateMediaAnalysisService::class,
            'instructions'
        );
        $method->setAccessible(true);

        $instructions = $method->invoke(new RealEstateMediaAnalysisService);

        $this->assertStringContainsString(
            'Orijinal medyanın tamamını yüksek ayrıntıyla yukarıdan aşağıya tara',
            $instructions
        );
        $this->assertStringContainsString(
            'İl, İlçe, Mahalle/Mevkii/Köy',
            $instructions
        );
        $this->assertStringContainsString(
            'city ve district alanlarını mutlaka doldur',
            $instructions
        );
    }
}
