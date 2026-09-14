<?php

namespace App\Services\Printing;

final class PrintingProductCatalog
{
    public function products(): array
    {
        return [
            'business_card' => [
                'label' => 'kartvizit',
                'aliases' => ['kartvizit', 'kart vizit'],
                'required' => ['quantity', 'size', 'sides', 'paper'],
                'optional' => ['lamination', 'special_finish', 'design_status'],
                'defaults' => ['size' => '85x50 mm'],
            ],
            'brochure' => [
                'label' => 'broşür',
                'aliases' => ['broşür', 'brosur', 'el ilanı', 'el ilani', 'flyer'],
                'required' => ['quantity', 'size', 'sides', 'paper'],
                'optional' => ['fold', 'lamination', 'design_status'],
            ],
            'catalog' => [
                'label' => 'katalog',
                'aliases' => ['katalog', 'katalog baskı', 'katalog baski'],
                'required' => ['quantity', 'size', 'page_count', 'inner_paper', 'cover_paper', 'binding'],
                'optional' => ['cover_lamination', 'design_status'],
            ],
            'sticker' => [
                'label' => 'etiket / sticker',
                'aliases' => ['etiket', 'sticker', 'çıkartma', 'cikartma'],
                'required' => ['quantity', 'size', 'material', 'cut_shape'],
                'optional' => ['indoor_outdoor', 'lamination', 'design_status'],
            ],
            'poster' => [
                'label' => 'afiş / poster',
                'aliases' => ['afiş', 'afis', 'poster'],
                'required' => ['quantity', 'size', 'paper'],
                'optional' => ['lamination', 'indoor_outdoor', 'design_status'],
            ],
            'menu' => [
                'label' => 'menü',
                'aliases' => ['menü', 'menu', 'restoran menüsü', 'restoran menusu'],
                'required' => ['quantity', 'size', 'page_count', 'material'],
                'optional' => ['lamination', 'binding', 'design_status'],
            ],
            'invitation' => [
                'label' => 'davetiye',
                'aliases' => ['davetiye', 'düğün davetiyesi', 'dugun davetiyesi'],
                'required' => ['quantity', 'size', 'paper'],
                'optional' => ['envelope', 'special_finish', 'design_status'],
            ],
            'letterhead' => [
                'label' => 'antetli kağıt',
                'aliases' => ['antetli', 'antetli kağıt', 'antetli kagit'],
                'required' => ['quantity', 'size', 'paper'],
                'optional' => ['design_status'],
                'defaults' => ['size' => 'A4'],
            ],
            'envelope' => [
                'label' => 'zarf',
                'aliases' => ['zarf', 'diplomat zarf', 'torba zarf'],
                'required' => ['quantity', 'size', 'paper'],
                'optional' => ['window', 'design_status'],
            ],
            'presentation_folder' => [
                'label' => 'sunum dosyası',
                'aliases' => ['sunum dosyası', 'sunum dosyasi', 'cepli dosya', 'dosya'],
                'required' => ['quantity', 'size', 'paper'],
                'optional' => ['lamination', 'special_finish', 'design_status'],
            ],
            'magnet' => [
                'label' => 'magnet',
                'aliases' => ['magnet', 'buzdolabı magneti', 'buzdolabi magneti'],
                'required' => ['quantity', 'size', 'material'],
                'optional' => ['cut_shape', 'design_status'],
            ],
            'notepad' => [
                'label' => 'bloknot',
                'aliases' => ['bloknot', 'blok not', 'notluk'],
                'required' => ['quantity', 'size', 'sheet_count', 'paper'],
                'optional' => ['cover', 'design_status'],
            ],
        ];
    }

    public function detect(string $message): ?string
    {
        $haystack = mb_strtolower($message, 'UTF-8');

        foreach ($this->products() as $key => $product) {
            foreach ($product['aliases'] as $alias) {
                if (str_contains($haystack, mb_strtolower($alias, 'UTF-8'))) {
                    return $key;
                }
            }
        }

        return null;
    }

    public function get(?string $product): ?array
    {
        if ($product === null) {
            return null;
        }

        return $this->products()[$product] ?? null;
    }

    public function requiredFields(string $product): array
    {
        return $this->get($product)['required'] ?? [];
    }

    public function defaults(string $product): array
    {
        return $this->get($product)['defaults'] ?? [];
    }
}
