<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingCreativeBackgroundService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PrintingCreativeBackgroundServiceTest extends TestCase
{
    public function test_it_uses_dedicated_matbaa_image_generation_endpoint(): void
    {
        config()->set('matbaa.enabled', true);
        config()->set('matbaa.api_key', 'test-matbaa-key');
        config()->set('matbaa.image_model', 'gpt-image-2.5-sunburst');
        config()->set('matbaa.image_quality', 'medium');
        config()->set('matbaa.creative_backgrounds', true);

        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('fake-image-bytes')]],
            ], 200),
        ]);

        $result = app(PrintingCreativeBackgroundService::class)->generate([
            'brand_name' => 'Soykan Auto',
            'style' => 'premium',
        ]);

        $this->assertSame(base64_encode('fake-image-bytes'), $result);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/images/generations'
                && $request['model'] === 'gpt-image-2.5-sunburst'
                && str_contains((string) $request['prompt'], 'No text');
        });
    }

    public function test_api_failure_returns_null_so_local_renderer_can_fallback(): void
    {
        config()->set('matbaa.enabled', true);
        config()->set('matbaa.api_key', 'test-matbaa-key');
        config()->set('matbaa.creative_backgrounds', true);

        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response(['error' => 'temporary'], 500),
        ]);

        $result = app(PrintingCreativeBackgroundService::class)->generate([
            'brand_name' => 'Soykan Auto',
            'style' => 'premium',
        ]);

        $this->assertNull($result);
    }
}
