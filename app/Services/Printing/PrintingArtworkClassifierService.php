<?php

namespace App\Services\Printing;

use RuntimeException;

final class PrintingArtworkClassifierService
{
    /**
     * Fail-safe classifier for business-card previews.
     * It never tries to infer semantic content from pixels; instead it uses
     * format, page count and physical aspect-ratio evidence so unrelated square
     * product photos are not automatically treated as print-ready artwork.
     */
    public function classifyBusinessCardImage(string $base64, string $caption = ''): array
    {
        $bytes = $this->decodeBase64($base64, 12 * 1024 * 1024);
        $info = @getimagesizefromstring($bytes);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Gönderilen görselin ölçüleri okunamadı.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $ratio = $width / max(1, $height);
        $normalized = max($ratio, 1 / max(0.0001, $ratio));
        $captionLower = mb_strtolower(trim($caption), 'UTF-8');

        $explicitArtwork = str_contains($captionLower, 'kartvizit tasarım')
            || str_contains($captionLower, 'kartvizit tasarim')
            || str_contains($captionLower, 'ön yüz')
            || str_contains($captionLower, 'on yuz')
            || str_contains($captionLower, 'arka yüz')
            || str_contains($captionLower, 'arka yuz');

        // Common business-card art is roughly 1.5-1.9:1 (or portrait inverse).
        // Allow bleed/crop variance but deliberately reject near-square imagery.
        $cardLikeRatio = $normalized >= 1.42 && $normalized <= 2.05;

        if ($cardLikeRatio || $explicitArtwork) {
            return [
                'role' => 'print_artwork',
                'confidence' => $explicitArtwork ? 'high' : 'medium',
                'width' => $width,
                'height' => $height,
                'ratio' => round($ratio, 4),
            ];
        }

        return [
            'role' => 'unknown',
            'confidence' => 'high',
            'width' => $width,
            'height' => $height,
            'ratio' => round($ratio, 4),
            'reason' => 'business_card_ratio_mismatch',
        ];
    }

    public function classifyPdfPages(array $pages): array
    {
        if ($pages === []) {
            return ['role' => 'unknown', 'confidence' => 'high', 'reason' => 'empty_pdf'];
        }

        $classifications = [];
        foreach (array_slice($pages, 0, 2) as $page) {
            if (! is_string($page) || trim($page) === '') {
                continue;
            }
            $classifications[] = $this->classifyBusinessCardImage($page, 'kartvizit tasarımı');
        }

        if ($classifications === []) {
            return ['role' => 'unknown', 'confidence' => 'high', 'reason' => 'unreadable_pdf_pages'];
        }

        return [
            'role' => 'print_artwork',
            'confidence' => 'high',
            'pages' => count($classifications),
        ];
    }

    private function decodeBase64(string $encoded, int $maxBytes): string
    {
        $encoded = trim($encoded);
        if (str_contains($encoded, ';base64,')) {
            $encoded = explode(';base64,', $encoded, 2)[1] ?? '';
        }

        $bytes = base64_decode($encoded, true);
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > $maxBytes) {
            throw new RuntimeException('Görsel çözülemedi veya güvenli boyut sınırını aşıyor.');
        }

        return $bytes;
    }
}
