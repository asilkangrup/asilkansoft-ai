<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealEstateCrmOnlyCostModeTest extends TestCase
{
    public function test_production_chat_is_crm_only_without_automatic_market_research(): void
    {
        $valuation = file_get_contents(app_path('Services/RealEstateValuationService.php'));
        $inbound = file_get_contents(app_path('Services/RealEstateWhatsAppInboundService.php'));
        $prompt = file_get_contents(app_path('Services/RealEstateOpenAIService.php'));

        $this->assertStringContainsString(
            "REAL_ESTATE_AUTOMATIC_MARKET_RESEARCH_ENABLED', false",
            $valuation
        );
        $this->assertStringContainsString(
            'market_research_mode',
            $inbound
        );
        $this->assertStringContainsString(
            'automatic_price_generated',
            $inbound
        );
        $this->assertStringContainsString(
            'Hızlı nakitte son fiyatınız nedir?',
            $inbound
        );
        $this->assertStringContainsString(
            'Bu WhatsApp botu otomatik piyasa/emsal araştırması yapmaz',
            $prompt
        );
    }

    public function test_media_is_saved_without_vision_and_chat_context_is_bounded(): void
    {
        $batch = file_get_contents(app_path('Jobs/ProcessRealEstateMediaBatch.php'));
        $inbound = file_get_contents(app_path('Services/RealEstateWhatsAppInboundService.php'));

        $this->assertStringContainsString('public int $tries = 2', $batch);
        $this->assertStringContainsString('analyzeMedia: false', $batch);
        $this->assertStringNotContainsString(
            'RecoverRealEstateUnansweredInbound::dispatch()',
            $batch
        );
        $this->assertStringContainsString(
            "in_array(\$mediaContext['type'] ?? null, ['image', 'document'], true)",
            $inbound
        );
        $this->assertStringContainsString('limit: 50', $inbound);
        $this->assertStringContainsString('görseldeki taşınmaz bilgilerini yazılı olarak da göndermeniz gerekiyor', $inbound);
        $this->assertStringContainsString('Yatırımcılardan hızlı nakit fiyatı almak ister misiniz?', $inbound);
        $this->assertStringContainsString('Yatırımcı tekliflerini topluyoruz', $inbound);
        $this->assertStringContainsString('görüşmeyi yeni sorularla uzatma', $prompt);
    }

    public function test_followups_and_cross_tenant_behavior_are_not_enabled(): void
    {
        $files = file_get_contents(app_path('Services/RealEstateValuationService.php'))
            .file_get_contents(app_path('Services/RealEstateWhatsAppInboundService.php'))
            .file_get_contents(app_path('Jobs/ProcessRealEstateMediaBatch.php'));

        $this->assertStringNotContainsString('follow_up_enabled = true', $files);
        $this->assertStringNotContainsString('second_follow_up_enabled = true', $files);
        $this->assertStringContainsString('isolatedProduction()', $files);
    }
}
