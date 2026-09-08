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
            $foldShift = (int) round(sin(($top + $row) / 31) * 0.65);
            $left = (int) round($centerX - ($rowWidth / 2)) + $foldShift;

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
                $shade = max(0.70, min(1.14, 0.72 + ($fabricLuminance / 105)));
                $weave = ((($destinationX * 13) + ($destinationY * 7)) % 5 - 2) * 0.006;
                $shade += $weave;

                $edgeFade = min(
                    1.0,
                    ($column + 1) / 2,
                    ($row + 1) / 2,
                    ($rowWidth - $column) / 2,
                    ($targetHeight - $row) / 2,
                );
                $opacity = ((127 - $alpha) / 127) * 0.94 * $edgeFade;

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
