<?php

namespace App\Services\Printing;

use RuntimeException;

final class PrintingReferenceAnalysisService
{
    /** @return array{primary_color:string,secondary_color:string,tone:string,aspect_ratio:float} */
    public function analyze(string $base64): array
    {
        $bytes = $this->decode($base64);
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new RuntimeException('Referans görseli açılamadı.');
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 1 || $height < 1) {
                throw new RuntimeException('Referans görselinin ölçüsü okunamadı.');
            }

            $buckets = [];
            $luminanceTotal = 0.0;
            $samples = 0;
            $stepX = max(1, (int) floor($width / 36));
            $stepY = max(1, (int) floor($height / 36));

            for ($y = 0; $y < $height; $y += $stepY) {
                for ($x = 0; $x < $width; $x += $stepX) {
                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xff;
                    $g = ($rgb >> 8) & 0xff;
                    $b = $rgb & 0xff;

                    $luminanceTotal += (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);
                    $samples++;

                    // Ignore almost-white/black pixels that are commonly margins,
                    // text, shadows or transparent-composite backgrounds.
                    $max = max($r, $g, $b);
                    $min = min($r, $g, $b);
                    if ($max > 246 || $max < 24 || ($max - $min) < 12) {
                        continue;
                    }

                    $qr = (int) round($r / 32) * 32;
                    $qg = (int) round($g / 32) * 32;
                    $qb = (int) round($b / 32) * 32;
                    $key = sprintf('%02X%02X%02X', min(255, $qr), min(255, $qg), min(255, $qb));
                    $buckets[$key] = ($buckets[$key] ?? 0) + 1;
                }
            }

            arsort($buckets);
            $colors = array_keys($buckets);
            $primary = '#'.($colors[0] ?? '202020');
            $secondary = '#'.($colors[1] ?? ($colors[0] ?? 'B99A55'));
            $average = $samples > 0 ? $luminanceTotal / $samples : 128;

            return [
                'primary_color' => $primary,
                'secondary_color' => $secondary,
                'tone' => $average < 100 ? 'dark' : ($average > 185 ? 'light' : 'balanced'),
                'aspect_ratio' => round($width / $height, 3),
            ];
        } finally {
            imagedestroy($image);
        }
    }

    private function decode(string $encoded): string
    {
        $encoded = trim($encoded);
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }

        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 12 * 1024 * 1024) {
            throw new RuntimeException('Referans görseli çözülemedi veya güvenli boyut sınırını aşıyor.');
        }

        return $bytes;
    }
}
