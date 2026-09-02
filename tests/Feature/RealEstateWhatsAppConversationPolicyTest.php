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
        $this->assertStringContainsString('hemen bütçe sorma', $prompt);
        $this->assertStringContainsString(
            'Taşınmaz türü/bölge/amaçtan en az ikisi netleşince',
            $prompt
        );
        $this->assertStringContainsString(
            'Müşteri kendiliğinden bütçe verirse kaydet; yeniden sorma',
            $prompt
        );
        $this->assertStringContainsString(
            'Daire/dubleks/villa/konut için ada/parseli veya konum linkini varsayılan soru olarak isteme',
            $prompt
        );
        $this->assertStringContainsString(
            'Arsa/tarla/arazi için konum, m², ada/parsel',
            $prompt
        );
        $this->assertStringContainsString(
            '1200 => 1.200.000 TL',
            $prompt
        );
        $this->assertStringContainsString(
            'ölçeği konuşma bağlamı belirler',
            $prompt
        );
        $this->assertStringContainsString(
            'tekrar satıcı fiyatını zorlamayı bırak',
            $prompt
        );
    }

    public function test_inbound_delivery_has_stale_turn_and_runtime_disable_guards(): void
    {
        $source = file_get_contents(app_path(
            'Services/RealEstateWhatsAppInboundService.php'
        ));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "'superseded_by_newer_customer_message'",
            $source
        );
        $this->assertStringContainsString(
            "'ai_disabled_before_delivery'",
            $source
        );
        $this->assertStringContainsString(
            "->where('id', '>', \$inboundMessage->id)",
            $source
        );
        $this->assertStringContainsString(
            '$bot->refresh();',
            $source
        );
        $this->assertStringContainsString(
            "'real-estate-latest-inbound:'",
            $source
        );
        $this->assertStringContainsString(
            '$supersededAtWebhook',
            $source
        );
    }
}
