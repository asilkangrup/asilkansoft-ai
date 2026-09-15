<?php

namespace App\Services\Printing;

use RuntimeException;

final class PrintingBusinessCardDesignService
{
    private const WIDTH = 1700;
    private const HEIGHT = 1000;

    /** @return array{front:string,back:string} */
    public function create(array $brief, ?string $creativeBackgroundBase64 = null): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Sunucuda GD görsel desteği etkin değil.');
        }

        $brand = trim((string) ($brief['brand_name'] ?? ''));
        if ($brand === '') {
            throw new RuntimeException('Kartvizit tasarımı için marka/firma adı eksik.');
        }

        $style = mb_strtolower(trim((string) ($brief['style'] ?? 'modern')), 'UTF-8');
        [$background, $foreground, $accent] = $this->palette((string) ($brief['colors'] ?? ''), $style);

        $creativeFront = $creativeBackgroundBase64 !== null
            ? $this->canvasFromBackground($creativeBackgroundBase64)
            : null;
        $front = $creativeFront ?? $this->canvas($background);
        $back = $this->canvas($background);

        $this->drawFront($front, $brand, $style, $foreground, $accent, $brief, $creativeFront !== null);
        $this->drawBack($back, $brand, $foreground, $accent, $brief);

        return [
            'front' => $this->encodePng($front),
            'back' => $this->encodePng($back),
        ];
    }

    private function drawFront(
        mixed $image,
        string $brand,
        string $style,
        array $fg,
        array $accent,
        array $brief,
        bool $creativeBackground,
    ): void {
        $accentColor = imagecolorallocate($image, ...$accent);
        $fgColor = imagecolorallocate($image, ...$fg);

        if ($creativeBackground) {
            imagealphablending($image, true);
            $lightText = array_sum($fg) > 500;
            $panel = $lightText
                ? imagecolorallocatealpha($image, 10, 10, 12, 48)
                : imagecolorallocatealpha($image, 255, 255, 255, 38);
            imagefilledrectangle($image, 390, 300, 1310, 670, $panel);
            imagefilledrectangle($image, 390, 670, 870, 682, $accentColor);
        } elseif (in_array($style, ['premium', 'lüks'], true)) {
            imagefilledrectangle($image, 0, 0, 90, self::HEIGHT, $accentColor);
            imagefilledrectangle($image, 1500, 0, self::WIDTH, 26, $accentColor);
        } elseif ($style === 'minimal' || $style === 'sade') {
            imagefilledrectangle($image, 130, 760, 620, 772, $accentColor);
        } else {
            imagefilledpolygon($image, [0, 0, 560, 0, 250, self::HEIGHT, 0, self::HEIGHT], 4, $accentColor);
        }

        $this->text($image, $brand, 720, 450, 74, $fgColor, true, 'center');
        $sector = trim((string) ($brief['sector'] ?? ''));
        if ($sector !== '') {
            $this->text($image, mb_strtoupper($sector, 'UTF-8'), 720, 535, 28, $fgColor, false, 'center');
        }
        $slogan = trim((string) ($brief['slogan'] ?? ''));
        if ($slogan !== '') {
            $this->text($image, $slogan, 720, 610, 24, $fgColor, false, 'center');
        }
    }

    private function drawBack(mixed $image, string $brand, array $fg, array $accent, array $brief): void
    {
        $accentColor = imagecolorallocate($image, ...$accent);
        $fgColor = imagecolorallocate($image, ...$fg);

        imagefilledrectangle($image, 0, 0, 34, self::HEIGHT, $accentColor);
        $this->text($image, $brand, 125, 190, 48, $fgColor, true);

        $lines = [];
        foreach ([
            'phone' => 'Tel',
            'email' => 'E-posta',
            'instagram' => 'Instagram',
            'address' => 'Adres',
        ] as $field => $label) {
            $value = trim((string) ($brief[$field] ?? ''));
            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        $content = trim((string) ($brief['content'] ?? ''));
        if ($lines === [] && $content !== '') {
            $lines = array_slice(preg_split('/\R+/u', $content) ?: [], 0, 5);
        }

        $y = 340;
        foreach (array_slice($lines, 0, 6) as $line) {
            $this->text($image, trim((string) $line), 125, $y, 30, $fgColor);
            $y += 68;
        }

        $this->text($image, 'WAI Tasarım Önizlemesi', 125, 885, 20, $accentColor);
    }

    private function canvas(array $background): mixed
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $bg = imagecolorallocate($image, ...$background);
        imagefill($image, 0, 0, $bg);
        return $image;
    }

    private function canvasFromBackground(string $encoded): mixed
    {
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }
        $bytes = base64_decode(trim($encoded), true);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $sourceW = imagesx($source);
        $sourceH = imagesy($source);
        if ($sourceW < 1 || $sourceH < 1) {
            imagedestroy($source);
            return null;
        }

        $scale = max(self::WIDTH / $sourceW, self::HEIGHT / $sourceH);
        $drawW = max(1, (int) ceil($sourceW * $scale));
        $drawH = max(1, (int) ceil($sourceH * $scale));
        $offsetX = (int) floor(($drawW - self::WIDTH) / 2);
        $offsetY = (int) floor(($drawH - self::HEIGHT) / 2);

        $resized = imagecreatetruecolor($drawW, $drawH);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $drawW, $drawH, $sourceW, $sourceH);
        imagedestroy($source);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopy($canvas, $resized, 0, 0, $offsetX, $offsetY, self::WIDTH, self::HEIGHT);
        imagedestroy($resized);

        return $canvas;
    }

    private function palette(string $requested, string $style): array
    {
        $requestedLower = mb_strtolower($requested, 'UTF-8');
        $hex = $this->firstHex($requested);
        $accent = $hex ?? match (true) {
            str_contains($requestedLower, 'mavi') => [32, 105, 210],
            str_contains($requestedLower, 'kırmızı'), str_contains($requestedLower, 'kirmizi') => [190, 35, 45],
            str_contains($requestedLower, 'yeşil'), str_contains($requestedLower, 'yesil') => [34, 139, 84],
            str_contains($requestedLower, 'mor') => [112, 65, 180],
            str_contains($requestedLower, 'turuncu') => [225, 112, 30],
            str_contains($requestedLower, 'sarı'), str_contains($requestedLower, 'sari') => [215, 168, 35],
            in_array($style, ['premium', 'lüks'], true) => [191, 150, 62],
            default => [36, 98, 173],
        };

        if (in_array($style, ['premium', 'lüks'], true)) {
            return [[18, 18, 20], [244, 242, 236], $accent];
        }

        return [[248, 248, 247], [25, 31, 38], $accent];
    }

    private function firstHex(string $value): ?array
    {
        if (! preg_match('/#([A-Fa-f0-9]{6})\b/', $value, $m)) {
            return null;
        }
        $hex = $m[1];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function text(
        mixed $image,
        string $text,
        int $x,
        int $y,
        int $size,
        int $color,
        bool $bold = false,
        string $align = 'left',
    ): void {
        $text = trim($text);
        if ($text === '') return;

        $font = $this->font($bold);
        if ($font !== null && function_exists('imagettftext')) {
            if ($align === 'center') {
                $box = imagettfbbox($size, 0, $font, $text);
                if (is_array($box)) {
                    $width = abs((int) $box[2] - (int) $box[0]);
                    $x = max(20, (int) round((self::WIDTH - $width) / 2));
                }
            }
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }

        imagestring($image, 5, $x, max(0, $y - 18), $text, $color);
    }

    private function font(bool $bold): ?string
    {
        $candidates = $bold
            ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf']
            : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf'];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) return $candidate;
        }
        return null;
    }

    private function encodePng(mixed $image): string
    {
        ob_start();
        imagepng($image, null, 7);
        $bytes = ob_get_clean();
        imagedestroy($image);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Kartvizit tasarım çıktısı oluşturulamadı.');
        }

        return base64_encode($bytes);
    }
}
