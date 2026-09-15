<?php

namespace App\Services\Printing;

use RuntimeException;

final class PrintingBusinessCardMockupService
{
    private const CANVAS = 1200;
    private const CARD_W = 650;
    private const CARD_H = 382;

    /**
     * Produce a presentation mockup without redrawing the customer's artwork.
     * The supplied pixels are only resized/rotated and placed on a studio scene.
     */
    public function create(string $frontBase64, ?string $backBase64 = null, string $finish = 'mat'): string
    {
        $this->assertGd();

        $front = $this->decodeArtwork($frontBase64);
        $back = filled($backBase64) ? $this->decodeArtwork((string) $backBase64) : null;

        $canvas = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $this->paintStudioBackground($canvas);

        if ($back !== null) {
            $this->placeCard($canvas, $back, 420, 335, -7.0, $finish, 0.86);
            $this->placeCard($canvas, $front, 640, 665, 6.5, $finish, 1.0);
        } else {
            $this->placeCard($canvas, $front, 600, 600, -3.5, $finish, 1.0);
        }

        ob_start();
        imagejpeg($canvas, null, 94);
        $jpeg = ob_get_clean();

        imagedestroy($front);
        if ($back !== null) {
            imagedestroy($back);
        }
        imagedestroy($canvas);

        if (! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Kartvizit mockup çıktısı oluşturulamadı.');
        }

        return base64_encode($jpeg);
    }

    private function assertGd(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Sunucuda GD görsel desteği etkin değil.');
        }
    }

    private function decodeArtwork(string $encoded): mixed
    {
        $encoded = trim($encoded);
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }

        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 12 * 1024 * 1024) {
            throw new RuntimeException('Kartvizit tasarımı çözülemedi veya güvenli boyut sınırını aşıyor.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('Kartvizit önizlemesi için JPG veya PNG tasarım gönderilmelidir.');
        }

        return $image;
    }

    private function paintStudioBackground(mixed $canvas): void
    {
        for ($y = 0; $y < self::CANVAS; $y++) {
            $ratio = $y / max(1, self::CANVAS - 1);
            $v = (int) round(244 - (18 * $ratio));
            $color = imagecolorallocate($canvas, $v, $v, max(0, $v - 2));
            imageline($canvas, 0, $y, self::CANVAS, $y, $color);
        }

        $this->softEllipse($canvas, 600, 865, 760, 180, [35, 35, 35], 88);
        $this->softEllipse($canvas, 600, 900, 560, 110, [30, 30, 30], 112);
    }

    private function placeCard(
        mixed $canvas,
        mixed $artwork,
        int $centerX,
        int $centerY,
        float $angle,
        string $finish,
        float $scale,
    ): void {
        $width = max(220, (int) round(self::CARD_W * $scale));
        $height = max(130, (int) round(self::CARD_H * $scale));

        $card = imagecreatetruecolor($width + 18, $height + 18);
        imagealphablending($card, true);
        imagesavealpha($card, true);
        $transparent = imagecolorallocatealpha($card, 0, 0, 0, 127);
        imagefill($card, 0, 0, $transparent);

        $paper = imagecolorallocate($card, 251, 250, 248);
        imagefilledrectangle($card, 5, 5, $width + 12, $height + 12, $paper);

        $fitted = $this->fitArtwork($artwork, $width, $height);
        imagecopy($card, $fitted, 9, 9, 0, 0, $width, $height);
        imagedestroy($fitted);

        $this->applyFinish($card, 9, 9, $width, $height, $finish);

        $rotated = imagerotate($card, $angle, imagecolorallocatealpha($card, 0, 0, 0, 127));
        imagedestroy($card);

        if ($rotated === false) {
            throw new RuntimeException('Kartvizit sahnesi döndürülemedi.');
        }

        imagesavealpha($rotated, true);
        imagealphablending($rotated, true);

        $shadowX = $centerX + 8;
        $shadowY = $centerY + 24;
        $this->softEllipse($canvas, $shadowX, $shadowY, (int) (imagesx($rotated) * .82), 76, [20, 20, 20], 102);

        $x = (int) round($centerX - (imagesx($rotated) / 2));
        $y = (int) round($centerY - (imagesy($rotated) / 2));
        imagecopy($canvas, $rotated, $x, $y, 0, 0, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
    }

    private function fitArtwork(mixed $artwork, int $width, int $height): mixed
    {
        $sourceW = imagesx($artwork);
        $sourceH = imagesy($artwork);
        if ($sourceW < 1 || $sourceH < 1) {
            throw new RuntimeException('Kartvizit tasarım ölçüsü okunamadı.');
        }

        // Cover-fit to the physical card ratio. This mirrors print trimming while
        // avoiding any AI redraw that could alter names, phones or logos.
        $scale = max($width / $sourceW, $height / $sourceH);
        $drawW = max(1, (int) ceil($sourceW * $scale));
        $drawH = max(1, (int) ceil($sourceH * $scale));
        $offsetX = (int) floor(($drawW - $width) / 2);
        $offsetY = (int) floor(($drawH - $height) / 2);

        $resized = imagecreatetruecolor($drawW, $drawH);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $artwork, 0, 0, 0, 0, $drawW, $drawH, $sourceW, $sourceH);

        $output = imagecreatetruecolor($width, $height);
        imagecopy($output, $resized, 0, 0, $offsetX, $offsetY, $width, $height);
        imagedestroy($resized);

        return $output;
    }

    private function applyFinish(mixed $card, int $x, int $y, int $width, int $height, string $finish): void
    {
        $finish = mb_strtolower(trim($finish), 'UTF-8');
        if ($finish !== 'parlak') {
            return;
        }

        $shine = imagecolorallocatealpha($card, 255, 255, 255, 105);
        for ($i = 0; $i < 6; $i++) {
            $startX = $x + (int) round($width * (.05 + ($i * .025)));
            imagefilledpolygon($card, [
                $startX, $y,
                $startX + (int) round($width * .12), $y,
                $startX + (int) round($width * .52), $y + $height,
                $startX + (int) round($width * .40), $y + $height,
            ], 4, $shine);
        }
    }

    private function softEllipse(
        mixed $canvas,
        int $centerX,
        int $centerY,
        int $width,
        int $height,
        array $rgb,
        int $baseAlpha,
    ): void {
        for ($i = 10; $i >= 1; $i--) {
            $factor = $i / 10;
            $alpha = min(126, max(0, (int) round($baseAlpha + ((1 - $factor) * 30))));
            $color = imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], $alpha);
            imagefilledellipse(
                $canvas,
                $centerX,
                $centerY,
                max(1, (int) round($width * $factor)),
                max(1, (int) round($height * $factor)),
                $color,
            );
        }
    }
}
