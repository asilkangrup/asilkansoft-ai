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

        $width = 1200;
        $height = 1050;
        $canvas = imagecreatetruecolor($width, $height);
        if (! $canvas) {
            throw new RuntimeException('Kartela görseli oluşturulamadı.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 20, 20, 20);
        $gray = imagecolorallocate($canvas, 245, 245, 245);
        imagefill($canvas, 0, 0, $white);

        imagestring($canvas, 5, 30, 24, $labels[$product], $black);
        imagestring($canvas, 3, 30, 52, 'İstanbul Tişört Baskı', $black);

        $cols = 4;
        $cellW = 285;
        $cellH = 300;
        $startX = 30;
        $startY = 92;

        $index = 0;
        foreach (self::COLORS as $color => $label) {
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

            imagestring($canvas, 5, $x + 18, $y + 240, $label, $black);
            $index++;
        }

        ob_start();
        imagejpeg($canvas, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Kartela JPEG çıktısı oluşturulamadı.');
        }

        return base64_encode($bytes);
    }
}
