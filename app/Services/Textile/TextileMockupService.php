<?php

namespace App\Services\Textile;

use RuntimeException;

class TextileMockupService
{
    private const SIZE = 1200;

    /**
     * Creates a photographic product preview while preserving the customer's
     * original artwork. Only light, texture and very slight fabric deformation
     * are transferred from the garment to the print.
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

        $canvas = $this->loadStudioTemplate();
        $this->recolorGarment($canvas, $shirtColor);
        $this->placeNaturalPrint($canvas, $logo, $position);

        ob_start();
        imagejpeg($canvas, null, 91);
        $jpeg = ob_get_clean();

        imagedestroy($logo);
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Mockup JPEG çıktısı oluşturulamadı.');
        }

        return base64_encode($jpeg);
    }

    /**
     * Creates a premium dark magic-mug preview. One artwork produces one hero
     * mug; multiple artworks are joined as a wrap and shown from three angles.
     */
    public function createMug(array $artworksBase64): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Sunucuda GD görsel desteği etkin değil.');
        }

        $artworks = [];
        foreach (array_slice($artworksBase64, 0, 6) as $encoded) {
            $bytes = base64_decode($this->cleanBase64((string) $encoded), true);
            if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
                continue;
            }
            $image = @imagecreatefromstring($bytes);
            if ($image !== false) {
                $artworks[] = $image;
            }
        }

        if ($artworks === []) {
            throw new RuntimeException('Bardak baskı görselleri açılamadı.');
        }

        $multiple = count($artworks) > 1;
        $canvas = $this->loadPhotoMugTemplate($multiple);
        $wrap = $this->buildMugWrap($artworks, $multiple ? 1800 : 1000, 650);

        if ($multiple) {
            // Coordinates match the three real mugs in the photographic table template.
            $this->placePhotoMugPrint($canvas, $wrap, 215, 405, 235, 315, 0.17);
            $this->placePhotoMugPrint($canvas, $wrap, 603, 405, 235, 315, 0.50);
            $this->placePhotoMugPrint($canvas, $wrap, 995, 405, 235, 315, 0.83);
        } else {
            // Large printable face of the single real mug.
            $this->placePhotoMugPrint($canvas, $wrap, 398, 250, 420, 490, 0.50);
        }

        ob_start();
        imagejpeg($canvas, null, 93);
        $jpeg = ob_get_clean();

        foreach ($artworks as $artwork) {
            imagedestroy($artwork);
        }
        imagedestroy($wrap);
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Bardak mockup çıktısı oluşturulamadı.');
        }

        return base64_encode($jpeg);
    }

    private function loadPhotoMugTemplate(bool $multiple): mixed
    {
        $filename = $multiple ? 'magic-mug-triple-v2.jpg' : 'magic-mug-single-v2.jpg';
        $path = public_path('assets/textile/'.$filename);
        if (! is_file($path)) {
            throw new RuntimeException('Fotoğrafik bardak şablonu bulunamadı.');
        }

        $canvas = @imagecreatefromjpeg($path);
        if ($canvas === false) {
            throw new RuntimeException('Fotoğrafik bardak şablonu açılamadı.');
        }

        return $canvas;
    }

    private function placePhotoMugPrint(
        mixed $canvas,
        mixed $wrap,
        int $x,
        int $y,
        int $width,
        int $height,
        float $viewCenter,
    ): void {
        $sourceWidth = imagesx($wrap);
        $sourceHeight = imagesy($wrap);

        for ($dx = 0; $dx < $width; $dx++) {
            $normal = (($dx / max(1, $width - 1)) * 2) - 1;
            $curve = sqrt(max(0.0, 1 - ($normal * $normal)));
            $sourceNormal = $viewCenter + ($normal * 0.285);
            $sourceX = ((int) round($sourceNormal * $sourceWidth) % $sourceWidth + $sourceWidth) % $sourceWidth;
            $bow = (int) round((1 - $curve) * 7);

            for ($dy = 0; $dy < $height; $dy++) {
                $destinationX = $x + $dx;
                $destinationY = $y + $dy + $bow;
                if (
                    $destinationX < 0 || $destinationX >= imagesx($canvas)
                    || $destinationY < 0 || $destinationY >= imagesy($canvas)
                ) {
                    continue;
                }

                $sourceY = min($sourceHeight - 1, (int) floor(($dy / max(1, $height - 1)) * ($sourceHeight - 1)));
                $artPixel = imagecolorat($wrap, $sourceX, $sourceY);
                $basePixel = imagecolorat($canvas, $destinationX, $destinationY);

                $artRed = ($artPixel >> 16) & 0xff;
                $artGreen = ($artPixel >> 8) & 0xff;
                $artBlue = $artPixel & 0xff;
                $baseRed = ($basePixel >> 16) & 0xff;
                $baseGreen = ($basePixel >> 8) & 0xff;
                $baseBlue = $basePixel & 0xff;
                $baseLuminance = (0.2126 * $baseRed) + (0.7152 * $baseGreen) + (0.0722 * $baseBlue);

                // The customer's artwork follows the cylinder: darker at the
                // sides, softly bowed at top/bottom, and crossed by the real
                // ceramic highlights already present in the photograph.
                $cylinderShade = 0.73 + (0.27 * $curve);
                $surfaceShade = max(0.76, min(1.13, 0.82 + ($baseLuminance / 380)));
                $shade = $cylinderShade * $surfaceShade;

                $edgeFade = min(
                    1.0,
                    ($dx + 1) / 26,
                    ($width - $dx) / 26,
                    ($dy + 1) / 24,
                    ($height - $dy) / 24,
                );
                $highlightPreservation = $baseLuminance > 120 ? 0.74 : 0.93;
                $opacity = $edgeFade * $highlightPreservation;

                $printRed = min(255, max(0, (int) round($artRed * $shade)));
                $printGreen = min(255, max(0, (int) round($artGreen * $shade)));
                $printBlue = min(255, max(0, (int) round($artBlue * $shade)));

                $outRed = (int) round(($printRed * $opacity) + ($baseRed * (1 - $opacity)));
                $outGreen = (int) round(($printGreen * $opacity) + ($baseGreen * (1 - $opacity)));
                $outBlue = (int) round(($printBlue * $opacity) + ($baseBlue * (1 - $opacity)));

                imagesetpixel($canvas, $destinationX, $destinationY, ($outRed << 16) | ($outGreen << 8) | $outBlue);
            }
        }
    }

    private function paintMugStudio(mixed $canvas, int $width, int $height): void
    {
        for ($y = 0; $y < $height; $y++) {
            $floor = $y > 850;
            $t = $y / max(1, $height - 1);
            $base = $floor ? 224 - (int) (($y - 850) * 0.07) : 248 - (int) ($t * 16);
            $color = imagecolorallocate($canvas, $base, min(255, $base + 3), min(255, $base + 1));
            imageline($canvas, 0, $y, $width, $y, $color);
        }

        $line = imagecolorallocatealpha($canvas, 128, 142, 134, 90);
        imageline($canvas, 0, 850, $width, 850, $line);
    }

    private function buildMugWrap(array $images, int $width, int $height): mixed
    {
        $wrap = imagecreatetruecolor($width, $height);
        imagealphablending($wrap, false);
        imagesavealpha($wrap, true);
        imagefill($wrap, 0, 0, imagecolorallocate($wrap, 245, 245, 243));

        $count = count($images);
        $segmentWidth = (int) ceil($width / $count);

        foreach ($images as $index => $source) {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = max($segmentWidth / max(1, $sourceWidth), $height / max(1, $sourceHeight));
            $cropWidth = max(1, (int) round($segmentWidth / $scale));
            $cropHeight = max(1, (int) round($height / $scale));
            $sourceX = max(0, (int) round(($sourceWidth - $cropWidth) / 2));
            $sourceY = max(0, (int) round(($sourceHeight - $cropHeight) / 2));
            imagecopyresampled(
                $wrap,
                $source,
                $index * $segmentWidth,
                0,
                $sourceX,
                $sourceY,
                min($segmentWidth, $width - ($index * $segmentWidth)),
                $height,
                min($cropWidth, $sourceWidth),
                min($cropHeight, $sourceHeight),
            );
        }

        return $wrap;
    }

    private function drawMagicMug(
        mixed $canvas,
        mixed $wrap,
        int $x,
        int $y,
        int $width,
        int $height,
        float $rotation,
    ): void {
        $bodyX = $x + (int) ($width * 0.15);
        $bodyY = $y + (int) ($height * 0.10);
        $bodyWidth = (int) ($width * 0.68);
        $bodyHeight = (int) ($height * 0.73);
        $handleOnRight = $rotation >= 0.12;
        $handleX = $handleOnRight ? $bodyX + $bodyWidth - 8 : $bodyX - (int) ($width * 0.21);

        $shadow = imagecolorallocatealpha($canvas, 17, 22, 20, 84);
        imagefilledellipse($canvas, $x + (int) ($width * 0.49), $y + (int) ($height * 0.88), (int) ($width * 0.80), (int) ($height * 0.13), $shadow);

        imagesetthickness($canvas, max(18, (int) ($width * 0.065)));
        $handleDark = imagecolorallocate($canvas, 15, 17, 18);
        imagearc(
            $canvas,
            $handleX + (int) ($width * 0.17),
            $bodyY + (int) ($bodyHeight * 0.48),
            (int) ($width * 0.33),
            (int) ($height * 0.48),
            $handleOnRight ? 265 : 85,
            $handleOnRight ? 95 : 275,
            $handleDark,
        );
        imagesetthickness($canvas, 1);

        for ($column = 0; $column < $bodyWidth; $column++) {
            $n = (($column / max(1, $bodyWidth - 1)) * 2) - 1;
            $light = (int) round(33 + (24 * (1 - ($n * $n))) + (10 * exp(-pow(($n - 0.48) / 0.10, 2))));
            $color = imagecolorallocate($canvas, $light, $light + 2, $light + 3);
            imageline($canvas, $bodyX + $column, $bodyY + 18, $bodyX + $column, $bodyY + $bodyHeight - 18, $color);
        }

        $bottom = imagecolorallocate($canvas, 35, 38, 39);
        imagefilledellipse($canvas, $bodyX + (int) ($bodyWidth / 2), $bodyY + $bodyHeight - 17, $bodyWidth, 45, $bottom);
        $rim = imagecolorallocate($canvas, 22, 24, 25);
        imagefilledellipse($canvas, $bodyX + (int) ($bodyWidth / 2), $bodyY + 18, $bodyWidth, 48, $rim);
        $inside = imagecolorallocate($canvas, 230, 231, 228);
        imagefilledellipse($canvas, $bodyX + (int) ($bodyWidth / 2), $bodyY + 17, (int) ($bodyWidth * 0.88), 32, $inside);

        $printX = $bodyX + (int) ($bodyWidth * 0.08);
        $printY = $bodyY + (int) ($bodyHeight * 0.18);
        $printWidth = (int) ($bodyWidth * 0.84);
        $printHeight = (int) ($bodyHeight * 0.62);
        $sourceWidth = imagesx($wrap);
        $sourceHeight = imagesy($wrap);
        $offset = (int) round(($rotation + 0.5) * $sourceWidth);

        for ($dx = 0; $dx < $printWidth; $dx++) {
            $n = (($dx / max(1, $printWidth - 1)) * 2) - 1;
            $curve = sqrt(max(0.0, 1 - ($n * $n)));
            $visibleHeight = max(1, (int) round($printHeight * (0.92 + 0.08 * $curve)));
            $top = $printY + (int) round(($printHeight - $visibleHeight) / 2);
            $sourceX = ($offset + (int) round(($dx / max(1, $printWidth - 1)) * ($sourceWidth * 0.54))) % $sourceWidth;
            imagecopyresampled($canvas, $wrap, $printX + $dx, $top, $sourceX, 0, 1, $visibleHeight, 1, $sourceHeight);

            $edgeShade = (int) round(74 * pow(abs($n), 1.7));
            if ($edgeShade > 0) {
                $shade = imagecolorallocatealpha($canvas, 7, 9, 9, max(30, 127 - $edgeShade));
                imageline($canvas, $printX + $dx, $top, $printX + $dx, $top + $visibleHeight, $shade);
            }
        }

        $highlight = imagecolorallocatealpha($canvas, 255, 255, 255, 98);
        imagefilledrectangle(
            $canvas,
            $bodyX + (int) ($bodyWidth * 0.70),
            $bodyY + (int) ($bodyHeight * 0.16),
            $bodyX + (int) ($bodyWidth * 0.735),
            $bodyY + (int) ($bodyHeight * 0.73),
            $highlight,
        );
    }

    private function centeredText(mixed $canvas, string $text, int $y, int $size, array $rgb): void
    {
        $font = public_path('fonts/DejaVuSans-Bold.ttf');
        if (function_exists('imagettftext') && is_file($font)) {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            imagettftext($canvas, $size, 0, (int) ((imagesx($canvas) - $width) / 2), $y, imagecolorallocate($canvas, ...$rgb), $font, $text);
        }
    }

    private function cleanBase64(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, ';base64,')) {
            $value = explode(';base64,', $value, 2)[1] ?? '';
        }

        return $value;
    }

    private function loadStudioTemplate(): mixed
    {
        $path = public_path('assets/textile/oversize-black-studio-v1.jpg');
        if (! is_file($path)) {
            throw new RuntimeException('Fotoğrafik tekstil şablonu bulunamadı.');
        }

        $source = @imagecreatefromjpeg($path);
        if ($source === false) {
            throw new RuntimeException('Fotoğrafik tekstil şablonu açılamadı.');
        }

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Mockup çalışma alanı oluşturulamadı.');
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            self::SIZE,
            self::SIZE,
            imagesx($source),
            imagesy($source),
        );
        imagedestroy($source);

        return $canvas;
    }

    private function recolorGarment(mixed $canvas, string $shirtColor): void
    {
        $target = match ($shirtColor) {
            'white' => [226, 225, 218],
            'navy' => [31, 48, 76],
            'burgundy' => [105, 35, 48],
            'beige' => [190, 169, 137],
            default => null,
        };

        if ($target === null) {
            return;
        }

        for ($y = 55; $y < 1125; $y++) {
            for ($x = 55; $x < 1145; $x++) {
                $pixel = imagecolorat($canvas, $x, $y);
                $red = ($pixel >> 16) & 0xff;
                $green = ($pixel >> 8) & 0xff;
                $blue = $pixel & 0xff;
                $luminance = (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);

                // The generated base has a dark garment on a light backdrop.
                // Keeping the threshold low preserves the studio background and shadow.
                if ($luminance > 105) {
                    continue;
                }

                // Preserve the naturally dark inside of the collar.
                if ($y < 205 && $x > 470 && $x < 730 && $luminance < 28) {
                    continue;
                }

                $shade = max(0.52, min(1.22, 0.52 + ($luminance / 72)));
                $newRed = min(255, (int) round($target[0] * $shade));
                $newGreen = min(255, (int) round($target[1] * $shade));
                $newBlue = min(255, (int) round($target[2] * $shade));

                imagesetpixel($canvas, $x, $y, ($newRed << 16) | ($newGreen << 8) | $newBlue);
            }
        }
    }

    private function placeNaturalPrint(mixed $canvas, mixed $logo, string $position): void
    {
        [$centerX, $centerY, $maxWidth, $maxHeight] = match ($position) {
            'left_chest' => [485, 375, 185, 155],
            'front_large' => [600, 525, 430, 390],
            'back_large' => [600, 510, 420, 380],
            default => [600, 445, 310, 255],
        };

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            throw new RuntimeException('Logo ölçüleri okunamadı.');
        }

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        $artwork = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($artwork === false) {
            throw new RuntimeException('Baskı çalışma alanı oluşturulamadı.');
        }

        imagealphablending($artwork, false);
        imagesavealpha($artwork, true);
        $transparent = imagecolorallocatealpha($artwork, 0, 0, 0, 127);
        imagefill($artwork, 0, 0, $transparent);
        imagecopyresampled(
            $artwork,
            $logo,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );

        $top = (int) round($centerY - ($targetHeight / 2));

        for ($row = 0; $row < $targetHeight; $row++) {
            // The lower part of a worn shirt is fractionally wider. A tiny
            // scanline displacement follows the fabric instead of looking pasted on.
            $progress = $targetHeight > 1 ? $row / ($targetHeight - 1) : 0;
            $perspective = 0.965 + (0.035 * $progress);
            $rowWidth = max(1, (int) round($targetWidth * $perspective));
            $foldShift = (int) round(sin(($top + $row) / 31) * 1.15);
            $left = (int) round($centerX - ($rowWidth / 2)) + $foldShift;

            $rowLuminance = 0.0;
            $rowSamples = 0;
            for ($sample = 0; $sample < $rowWidth; $sample += 4) {
                $sampleX = $left + $sample;
                $sampleY = $top + $row;
                if ($sampleX < 0 || $sampleX >= self::SIZE || $sampleY < 0 || $sampleY >= self::SIZE) {
                    continue;
                }

                $samplePixel = imagecolorat($canvas, $sampleX, $sampleY);
                $sampleRed = ($samplePixel >> 16) & 0xff;
                $sampleGreen = ($samplePixel >> 8) & 0xff;
                $sampleBlue = $samplePixel & 0xff;
                $rowLuminance += (0.2126 * $sampleRed) + (0.7152 * $sampleGreen) + (0.0722 * $sampleBlue);
                $rowSamples++;
            }
            $rowAverage = $rowLuminance / max(1, $rowSamples);

            for ($column = 0; $column < $rowWidth; $column++) {
                $sourceX = min(
                    $targetWidth - 1,
                    (int) floor(($column / max(1, $rowWidth - 1)) * ($targetWidth - 1)),
                );
                $artPixel = imagecolorat($artwork, $sourceX, $row);
                $alpha = ($artPixel >> 24) & 0x7f;
                if ($alpha >= 127) {
                    continue;
                }

                $destinationX = $left + $column;
                $destinationY = $top + $row;
                if ($destinationX < 0 || $destinationX >= self::SIZE || $destinationY < 0 || $destinationY >= self::SIZE) {
                    continue;
                }

                $basePixel = imagecolorat($canvas, $destinationX, $destinationY);
                $baseRed = ($basePixel >> 16) & 0xff;
                $baseGreen = ($basePixel >> 8) & 0xff;
                $baseBlue = $basePixel & 0xff;
                $fabricLuminance = (0.2126 * $baseRed) + (0.7152 * $baseGreen) + (0.0722 * $baseBlue);

                $artRed = ($artPixel >> 16) & 0xff;
                $artGreen = ($artPixel >> 8) & 0xff;
                $artBlue = $artPixel & 0xff;

                // Transfer local shirt highlights and folds into the ink. The
                // tiny deterministic weave variation prevents a flat digital surface.
                $shade = max(0.70, min(1.14, 0.92 + (($fabricLuminance - $rowAverage) / 48)));
                $weave = ((($destinationX * 13) + ($destinationY * 7)) % 5 - 2) * 0.012;
                $shade += $weave;

                $edgeFade = min(
                    1.0,
                    ($column + 1) / 2,
                    ($row + 1) / 2,
                    ($rowWidth - $column) / 2,
                    ($targetHeight - $row) / 2,
                );
                $opacity = ((127 - $alpha) / 127) * 0.90 * $edgeFade;

                $printRed = min(255, max(0, (int) round($artRed * $shade)));
                $printGreen = min(255, max(0, (int) round($artGreen * $shade)));
                $printBlue = min(255, max(0, (int) round($artBlue * $shade)));

                $outRed = (int) round(($printRed * $opacity) + ($baseRed * (1 - $opacity)));
                $outGreen = (int) round(($printGreen * $opacity) + ($baseGreen * (1 - $opacity)));
                $outBlue = (int) round(($printBlue * $opacity) + ($baseBlue * (1 - $opacity)));

                imagesetpixel($canvas, $destinationX, $destinationY, ($outRed << 16) | ($outGreen << 8) | $outBlue);
            }
        }

        imagedestroy($artwork);
    }
}
