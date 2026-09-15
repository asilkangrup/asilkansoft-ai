<?php

namespace Tests\Feature\Printing;

use App\Services\Printing\PrintingBusinessCardDesignService;
use Tests\TestCase;

final class PrintingBusinessCardDesignServiceTest extends TestCase
{
    public function test_it_creates_front_and_back_png_faces_from_brief(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is unavailable.');
        }

        $service = app(PrintingBusinessCardDesignService::class);
        $faces = $service->create([
            'brand_name' => 'Ruba Ofset',
            'style' => 'modern',
            'colors' => 'mavi ve beyaz',
            'sector' => 'matbaa',
            'phone' => '0532 111 22 33',
            'email' => 'info@example.com',
            'instagram' => '@rubaofset',
            'content' => 'Kaliteli Baskı',
        ]);

        foreach (['front', 'back'] as $side) {
            $bytes = base64_decode($faces[$side], true);
            $this->assertIsString($bytes);
            $this->assertStringStartsWith("\x89PNG", $bytes);
            $image = imagecreatefromstring($bytes);
            $this->assertNotFalse($image);
            $this->assertSame(1700, imagesx($image));
            $this->assertSame(1000, imagesy($image));
            imagedestroy($image);
        }
    }
}
