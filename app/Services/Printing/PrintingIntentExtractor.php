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
        $slots = $current['slots'] ?? [];
        $pendingFields = array_values(array_filter($current['pending_fields'] ?? [], 'is_string'));

        if ($product && empty($slots)) {
            $slots = $this->catalog->defaults($product);
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

        if (str_contains($text, 'çift yön') || str_contains($text, 'cift yon') || str_contains($text, 'ön arka') || str_contains($text, 'on arka')) {
            $slots['sides'] = 'double';
        } elseif (str_contains($text, 'tek yön') || str_contains($text, 'tek yon')) {
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

        if (str_contains($text, 'tasarım hazır') || str_contains($text, 'tasarim hazir') || str_contains($text, 'dosyam hazır') || str_contains($text, 'dosyam hazir')) {
            $slots['design_status'] = 'ready';
        } elseif (str_contains($text, 'tasarım yok') || str_contains($text, 'tasarim yok') || str_contains($text, 'tasarım lazım') || str_contains($text, 'tasarim lazim')) {
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

        // "Yok", "farketmez", "tercihim yok" gibi kısa cevapları son sorulan
        // alana göre yorumla. Böylece müşteri aynı soruya tekrar tekrar maruz kalmaz.
        $noPreference = preg_match('/^(?:yok|hayır|hayir|farketmez|fark etmez|tercihim yok|siz seçin|siz secin|standart olsun)$/u', $text) === 1
            || str_contains($text, 'tercihim yok')
            || str_contains($text, 'fark etmez');

        if ($noPreference) {
            if (in_array('paper', $pendingFields, true) && ! isset($slots['paper'])) {
                $slots['paper'] = 'no_preference';
            }
            if (in_array('material', $pendingFields, true) && ! isset($slots['material'])) {
                $slots['material'] = 'no_preference';
            }
            if (in_array('design_status', $pendingFields, true) && ! isset($slots['design_status'])) {
                $slots['design_status'] = 'needs_design';
            }
        }

        return [
            'product' => $product,
            'slots' => array_filter($slots, static fn ($value) => $value !== null && $value !== ''),
            'pending_fields' => $pendingFields,
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
}
