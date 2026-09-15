<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingBusinessCardMockupService;
use Tests\TestCase;

final class PrintingBusinessCardMockupServiceTest extends TestCase
{
    public function test_it_creates_a_jpeg_mockup_without_redrawing_artwork(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        $artwork = imagecreatetruecolor(850, 500);
        $background = imagecolorallocate($artwork, 18, 28, 42);
        $accent = imagecolorallocate($artwork, 245, 210, 64);
        imagefill($artwork, 0, 0, $background);
        imagefilledrectangle($artwork, 50, 50, 800, 110, $accent);

        ob_start();
        imagepng($artwork);
        $png = ob_get_clean();
        imagedestroy($artwork);

        $this->assertIsString($png);

        $service = app(PrintingBusinessCardMockupService::class);
        $encoded = $service->create(base64_encode($png), null, 'mat');
        $bytes = base64_decode($encoded, true);

        $this->assertIsString($bytes);
        $this->assertGreaterThan(20_000, strlen($bytes));
        $this->assertSame("\xFF\xD8\xFF", substr($bytes, 0, 3));

        $mockup = imagecreatefromstring($bytes);
        $this->assertNotFalse($mockup);
        $this->assertSame(1200, imagesx($mockup));
        $this->assertSame(1200, imagesy($mockup));
        imagedestroy($mockup);
    }

    public function test_it_supports_front_and_back_artwork(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        $front = $this->solidArtwork(850, 500, [20, 90, 160]);
        $back = $this->solidArtwork(850, 500, [170, 45, 60]);

        $service = app(PrintingBusinessCardMockupService::class);
        $encoded = $service->create($front, $back, 'parlak');

        $this->assertNotEmpty($encoded);
        $this->assertNotFalse(imagecreatefromstring((string) base64_decode($encoded, true)));
    }

    private function solidArtwork(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return base64_encode((string) $png);
    }
}
