<?php

namespace App\Services\Printing;

use RuntimeException;

final class PrintingBusinessCardDesignService
{
    private const WIDTH = 1700;
    private const HEIGHT = 1000;

    /** @return array{front:string,back:string} */
    public function create(array $brief): array
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

        $front = $this->canvas($background);
        $back = $this->canvas($background);

        $this->drawFront($front, $brand, $style, $foreground, $accent, $brief);
        $this->drawBack($back, $brand, $foreground, $accent, $brief);

        return [
            'front' => $this->encodePng($front),
            'back' => $this->encodePng($back),
        ];
    }

    private function drawFront(mixed $image, string $brand, string $style, array $fg, array $accent, array $brief): void
    {
        $accentColor = imagecolorallocate($image, ...$accent);
        $fgColor = imagecolorallocate($image, ...$fg);

        if (in_array($style, ['premium', 'lüks'], true)) {
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

        $y = 360;
        foreach (array_slice($lines, 0, 5) as $line) {
            $this->text($image, trim((string) $line), 125, $y, 30, $fgColor);
            $y += 72;
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

    private function palette(string $requested, string $style): array
    {
        $requested = mb_strtolower($requested, 'UTF-8');
        $accent = match (true) {
            str_contains($requested, 'mavi') => [32, 105, 210],
            str_contains($requested, 'kırmızı'), str_contains($requested, 'kirmizi') => [190, 35, 45],
            str_contains($requested, 'yeşil'), str_contains($requested, 'yesil') => [34, 139, 84],
            str_contains($requested, 'mor') => [112, 65, 180],
            str_contains($requested, 'turuncu') => [225, 112, 30],
            str_contains($requested, 'sarı'), str_contains($requested, 'sari') => [215, 168, 35],
            in_array($style, ['premium', 'lüks'], true) => [191, 150, 62],
            default => [36, 98, 173],
        };

        if (in_array($style, ['premium', 'lüks'], true)) {
            return [[18, 18, 20], [244, 242, 236], $accent];
        }

        return [[248, 248, 247], [25, 31, 38], $accent];
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
