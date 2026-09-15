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
     *
     * @param list<string> $pendingFields
     */
    public function collect(string $message, array $brief = [], array $pendingFields = []): array
    {
        $text = trim($message);
        $lower = mb_strtolower($text, 'UTF-8');

        if ($text === '') {
            return $brief;
        }

        if (preg_match('/(?:firma|marka|işletme|isletme)\s*(?:adı|adi|ismi)?\s*[:\-]?\s*([^,\n]{2,80})/iu', $text, $m)) {
            $brief['brand_name'] = $this->cleanBrand((string) $m[1]);
        }

        $detectedStyle = $this->detectStyle($lower);
        if ($detectedStyle !== null) {
            $brief['style'] = $detectedStyle;
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
        } elseif (preg_match('/^@([A-Za-z0-9._]{2,40})$/u', $text, $m)) {
            $brief['instagram'] = '@'.trim($m[1]);
        }

        if (preg_match('/(?:slogan|motto)\s*(?:ımız|imiz|umuz|ümüz)?\s*[:\-]?\s*([^\n]{2,120})/iu', $text, $m)) {
            $brief['slogan'] = trim($m[1]);
        }

        if (preg_match('/(?:adres|konum)\s*[:\-]?\s*([^\n]{4,180})/iu', $text, $m)) {
            $brief['address'] = trim($m[1]);
        }

        if (! isset($brief['brand_name']) && $this->isPending($pendingFields, 'brand_name')) {
            $candidate = $this->brandCandidate($text);
            if ($candidate !== null) {
                $brief['brand_name'] = $candidate;
            }
        }

        if (! isset($brief['style']) && $this->isPending($pendingFields, 'style') && $detectedStyle === null) {
            $candidateStyle = $this->detectStyle($lower);
            if ($candidateStyle !== null) {
                $brief['style'] = $candidateStyle;
            }
        }

        if ($this->hasUsableContent($brief)) {
            $brief['content'] = $this->contentSummary($brief, (string) ($brief['content'] ?? ''));
        } elseif ($this->isPending($pendingFields, 'content') && $this->looksLikeActualContent($text, $lower)) {
            $brief['content'] = $this->mergeContent((string) ($brief['content'] ?? ''), $text);
        } elseif ($this->looksLikeActualContent($text, $lower) && mb_strlen($text, 'UTF-8') >= 18) {
            $brief['content'] = $this->mergeContent((string) ($brief['content'] ?? ''), $text);
        }

        return $brief;
    }

    public function missing(array $brief): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            fn (string $field) => $field === 'content'
                ? ! $this->hasUsableContent($brief) && trim((string) ($brief['content'] ?? '')) === ''
                : ! isset($brief[$field]) || trim((string) $brief[$field]) === ''
        ));
    }

    public function questions(array $missing, int $max = 2): array
    {
        $map = [
            'brand_name' => 'Kartta kullanacağımız firma veya marka adı nedir?',
            'style' => 'Nasıl bir görünüm istersiniz; sade, modern, kurumsal veya daha premium bir tarz olabilir?',
            'content' => 'Kartta hangi iletişim bilgileri yer alsın? Telefon, adres, sosyal medya, e-posta veya slogan paylaşabilirsiniz.',
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

    private function isPending(array $pendingFields, string $field): bool
    {
        return in_array($field, $pendingFields, true)
            || in_array('brief:'.$field, $pendingFields, true);
    }

    private function detectStyle(string $lower): ?string
    {
        foreach (['modern', 'kurumsal', 'sade', 'minimal', 'lüks', 'luks', 'premium', 'renkli', 'eğlenceli', 'eglenceli', 'klasik'] as $style) {
            if (! str_contains($lower, $style)) {
                continue;
            }

            return match ($style) {
                'luks' => 'lüks',
                'eglenceli' => 'eğlenceli',
                default => $style,
            };
        }

        return null;
    }

    private function brandCandidate(string $text): ?string
    {
        $parts = preg_split('/[,;\n]+/u', trim($text)) ?: [];
        $candidate = trim((string) ($parts[0] ?? ''));

        if ($candidate === '' || mb_strlen($candidate, 'UTF-8') < 2 || mb_strlen($candidate, 'UTF-8') > 80) {
            return null;
        }

        $lower = mb_strtolower($candidate, 'UTF-8');
        if ($this->isGenericDesignAnswer($lower) || $this->detectStyle($lower) !== null || preg_match('/^\+?\d[\d\s.-]+$/u', $candidate)) {
            return null;
        }

        return $this->cleanBrand($candidate);
    }

    private function cleanBrand(string $brand): string
    {
        return trim(preg_replace('/\s+/u', ' ', $brand) ?? $brand);
    }

    private function hasUsableContent(array $brief): bool
    {
        foreach (['phone', 'email', 'instagram', 'address', 'slogan'] as $field) {
            if (trim((string) ($brief[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function contentSummary(array $brief, string $existing): string
    {
        $parts = [];
        foreach (['phone', 'email', 'instagram', 'address', 'slogan'] as $field) {
            $value = trim((string) ($brief[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $field.': '.$value;
            }
        }

        $summary = implode("\n", $parts);
        return $this->mergeContent($existing, $summary);
    }

    private function looksLikeActualContent(string $text, string $lower): bool
    {
        if ($this->isGenericDesignAnswer($lower)) {
            return false;
        }

        if (preg_match('/\b(?:telefon|tel|gsm|adres|instagram|insta|ig|e-?posta|email|mail|web|site|www|slogan|motto)\b/iu', $text)) {
            return true;
        }

        if (preg_match('/\b(?:\+?90\s*)?(?:0?5\d{2})[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}\b/u', $text)
            || preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text)
            || preg_match('/@([A-Za-z0-9._]{2,40})/u', $text)
            || preg_match('/https?:\/\//iu', $text)) {
            return true;
        }

        // Order-intake sentences must never be promoted to card copy simply
        // because they are long. This is what caused "500 adet ... tasarım yok"
        // to complete the brief before the customer supplied contact details.
        if (preg_match('/\b(?:adet|tane|kartvizit|broşür|brosur|etiket|katalog|tasarım yok|tasarim yok|tasarımım yok|tasarimim yok|çift yön|cift yon|tek yön|tek yon|kağıt|kagit|gramaj|ölçü|olcu)\b/iu', $text)) {
            return false;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/u', $text) ?: [])));
        if (count($parts) <= 2 && $this->detectStyle($lower) !== null) {
            return false;
        }

        return mb_strlen($text, 'UTF-8') >= 24;
    }

    private function isGenericDesignAnswer(string $text): bool
    {
        return preg_match('/^(?:siz yapın|siz yapin|siz tasarlayın|siz tasarlayin|siz hazırlayın|siz hazirlayin|hazırla|hazirla|devam|örnek|ornek|referans|tasarım yok|tasarim yok|tasarımım yok|tasarimim yok)$/u', trim($text)) === 1;
    }

    private function mergeContent(string $existing, string $new): string
    {
        $existing = trim($existing);
        $new = trim($new);

        if ($new === '') {
            return $existing;
        }

        if ($existing === '') {
            return $new;
        }

        if (str_contains(mb_strtolower($existing, 'UTF-8'), mb_strtolower($new, 'UTF-8'))) {
            return $existing;
        }

        return $existing."\n".$new;
    }
}
