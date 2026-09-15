<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingArtworkClassifierService;
use Tests\TestCase;

final class PrintingArtworkClassifierServiceTest extends TestCase
{
    public function test_standard_business_card_ratio_is_accepted(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is unavailable.');
        }

        $service = app(PrintingArtworkClassifierService::class);
        $result = $service->classifyBusinessCardImage($this->imageBase64(850, 500));

        $this->assertSame('print_artwork', $result['role']);
    }

    public function test_square_product_photo_is_not_auto_accepted_as_business_card_artwork(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is unavailable.');
        }

        $service = app(PrintingArtworkClassifierService::class);
        $result = $service->classifyBusinessCardImage($this->imageBase64(1000, 1000));

        $this->assertSame('unknown', $result['role']);
        $this->assertSame('business_card_ratio_mismatch', $result['reason']);
    }

    public function test_explicit_card_caption_can_confirm_unusual_artwork_ratio(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is unavailable.');
        }

        $service = app(PrintingArtworkClassifierService::class);
        $result = $service->classifyBusinessCardImage(
            $this->imageBase64(1000, 1000),
            'Bu kartvizit tasarımının ön yüzü'
        );

        $this->assertSame('print_artwork', $result['role']);
        $this->assertSame('high', $result['confidence']);
    }

    private function imageBase64(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 240, 240, 240);
        imagefill($image, 0, 0, $background);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return base64_encode((string) $bytes);
    }
}
