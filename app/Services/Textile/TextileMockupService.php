<?php

namespace App\Services\Textile;

use RuntimeException;

class TextileMockupService
{
    /**
     * Creates a premium 1200x1200 product mockup while preserving the uploaded
     * artwork pixels. The AI never redraws the customer's logo.
     */
    public function create(
        string $logoBase64,
        string $position = 'front_center',
        string $shirtColor = 'black',
    ): string {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Sunucuda GD görsel desteği etkin değil.');
        }

        $logoBytes = base64_decode($this->cleanBase64($logoBase64), true);
        if (! is_string($logoBytes) || $logoBytes === '') {
            throw new RuntimeException('Müşteri logosu çözülemedi.');
        }

        if (strlen($logoBytes) > 8 * 1024 * 1024) {
            throw new RuntimeException('Logo 8 MB güvenli demo sınırını aşıyor.');
        }

        $logo = @imagecreatefromstring($logoBytes);
        if ($logo === false) {
            throw new RuntimeException('Logo JPG, PNG veya WEBP formatında olmalıdır.');
        }

        $size = 1200;
        $canvas = imagecreatetruecolor($size, $size);
        if ($canvas === false) {
            imagedestroy($logo);
            throw new RuntimeException('Mockup çalışma alanı oluşturulamadı.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        if (function_exists('imageantialias')) {
            imageantialias($canvas, true);
        }

        $this->drawBackground($canvas, $size);
        $shirtRgb = $this->shirtRgb($shirtColor);
        $this->drawShirt($canvas, $shirtRgb);
        $this->placeLogo($canvas, $logo, $position);
        $this->drawFooter($canvas, $position, $shirtColor);

        ob_start();
        imagepng($canvas, null, 6);
        $png = ob_get_clean();

        imagedestroy($logo);
        imagedestroy($canvas);

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('Mockup PNG çıktısı oluşturulamadı.');
        }

        return base64_encode($png);
    }

    private function cleanBase64(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, ';base64,')) {
            $value = explode(';base64,', $value, 2)[1] ?? '';
        }

        return $value;
    }

    private function drawBackground(mixed $image, int $size): void
    {
        for ($y = 0; $y < $size; $y++) {
            $ratio = $y / max(1, $size - 1);
            $r = (int) round(247 - (15 * $ratio));
            $g = (int) round(248 - (14 * $ratio));
            $b = (int) round(250 - (12 * $ratio));
            imageline($image, 0, $y, $size, $y, imagecolorallocate($image, $r, $g, $b));
        }

        $shadow = imagecolorallocatealpha($image, 15, 23, 42, 98);
        for ($i = 0; $i < 32; $i++) {
            imagefilledellipse($image, 600, 982 + $i, 650 + ($i * 4), 86 + ($i * 2), $shadow);
        }

        $violet = imagecolorallocatealpha($image, 124, 58, 237, 112);
        $cyan = imagecolorallocatealpha($image, 6, 182, 212, 116);
        imagefilledellipse($image, 130, 120, 360, 360, $violet);
        imagefilledellipse($image, 1090, 130, 290, 290, $cyan);
    }

    private function drawShirt(mixed $image, array $rgb): void
    {
        [$r, $g, $b] = $rgb;
        $shadow = imagecolorallocatealpha($image, 2, 6, 23, 75);
        $dark = imagecolorallocate($image, max(0, $r - 22), max(0, $g - 22), max(0, $b - 22));
        $base = imagecolorallocate($image, $r, $g, $b);
        $light = imagecolorallocate($image, min(255, $r + 13), min(255, $g + 13), min(255, $b + 13));
        $neck = imagecolorallocate($image, max(0, $r - 35), max(0, $g - 35), max(0, $b - 35));

        $shape = [
            352, 250, 440, 205, 480, 230, 600, 265, 720, 230, 760, 205,
            848, 250, 1030, 390, 915, 555, 805, 485, 795, 930, 405, 930,
            395, 485, 285, 555, 170, 390,
        ];
        $shadowShape = array_map(static fn (int $v, int $i): int => $i % 2 === 0 ? $v + 16 : $v + 20, $shape, array_keys($shape));
        imagefilledpolygon($image, $shadowShape, count($shadowShape) / 2, $shadow);
        imagefilledpolygon($image, $shape, count($shape) / 2, $base);

        imagefilledpolygon($image, [170,390,352,250,395,485,285,555], 4, $dark);
        imagefilledpolygon($image, [848,250,1030,390,915,555,805,485], 4, $light);

        imagefilledellipse($image, 600, 247, 210, 145, $neck);
        imagefilledellipse($image, 600, 232, 166, 112, imagecolorallocate($image, 236, 238, 242));

        for ($x = 430; $x <= 770; $x += 18) {
            imageline($image, $x, 350, $x + 8, 900, imagecolorallocatealpha($image, 255, 255, 255, 122));
        }

        imageline($image, 410, 929, 790, 929, $dark);
        imageline($image, 412, 934, 788, 934, imagecolorallocatealpha($image, 255, 255, 255, 105));
    }

    private function placeLogo(mixed $canvas, mixed $logo, string $position): void
    {
        [$centerX, $centerY, $maxWidth, $maxHeight] = match ($position) {
            'left_chest' => [515, 430, 155, 125],
            'front_large' => [600, 540, 390, 330],
            'back_large' => [600, 500, 410, 350],
            default => [600, 455, 255, 210],
        };

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            throw new RuntimeException('Logo ölçüleri okunamadı.');
        }

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $x = (int) round($centerX - ($targetWidth / 2));
        $y = (int) round($centerY - ($targetHeight / 2));

        imagealphablending($logo, true);
        imagesavealpha($logo, true);
        imagecopyresampled(
            $canvas,
            $logo,
            $x,
            $y,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
    }

    private function drawFooter(mixed $image, string $position, string $shirtColor): void
    {
        $ink = imagecolorallocate($image, 30, 41, 59);
        $muted = imagecolorallocate($image, 100, 116, 139);
        $green = imagecolorallocate($image, 5, 150, 105);

        imagestring($image, 5, 70, 1050, 'WAI TEXTILE  /  BASKI ONIZLEMESI', $ink);
        imagestring($image, 3, 70, 1080, 'Logo yeniden cizilmedi; orijinal dosya kullanildi.', $muted);
        imagestring($image, 4, 790, 1055, strtoupper($this->positionLabel($position)), $green);
        imagestring($image, 3, 790, 1082, 'URUN RENGI: '.strtoupper($shirtColor), $muted);
    }

    private function positionLabel(string $position): string
    {
        return match ($position) {
            'left_chest' => 'Sol gogus',
            'front_large' => 'On buyuk baski',
            'back_large' => 'Arka buyuk baski',
            default => 'On orta',
        };
    }

    private function shirtRgb(string $color): array
    {
        return match ($color) {
            'white' => [238, 239, 236],
            'navy' => [31, 51, 81],
            'burgundy' => [112, 33, 50],
            'beige' => [210, 190, 158],
            default => [28, 31, 38],
        };
    }
}
