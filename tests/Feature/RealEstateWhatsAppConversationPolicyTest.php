<?php

namespace Tests\Feature;

use App\Services\RealEstateOpenAIService;
use ReflectionMethod;
use Tests\TestCase;

class RealEstateWhatsAppConversationPolicyTest extends TestCase
{
    public function test_master_prompt_requires_contextual_decisive_image_and_chat_replies(): void
    {
        $method = new ReflectionMethod(RealEstateOpenAIService::class, 'masterPrompt');
        $prompt = (string) $method->invoke(app(RealEstateOpenAIService::class));

        $this->assertStringContainsString('ilk cümlesinde doğrudan bu ihtiyaca cevap ver', $prompt);
        $this->assertStringContainsString('sadece alanları listeleme', $prompt);
        $this->assertStringContainsString('Yeterli veri varsa soru sorarak kaçma', $prompt);
        $this->assertStringContainsString('tek kritik soru', $prompt);
        $this->assertStringContainsString('yatırımcı teklifine geçir', $prompt);
        $this->assertStringContainsString('özel minimum/taban fiyatını', $prompt);
        $this->assertStringContainsString('sahte alıcı, sahte teklif', $prompt);
        $this->assertStringContainsString(
            'Acil nakde dönmek isteyen mülk sahiplerinden gelen gayrimenkul dosyalarını inceliyoruz',
            $prompt
        );
        $this->assertStringContainsString(
            'kriterleri eşleşen gerçek yatırımcılara iletiyoruz',
            $prompt
        );
        $this->assertStringContainsString(
            'Bu açıklamayı konuşmanın ilerleyen mesajlarında tekrarlama',
            $prompt
        );
        $this->assertStringContainsString('garantili kazanç', $prompt);
        $this->assertStringContainsString('en fazla 1-2 kritik kriter sor', $prompt);
    }
}
