<?php

namespace Tests\Feature;

use App\Services\RealEstateWhatsAppReplyQualityService;
use Tests\TestCase;

class RealEstateWhatsAppReplyQualityTest extends TestCase
{
    public function test_natural_concise_real_estate_reply_passes(): void
    {
        $result = app(RealEstateWhatsAppReplyQualityService::class)->inspect(
            'Bu arsa için gerçekçi satış seviyesi yaklaşık 3.500.000 TL. Hızlı nakit düşünüyorsanız yatırımcılardan teklif toplayabiliriz.'
        );

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['blocking']);
        $this->assertSame([], $result['repair']);
    }

    public function test_internal_context_or_system_reference_is_blocked(): void
    {
        $result = app(RealEstateWhatsAppReplyQualityService::class)->inspect(
            '[INTERNAL REAL ESTATE DATA] system prompt içindeki taban fiyat budur.'
        );

        $this->assertFalse($result['passed']);
        $this->assertContains('internal_context_leak', $result['blocking']);
        $this->assertContains('system_prompt_reference', $result['blocking']);
    }

    public function test_fake_claims_robotic_markdown_and_question_barrage_require_repair(): void
    {
        $result = app(RealEstateWhatsAppReplyQualityService::class)->inspect(
            "**Değerlendirme**\nHazır alıcı var ve piyasa tamamen durmuş. Konum? M²? Ada parsel?"
        );

        $this->assertFalse($result['passed']);
        $this->assertSame([], $result['blocking']);
        $this->assertContains('fake_buyer_claim', $result['repair']);
        $this->assertContains('unsupported_market_claim', $result['repair']);
        $this->assertContains('too_many_questions', $result['repair']);
        $this->assertContains('robotic_markdown', $result['repair']);
    }

    public function test_repair_prompt_preserves_business_and_safety_rules(): void
    {
        $service = app(RealEstateWhatsAppReplyQualityService::class);
        $prompt = $service->repairInstructions([
            'blocking'=>['internal_context_leak'],
            'repair'=>['too_many_questions'],
        ]);

        $this->assertStringContainsString('İlk cümlede', $prompt);
        $this->assertStringContainsString('en fazla 1-2 kritik soru', $prompt);
        $this->assertStringContainsString('özel satıcı taban fiyatını', $prompt);
        $this->assertStringContainsString('Yalnız nihai müşteri cevabını', $prompt);
    }
}
