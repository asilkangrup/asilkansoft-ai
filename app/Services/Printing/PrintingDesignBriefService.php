<?php

namespace App\Services\Printing;

final class PrintingDesignBriefService
{
    private const REQUIRED = [
        'brand_name',
        'style',
        'content',
    ];

    /**
     * Collect enough structured information to hand a design job to a creative
     * renderer without forcing the customer through a rigid form.
     */
    public function collect(string $message, array $brief = []): array
    {
        $text = trim($message);
        $lower = mb_strtolower($text, 'UTF-8');

        if (preg_match('/(?:firma|marka|işletme|isletme)\s*(?:adı|adi|ismi)?\s*[:\-]?\s*([^,\n]{2,80})/iu', $text, $m)) {
            $brief['brand_name'] = trim($m[1]);
        }

        foreach (['modern', 'kurumsal', 'sade', 'minimal', 'lüks', 'luks', 'premium', 'renkli', 'eğlenceli', 'eglenceli', 'klasik'] as $style) {
            if (str_contains($lower, $style)) {
                $brief['style'] = match ($style) {
                    'luks' => 'lüks',
                    'eglenceli' => 'eğlenceli',
                    default => $style,
                };
                break;
            }
        }

        if (preg_match('/(?:renk|renkler|renk tercihi)\s*[:\-]?\s*([^\n]{2,80})/iu', $text, $m)) {
            $brief['colors'] = trim($m[1]);
        }

        if (preg_match('/(?:sektör|sektor)\s*[:\-]?\s*([^,\n]{2,80})/iu', $text, $m)) {
            $brief['sector'] = trim($m[1]);
        }

        if (preg_match('/\b(?:\+?90\s*)?(?:0?5\d{2})[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}\b/u', $text, $m)) {
            $brief['phone'] = trim($m[0]);
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $m)) {
            $brief['email'] = trim($m[0]);
        }

        if (preg_match('/(?:instagram|insta|ig)\s*[:\-]?\s*@?([A-Za-z0-9._]{2,40})/iu', $text, $m)) {
            $brief['instagram'] = '@'.trim($m[1]);
        }

        // If the customer gives a reasonably detailed free-form brief, preserve
        // it verbatim instead of trying to over-structure every sentence.
        if (mb_strlen($text, 'UTF-8') >= 18 && ! $this->isGenericDesignAnswer($lower)) {
            $brief['content'] = $this->mergeContent((string) ($brief['content'] ?? ''), $text);
        }

        return $brief;
    }

    public function missing(array $brief): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            static fn (string $field) => ! isset($brief[$field]) || trim((string) $brief[$field]) === ''
        ));
    }

    public function questions(array $missing, int $max = 2): array
    {
        $map = [
            'brand_name' => 'Tasarımda kullanacağımız firma veya marka adı nedir?',
            'style' => 'Nasıl bir görünüm istersiniz; sade, modern, kurumsal veya daha premium bir tarz olabilir?',
            'content' => 'Tasarımda mutlaka yer alması gereken bilgileri paylaşır mısınız? Örneğin telefon, adres, sosyal medya veya slogan.',
        ];

        return array_values(array_map(
            static fn (string $field) => $map[$field] ?? ucfirst(str_replace('_', ' ', $field)).' bilgisini paylaşır mısınız?',
            array_slice($missing, 0, max(1, $max))
        ));
    }

    public function ready(array $brief): bool
    {
        return $this->missing($brief) === [];
    }

    private function isGenericDesignAnswer(string $text): bool
    {
        return preg_match('/^(?:siz yapın|siz yapin|siz tasarlayın|siz tasarlayin|tasarım yok|tasarim yok|tasarımım yok|tasarimim yok)$/u', trim($text)) === 1;
    }

    private function mergeContent(string $existing, string $new): string
    {
        $existing = trim($existing);
        $new = trim($new);

        if ($existing === '') {
            return $new;
        }

        if (str_contains(mb_strtolower($existing, 'UTF-8'), mb_strtolower($new, 'UTF-8'))) {
            return $existing;
        }

        return $existing."\n".$new;
    }
}
