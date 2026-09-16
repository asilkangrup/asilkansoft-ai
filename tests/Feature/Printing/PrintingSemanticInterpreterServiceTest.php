<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingSemanticInterpreterService;
use Tests\TestCase;

final class PrintingSemanticInterpreterServiceTest extends TestCase
{
    public function test_hazirlayin_means_we_need_design_when_design_choice_is_pending(): void
    {
        config()->set('matbaa.enabled', false);
        $service = app(PrintingSemanticInterpreterService::class);

        $normalized = $service->normalize('Hazırlayın', [
            'product' => 'business_card',
            'pending_fields' => ['design_status'],
        ]);

        $this->assertSame('Tasarımım yok, siz hazırlayın.', $normalized);
    }

    public function test_standard_is_applied_to_the_pending_paper_question(): void
    {
        config()->set('matbaa.enabled', false);
        $service = app(PrintingSemanticInterpreterService::class);

        $normalized = $service->normalize('Standart', [
            'product' => 'business_card',
            'pending_fields' => ['paper'],
        ]);

        $this->assertSame('Kağıt ve gramajı uygun standart seçeneğe göre siz belirleyin.', $normalized);
    }

    public function test_plain_phone_is_expanded_only_when_design_content_is_pending(): void
    {
        config()->set('matbaa.enabled', false);
        $service = app(PrintingSemanticInterpreterService::class);

        $normalized = $service->normalize('0536 475 0098', [
            'product' => 'business_card',
            'pending_fields' => ['brief:content'],
        ]);

        $this->assertSame('Kartta telefon: 0536 475 0098 yer alsın.', $normalized);
    }
}
