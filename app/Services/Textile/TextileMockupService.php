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
        string $product = 'Premium Oversize Tişört',
        array $additionalPrints = [],
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

        [$canvas, $templateKind, $templateColor] = $this->loadStudioTemplate($product, $shirtColor);
        if ($shirtColor !== $templateColor) {
            $this->recolorGarment($canvas, $shirtColor, $templateKind);
        }
        $this->placeNaturalPrint($canvas, $logo, $position, $templateKind);

        foreach (array_slice($additionalPrints, 0, 8) as $additionalPrint) {
            if (! is_array($additionalPrint)) {
                continue;
            }

            $encoded = trim((string) ($additionalPrint['logo_base64'] ?? ''));
            $extraPosition = trim((string) ($additionalPrint['position'] ?? ''));
            if ($encoded === '' || $extraPosition === '') {
                continue;
            }

            $extraBytes = base64_decode($this->cleanBase64($encoded), true);
            if (! is_string($extraBytes) || $extraBytes === '' || strlen($extraBytes) > 8 * 1024 * 1024) {
                continue;
            }

            $extraLogo = @imagecreatefromstring($extraBytes);
            if ($extraLogo === false) {
                continue;
            }

            $this->placeNaturalPrint($canvas, $extraLogo, $extraPosition, $templateKind);
            imagedestroy($extraLogo);
        }

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
     * Creates a photographic dark magic-mug preview. Artwork keeps its original
     * aspect ratio and the ceramic remains visible around a realistic print area.
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
        $wrap = null;

        if ($multiple) {
            // Build one continuous production strip and photograph the same
            // revealed magic mug from right, centre and left viewing angles.
            $wrap = $this->buildMugWrap($artworks, count($artworks) * 700, 700);
            // Aim the outer views at the artwork joins. Each mug therefore
            // shows two neighbouring photos tapering around the cylinder,
            // making the three renders read as rotations of one continuous
            // wrap instead of three unrelated square prints.
            $this->placePhotoMugWrap($canvas, $wrap, 224, 386, 314, 326, 1 / 3);
            $this->placePhotoMugWrap($canvas, $wrap, 626, 386, 314, 326, 0.50);
            $this->placePhotoMugWrap($canvas, $wrap, 1024, 386, 314, 326, 2 / 3);
        } else {
            $this->placePhotoMugPrint($canvas, $artworks[0], 615, 635, 390, 445);
        }

        ob_start();
        imagejpeg($canvas, null, 93);
        $jpeg = ob_get_clean();

        foreach ($artworks as $artwork) {
            imagedestroy($artwork);
        }
        if ($wrap !== null) {
            imagedestroy($wrap);
        }
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Bardak mockup çıktısı oluşturulamadı.');
        }

        return base64_encode($jpeg);
    }

    private function loadPhotoMugTemplate(bool $multiple): mixed
    {
        $filename = $multiple ? 'magic-mug-triple-revealed-v4.jpg' : 'magic-mug-single-v3.jpg';
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

    private function placePhotoMugWrap(
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
            $angle = asin(max(-1.0, min(1.0, $normal)));
            $sourceNormal = $viewCenter + ($angle / (2 * M_PI));
            $sourceX = ((int) round($sourceNormal * $sourceWidth) % $sourceWidth + $sourceWidth) % $sourceWidth;
            $topBow = (int) round((1 - $curve) * 8);
            $bottomBow = (int) round((1 - $curve) * 7);
            $columnHeight = max(1, $height - $topBow - $bottomBow);

            for ($dy = 0; $dy < $columnHeight; $dy++) {
                $destinationX = $x + $dx;
                $destinationY = $y + $dy + $topBow;
                $sourceY = min(
                    $sourceHeight - 1,
                    (int) floor(($dy / max(1, $columnHeight - 1)) * ($sourceHeight - 1)),
                );

                $artPixel = imagecolorat($wrap, $sourceX, $sourceY);
                $basePixel = imagecolorat($canvas, $destinationX, $destinationY);
                $artRed = ($artPixel >> 16) & 0xff;
                $artGreen = ($artPixel >> 8) & 0xff;
                $artBlue = $artPixel & 0xff;
                $baseRed = ($basePixel >> 16) & 0xff;
                $baseGreen = ($basePixel >> 8) & 0xff;
                $baseBlue = $basePixel & 0xff;
                $baseLuminance = (0.2126 * $baseRed) + (0.7152 * $baseGreen) + (0.0722 * $baseBlue);

                // Sublimation ink follows the cylinder while the real white
                // ceramic's lighting and narrow specular highlights stay visible.
                $cylinderShade = 0.78 + (0.22 * pow($curve, 0.72));
                $surfaceShade = max(0.90, min(1.06, $baseLuminance / 236));
                $shade = $cylinderShade * $surfaceShade;
                $specular = max(0.0, min(0.26, ($baseLuminance - 242) / 55));
                $edgeFade = min(
                    1.0,
                    ($dx + 1) / 4,
                    ($width - $dx) / 4,
                    ($dy + 1) / 2,
                    ($columnHeight - $dy) / 2,
                );
                $opacity = $edgeFade * (0.975 - $specular);

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

    private function placePhotoMugPrint(
        mixed $canvas,
        mixed $artwork,
        int $centerX,
        int $centerY,
        int $maxWidth,
        int $maxHeight,
    ): void {
        $sourceWidth = imagesx($artwork);
        $sourceHeight = imagesy($artwork);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return;
        }

        // Never stretch or crop customer photos. Square, portrait and landscape
        // files are fitted into the real printable area at their original ratio.
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $x = (int) round($centerX - ($width / 2));
        $y = (int) round($centerY - ($height / 2));

        for ($dx = 0; $dx < $width; $dx++) {
            $normal = (($dx / max(1, $width - 1)) * 2) - 1;
            $curve = sqrt(max(0.0, 1 - ($normal * $normal)));

            // Mild inverse-cylinder mapping makes the print follow the ceramic
            // without turning a normal photo into an edge-to-edge mug wrap.
            $projected = asin($normal * 0.78) / asin(0.78);
            $sourceX = min(
                $sourceWidth - 1,
                max(0, (int) round((0.5 + ($projected / 2)) * ($sourceWidth - 1))),
            );
            $topBow = (int) round((1 - $curve) * 6);
            $bottomBow = (int) round((1 - $curve) * 5);
            $columnHeight = max(1, $height - $topBow - $bottomBow);

            for ($dy = 0; $dy < $columnHeight; $dy++) {
                $destinationX = $x + $dx;
                $destinationY = $y + $dy + $topBow;
                if (
                    $destinationX < 0 || $destinationX >= imagesx($canvas)
                    || $destinationY < 0 || $destinationY >= imagesy($canvas)
                ) {
                    continue;
                }

                $sourceY = min($sourceHeight - 1, (int) floor(($dy / max(1, $columnHeight - 1)) * ($sourceHeight - 1)));
                $artPixel = imagecolorat($artwork, $sourceX, $sourceY);
                $artAlpha = ($artPixel >> 24) & 0x7f;
                if ($artAlpha >= 127) {
                    continue;
                }
                $basePixel = imagecolorat($canvas, $destinationX, $destinationY);

                $artRed = ($artPixel >> 16) & 0xff;
                $artGreen = ($artPixel >> 8) & 0xff;
                $artBlue = $artPixel & 0xff;
                $baseRed = ($basePixel >> 16) & 0xff;
                $baseGreen = ($basePixel >> 8) & 0xff;
                $baseBlue = $basePixel & 0xff;
                $baseLuminance = (0.2126 * $baseRed) + (0.7152 * $baseGreen) + (0.0722 * $baseBlue);

                // Keep the supplied pixels dominant while transferring the
                // real mug's black side shading and specular ceramic streaks.
                $cylinderShade = 0.80 + (0.20 * pow($curve, 0.78));
                $shade = $cylinderShade * max(0.95, min(1.07, 0.97 + ($baseLuminance / 1050)));

                $edgeFade = min(
                    1.0,
                    ($dx + 1) / 3,
                    ($width - $dx) / 3,
                    ($dy + 1) / 2,
                    ($columnHeight - $dy) / 2,
                );
                $specular = max(0.0, min(0.42, ($baseLuminance - 48) / 360));
                $sourceOpacity = (127 - $artAlpha) / 127;
                $opacity = $sourceOpacity * $edgeFade * (0.965 - $specular);

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

    /**
     * @return array{0: mixed, 1: string, 2: string}
     */
    private function loadStudioTemplate(string $product, string $shirtColor): array
    {
        $normalized = mb_strtolower($product, 'UTF-8');
        $kind = 'oversize';
        $templateColor = 'black';
        $filename = 'oversize-black-studio.jpg';

        if (str_contains($normalized, 'regular') || str_contains($normalized, 'bisiklet')) {
            $kind = 'regular';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'regular-white-studio.jpg'
                : 'regular-black-studio.jpg';
        } elseif (str_contains($normalized, 'oversize')) {
            $kind = 'oversize';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'oversize-white-studio.jpg'
                : 'oversize-black-studio.jpg';
        } elseif (str_contains($normalized, 'kapüşon') || str_contains($normalized, 'kapuson') || str_contains($normalized, 'sweat')) {
            $kind = 'hoodie';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'hoodie-white-studio.jpg'
                : 'hoodie-black-studio.jpg';
        } elseif (str_contains($normalized, 'polo')) {
            $kind = 'polo';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'polo-white-studio.jpg'
                : 'polo-black-studio.jpg';
        } elseif (str_contains($normalized, 'polyester') && (str_contains($normalized, 'şapka') || str_contains($normalized, 'sapka'))) {
            $kind = 'cap';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'cap-polyester-white-studio.jpg'
                : 'cap-polyester-black-studio.jpg';
        } elseif (str_contains($normalized, 'şapka') || str_contains($normalized, 'sapka')) {
            $kind = 'cap';
            $templateColor = $shirtColor === 'white' ? 'white' : 'black';
            $filename = $templateColor === 'white'
                ? 'cap-cotton-white-studio.jpg'
                : 'cap-cotton-black-studio.jpg';
        }

        $path = public_path('assets/textile/catalog/'.$filename);
        if (! is_file($path)) {
            throw new RuntimeException('Seçilen ürünün fotoğrafik şablonu bulunamadı.');
        }

        $source = @imagecreatefromjpeg($path);
        if ($source === false) {
            throw new RuntimeException('Seçilen ürünün fotoğrafik şablonu açılamadı.');
        }

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        if ($canvas === false) {
            imagedestroy($source);
            throw new RuntimeException('Mockup çalışma alanı oluşturulamadı.');
        }

        $background = imagecolorallocate($canvas, 239, 239, 239);
        imagefill($canvas, 0, 0, $background);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(self::SIZE / max(1, $sourceWidth), self::SIZE / max(1, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $left = (int) round((self::SIZE - $targetWidth) / 2);
        $top = (int) round((self::SIZE - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $left,
            $top,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
        imagedestroy($source);

        return [$canvas, $kind, $templateColor];
    }

    private function recolorGarment(mixed $canvas, string $shirtColor, string $kind): void
    {
        $target = match ($shirtColor) {
            'white' => [226, 225, 218],
            'navy' => [31, 48, 76],
            'burgundy' => [105, 35, 48],
            'beige' => [190, 169, 137],
            'red' => [185, 35, 42],
            'blue' => [34, 91, 170],
            'turquoise' => [33, 164, 178],
            'green' => [38, 117, 69],
            'yellow' => [222, 188, 34],
            'orange' => [218, 104, 30],
            'pink' => [219, 145, 166],
            'brown' => [113, 70, 48],
            'gray' => [122, 126, 132],
            'charcoal' => [60, 64, 70],
            default => null,
        };

        if ($target === null) {
            return;
        }

        for ($y = 35; $y < 1165; $y++) {
            for ($x = 35; $x < 1165; $x++) {
                $pixel = imagecolorat($canvas, $x, $y);
                $red = ($pixel >> 16) & 0xff;
                $green = ($pixel >> 8) & 0xff;
                $blue = $pixel & 0xff;
                $luminance = (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);

                if (! $this->isGarmentPixel($x, $y, $luminance, $kind)) {
                    continue;
                }

                $shade = max(0.48, min(1.24, 0.50 + ($luminance / 72)));
                $newRed = min(255, (int) round($target[0] * $shade));
                $newGreen = min(255, (int) round($target[1] * $shade));
                $newBlue = min(255, (int) round($target[2] * $shade));

                imagesetpixel($canvas, $x, $y, ($newRed << 16) | ($newGreen << 8) | $newBlue);
            }
        }
    }

    private function isGarmentPixel(int $x, int $y, float $luminance, string $kind): bool
    {
        if ($luminance > 125) {
            return false;
        }

        if ($kind === 'cap') {
            return $y >= 35 && $y <= 690 && $x >= 190 && $x <= 1010;
        }

        if (in_array($kind, ['hoodie', 'polo'], true)) {
            if ($this->isMannequinNeckPixel($x, $y)) {
                return false;
            }

            $bottom = $kind === 'hoodie' ? 1100 : 1135;

            return $y >= 115 && $y <= $bottom && $x >= 75 && $x <= 1125;
        }

        if ($this->isMannequinNeckPixel($x, $y)) {
            return false;
        }

        if ($y < 500) {
            return $x >= 70 && $x <= 1130;
        }

        return $x >= 245 && $x <= 955;
    }

    /**
     * Protects the mannequin with a tapered, rounded mask. A rectangular
     * exclusion leaves an artificial dark block around collars and hoods.
     */
    private function isMannequinNeckPixel(int $x, int $y): bool
    {
        if ($y < 18 || $y > 225) {
            return false;
        }

        $distance = abs($x - 600);
        if ($y <= 165) {
            return $distance <= 92;
        }

        $halfWidth = max(18, (int) round(92 - (($y - 165) * 1.22)));

        return $distance <= $halfWidth;
    }

    private function placeNaturalPrint(
        mixed $canvas,
        mixed $logo,
        string $position,
        string $kind,
    ): void {
        // Customers name the wearer's side. A front-facing product photo is
        // mirrored from the viewer's perspective, so render left on the
        // viewer's right and right on the viewer's left.
        $visualPosition = match ($visualPosition) {
            'left_chest' => 'right_chest',
            'right_chest' => 'left_chest',
            'left_sleeve' => 'right_sleeve',
            'right_sleeve' => 'left_sleeve',
            default => $position,
        };

        [$centerX, $centerY, $maxWidth, $maxHeight] = match ($kind) {
            'cap' => [600, 385, 340, 185],
            'hoodie' => match ($visualPosition) {
                'left_chest' => [430, 430, 185, 150],
                'right_chest' => [770, 430, 185, 150],
                'left_sleeve' => [245, 430, 140, 120],
                'right_sleeve' => [955, 430, 140, 120],
                'front_large', 'back_large' => [600, 515, 410, 330],
                default => [600, 450, 315, 245],
            },
            'polo' => match ($visualPosition) {
                'left_chest' => [435, 410, 175, 145],
                'right_chest' => [765, 410, 175, 145],
                'left_sleeve' => [250, 410, 135, 115],
                'right_sleeve' => [950, 410, 135, 115],
                'front_large', 'back_large' => [600, 540, 390, 345],
                default => [600, 500, 300, 240],
            },
            default => match ($visualPosition) {
                'left_chest' => [455, 385, 185, 155],
                'right_chest' => [745, 385, 185, 155],
                'left_sleeve' => [245, 405, 145, 125],
                'right_sleeve' => [955, 405, 145, 125],
                'front_large' => [600, 545, 430, 390],
                'back_large' => [600, 530, 420, 380],
                default => [600, 470, 310, 255],
            },
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
