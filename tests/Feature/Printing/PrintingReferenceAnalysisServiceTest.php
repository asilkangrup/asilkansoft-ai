<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingReferenceAnalysisService;
use Tests\TestCase;

final class PrintingReferenceAnalysisServiceTest extends TestCase
{
    public function test_it_extracts_reusable_visual_direction_from_reference_image(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        $image = imagecreatetruecolor(600, 400);
        $blue = imagecolorallocate($image, 32, 96, 192);
        $gold = imagecolorallocate($image, 192, 150, 55);
        imagefill($image, 0, 0, $blue);
        imagefilledrectangle($image, 0, 300, 600, 400, $gold);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $result = app(PrintingReferenceAnalysisService::class)->analyze(base64_encode((string) $bytes));

        $this->assertMatchesRegularExpression('/^#[A-F0-9]{6}$/', $result['primary_color']);
        $this->assertMatchesRegularExpression('/^#[A-F0-9]{6}$/', $result['secondary_color']);
        $this->assertContains($result['tone'], ['dark', 'light', 'balanced']);
        $this->assertSame(1.5, $result['aspect_ratio']);
    }
}
