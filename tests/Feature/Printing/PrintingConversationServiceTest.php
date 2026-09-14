<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingConversationService;
use Tests\TestCase;

final class PrintingConversationServiceTest extends TestCase
{
    public function test_it_does_not_ask_for_information_already_in_the_message(): void
    {
        $service = app(PrintingConversationService::class);

        $result = $service->process('10.000 adet A5 135 gr çift yön broşür istiyorum');

        $this->assertSame('brochure', $result['state']['product']);
        $this->assertSame(10000, $result['state']['slots']['quantity']);
        $this->assertSame('A5', $result['state']['slots']['size']);
        $this->assertSame('135 gr', $result['state']['slots']['paper']);
        $this->assertSame('double', $result['state']['slots']['sides']);
        $this->assertNotContains('quantity', $result['missing']);
        $this->assertNotContains('size', $result['missing']);
        $this->assertNotContains('paper', $result['missing']);
        $this->assertNotContains('sides', $result['missing']);
    }

    public function test_business_card_uses_standard_size_and_keeps_all_supplied_details(): void
    {
        $service = app(PrintingConversationService::class);

        $result = $service->process('500 tane 350 gr kartvizit çift yön mat selefon, tasarım hazır');

        $this->assertSame('business_card', $result['state']['product']);
        $this->assertSame(500, $result['state']['slots']['quantity']);
        $this->assertSame('85x50 mm', $result['state']['slots']['size']);
        $this->assertSame('350 gr', $result['state']['slots']['paper']);
        $this->assertSame('double', $result['state']['slots']['sides']);
        $this->assertSame('mat', $result['state']['slots']['lamination']);
        $this->assertSame('ready', $result['state']['slots']['design_status']);
        $this->assertSame('awaiting_file', $result['status']);
    }

    public function test_uploaded_artwork_prevents_reasking_design_question(): void
    {
        $service = app(PrintingConversationService::class);

        $result = $service->process(
            '1000 adet A5 135 gr çift yön broşür',
            [],
            [['name' => 'tasarim.pdf', 'mime' => 'application/pdf']]
        );

        $this->assertSame('ready', $result['state']['slots']['design_status']);
        $this->assertSame('quote_ready', $result['status']);
        $this->assertStringNotContainsString('Tasarım dosyanız hazır mı', $result['reply']);
    }

    public function test_it_limits_questions_per_turn(): void
    {
        config()->set('matbaa.max_questions_per_turn', 2);
        $service = app(PrintingConversationService::class);

        $result = $service->process('Katalog bastırmak istiyorum');

        $this->assertGreaterThan(2, count($result['missing']));
        $this->assertLessThanOrEqual(2, substr_count($result['reply'], '?'));
    }

    public function test_it_never_generates_a_price_in_the_deterministic_flow(): void
    {
        $service = app(PrintingConversationService::class);

        $result = $service->process('500 adet 350 gr kartvizit çift yön mat selefon, tasarım hazır', [], [
            ['name' => 'kartvizit.pdf', 'mime' => 'application/pdf'],
        ]);

        $this->assertSame('quote_ready', $result['status']);
        $this->assertStringContainsString('net fiyatı kontrol edilmeden kesin rakam paylaşmayacağım', $result['reply']);
        $this->assertDoesNotMatchRegularExpression('/\b\d+[\.,]?\d*\s*(?:₺|tl|lira)\b/iu', $result['reply']);
    }
}
