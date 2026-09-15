<?php

namespace App\Services\TextileV2;

use RuntimeException;

class TextileV2ColorCatalogueService
{
    private const COLORS = [
        'beige' => 'Bej',
        'orange' => 'Turuncu',
        'red' => 'Kırmızı',
        'burgundy' => 'Bordo',
        'yellow' => 'Sarı',
        'green' => 'Yeşil',
        'white' => 'Beyaz',
        'turquoise' => 'Turkuaz',
        'blue' => 'Mavi',
        'navy' => 'Lacivert',
        'gray' => 'Gri',
        'black' => 'Siyah',
    ];

    public function create(string $product): string
    {
        $labels = [
            'regular' => 'REGULAR TİŞÖRT RENK KARTELASI',
            'oversize' => 'OVERSIZE TİŞÖRT RENK KARTELASI',
            'polo' => 'POLO YAKA RENK KARTELASI',
        ];

        if (! isset($labels[$product])) {
            throw new RuntimeException('Geçersiz kartela ürünü.');
        }

        $colors = $product === 'oversize'
            ? ['white' => 'Beyaz', 'black' => 'Siyah']
            : self::COLORS;

        $font = $this->fontPath();
        $cols = $product === 'oversize' ? 2 : 4;
        $cellW = 285;
        $cellH = 300;
        $startX = 30;
        $startY = 92;
        $rows = (int) ceil(count($colors) / $cols);
        $width = $product === 'oversize' ? 620 : 1200;
        $height = $startY + ($rows * $cellH) + 30;

        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) {
            throw new RuntimeException('Kartela görseli oluşturulamadı.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 20, 20, 20);
        $gray = imagecolorallocate($canvas, 245, 245, 245);
        imagefill($canvas, 0, 0, $white);

        $this->writeText($canvas, 22, 30, 40, $black, $font, $labels[$product]);
        $this->writeText($canvas, 14, 30, 70, $black, $font, 'İstanbul Tişört Baskı');

        $index = 0;
        foreach ($colors as $color => $label) {
            $row = intdiv($index, $cols);
            $col = $index % $cols;
            $x = $startX + ($col * $cellW);
            $y = $startY + ($row * $cellH);

            imagefilledrectangle($canvas, $x, $y, $x + 265, $y + 270, $gray);

            $path = public_path("assets/textile/catalog/ready/{$product}-{$color}-front.jpg");
            if (is_file($path)) {
                $src = @imagecreatefromjpeg($path);
                if ($src) {
                    $sw = imagesx($src);
                    $sh = imagesy($src);
                    $maxW = 235;
                    $maxH = 220;
                    $scale = min($maxW / $sw, $maxH / $sh);
                    $dw = max(1, (int) round($sw * $scale));
                    $dh = max(1, (int) round($sh * $scale));
                    $dx = $x + (int) (($maxW - $dw) / 2) + 15;
                    $dy = $y + 12 + (int) (($maxH - $dh) / 2);
                    imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
                    imagedestroy($src);
                }
            }

            $this->writeText($canvas, 15, $x + 18, $y + 258, $black, $font, $label);
            $index++;
        }

        ob_start();
        imagejpeg($canvas, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Kartela JPEG çıktısı oluşturulamadı.');
        }

        return base64_encode($bytes);
    }

    private function fontPath(): string
    {
        foreach ([
            public_path('fonts/DejaVuSans.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        throw new RuntimeException('Türkçe karakter destekli font bulunamadı.');
    }

    private function writeText($image, int $size, int $x, int $y, int $color, string $font, string $text): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }
}
