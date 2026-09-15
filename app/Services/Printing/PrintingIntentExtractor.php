<?php

namespace App\Services\Printing;

final class PrintingIntentExtractor
{
    public function __construct(private readonly PrintingProductCatalog $catalog)
    {
    }

    public function extract(string $message, array $current = []): array
    {
        $text = mb_strtolower(trim($message), 'UTF-8');
        $product = $this->catalog->detect($text) ?? ($current['product'] ?? null);
        $slots = is_array($current['slots'] ?? null) ? $current['slots'] : [];
        $pendingFields = array_values(array_filter($current['pending_fields'] ?? [], 'is_string'));

        if ($product && $slots === []) {
            $slots = $this->catalog->defaults($product);
        } elseif ($product) {
            $slots = array_replace($this->catalog->defaults($product), $slots);
        }

        if (preg_match('/(?<!\d)(\d{1,3}(?:[\.\s]\d{3})+|\d+)\s*(?:adet|tane)\b/u', $text, $m)) {
            $slots['quantity'] = (int) preg_replace('/\D/', '', $m[1]);
        }

        if (preg_match('/\b(a[0-7])\b/iu', $text, $m)) {
            $slots['size'] = mb_strtoupper($m[1], 'UTF-8');
        } elseif (preg_match('/\b(\d{1,3}(?:[\.,]\d+)?)\s*[x×]\s*(\d{1,3}(?:[\.,]\d+)?)\s*(mm|cm)?\b/iu', $text, $m)) {
            $slots['size'] = str_replace(',', '.', $m[1]).'x'.str_replace(',', '.', $m[2]).' '.($m[3] ?? 'cm');
        }

        if (preg_match('/\b(\d{2,4})\s*(?:gr|gram|gsm)\b/u', $text, $m)) {
            $slots['paper'] = $m[1].' gr';
        }

        if (
            str_contains($text, 'çift yön') || str_contains($text, 'cift yon')
            || str_contains($text, 'çift taraf') || str_contains($text, 'cift taraf')
            || str_contains($text, 'ön arka') || str_contains($text, 'on arka')
            || preg_match('/\b4\s*\+\s*4\b/u', $text)
        ) {
            $slots['sides'] = 'double';
        } elseif (
            str_contains($text, 'tek yön') || str_contains($text, 'tek yon')
            || str_contains($text, 'tek taraf')
            || preg_match('/\b4\s*\+\s*0\b/u', $text)
        ) {
            $slots['sides'] = 'single';
        }

        if (str_contains($text, 'mat selefon')) {
            $slots['lamination'] = 'mat';
        } elseif (str_contains($text, 'parlak selefon')) {
            $slots['lamination'] = 'parlak';
        } elseif (str_contains($text, 'selefonsuz') || str_contains($text, 'selefon olmasın') || str_contains($text, 'selefon olmasin')) {
            $slots['lamination'] = 'yok';
        }

        if (preg_match('/\b(\d{1,4})\s*(?:sayfa|sf)\b/u', $text, $m)) {
            $slots['page_count'] = (int) $m[1];
        }

        if (preg_match('/\b(\d{1,4})\s*(?:yaprak)\b/u', $text, $m)) {
            $slots['sheet_count'] = (int) $m[1];
        }

        $designReady = str_contains($text, 'tasarım hazır')
            || str_contains($text, 'tasarim hazir')
            || str_contains($text, 'dosyam hazır')
            || str_contains($text, 'dosyam hazir')
            || (in_array('design_status', $pendingFields, true) && preg_match('/^(?:hazır|hazir|evet|var)$/u', $text));

        $needsDesign = str_contains($text, 'tasarım yok')
            || str_contains($text, 'tasarim yok')
            || str_contains($text, 'tasarımım yok')
            || str_contains($text, 'tasarimim yok')
            || str_contains($text, 'tasarım lazım')
            || str_contains($text, 'tasarim lazim')
            || str_contains($text, 'siz tasarlayın')
            || str_contains($text, 'siz tasarlayin')
            || str_contains($text, 'tasarımı siz yapın')
            || str_contains($text, 'tasarimi siz yapin')
            || str_contains($text, 'siz hazırlayın')
            || str_contains($text, 'siz hazirlayin')
            || str_contains($text, 'siz yapın')
            || str_contains($text, 'siz yapin');

        if ($designReady) {
            $slots['design_status'] = 'ready';
        } elseif ($needsDesign) {
            $slots['design_status'] = 'needs_design';
        }

        foreach ([
            'kuşe' => 'kuşe', 'kuse' => 'kuşe', 'bristol' => 'bristol', 'kraft' => 'kraft',
            'opak' => 'opak', '1. hamur' => '1. hamur', '1.hamur' => '1. hamur',
        ] as $needle => $paper) {
            if (str_contains($text, $needle)) {
                $slots['paper_type'] = $paper;
                if (! isset($slots['paper'])) {
                    $slots['paper'] = $paper;
                }
            }
        }

        if (str_contains($text, 'dış mekan') || str_contains($text, 'dis mekan')) {
            $slots['indoor_outdoor'] = 'outdoor';
        } elseif (str_contains($text, 'iç mekan') || str_contains($text, 'ic mekan')) {
            $slots['indoor_outdoor'] = 'indoor';
        }

        if (str_contains($text, 'oval kesim')) {
            $slots['cut_shape'] = 'oval';
        } elseif (str_contains($text, 'yuvarlak')) {
            $slots['cut_shape'] = 'yuvarlak';
        } elseif (str_contains($text, 'özel kesim') || str_contains($text, 'ozel kesim')) {
            $slots['cut_shape'] = 'özel kesim';
        }

        $standardChoice = $this->standardChoice($text);

        // Explicit delegation must work even on the very first message, before
        // there is any pending-field history. Example:
        // "kağıt ve ölçüyü siz belirleyin".
        $delegatesPaper = $standardChoice && preg_match('/\b(kağıt|kagit|gramaj)\b/u', $text) === 1;
        $delegatesSize = $standardChoice && preg_match('/\b(ölçü|olcu|ebat|boyut)\b/u', $text) === 1;
        $delegatesMaterial = $standardChoice && preg_match('/\b(malzeme|materyal)\b/u', $text) === 1;

        if ($delegatesPaper && ! isset($slots['paper'])) {
            $slots['paper'] = 'no_preference';
        }
        if ($delegatesMaterial && ! isset($slots['material'])) {
            $slots['material'] = 'no_preference';
        }
        if ($delegatesSize && $product) {
            $defaultSize = $this->catalog->defaults($product)['size'] ?? null;
            if (is_string($defaultSize) && $defaultSize !== '') {
                $slots['size'] = $defaultSize;
            }
        }

        // Contextual short replies ("yok", "standart olsun", "siz seçin")
        // still use the previously asked field when the customer did not name it.
        if ($standardChoice) {
            if (in_array('paper', $pendingFields, true) && ! isset($slots['paper'])) {
                $slots['paper'] = 'no_preference';
            }
            if (in_array('material', $pendingFields, true) && ! isset($slots['material'])) {
                $slots['material'] = 'no_preference';
            }
            if (in_array('size', $pendingFields, true) && $product) {
                $defaultSize = $this->catalog->defaults($product)['size'] ?? null;
                if (is_string($defaultSize) && $defaultSize !== '') {
                    $slots['size'] = $defaultSize;
                }
            }
        }

        return [
            'product' => $product,
            'slots' => array_filter($slots, static fn ($value) => $value !== null && $value !== ''),
            'pending_fields' => $pendingFields,
            'design_brief' => is_array($current['design_brief'] ?? null) ? $current['design_brief'] : [],
        ];
    }

    public function missing(array $state): array
    {
        $product = $state['product'] ?? null;
        if (! $product) {
            return ['product'];
        }

        $slots = $state['slots'] ?? [];

        return array_values(array_filter(
            $this->catalog->requiredFields($product),
            static fn (string $field) => ! array_key_exists($field, $slots)
        ));
    }

    private function standardChoice(string $text): bool
    {
        return preg_match(
            '/^(?:yok|hayır|hayir|farketmez|fark etmez|tercihim yok|siz seçin|siz secin|siz belirleyin|onu siz belirleyin|onu da siz belirleyin|onuda siz belirleyin|standart olsun|standart|uygun olan olsun|uygun olanı siz seçin|uygun olani siz secin)$/u',
            trim($text)
        ) === 1
            || str_contains($text, 'tercihim yok')
            || str_contains($text, 'fark etmez')
            || str_contains($text, 'siz belirleyin')
            || str_contains($text, 'siz seçin')
            || str_contains($text, 'siz secin')
            || str_contains($text, 'standart olsun')
            || str_contains($text, 'uygun olan');
    }
}
