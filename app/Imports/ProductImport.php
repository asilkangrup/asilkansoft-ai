<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{
    protected int $aiBotId;

    public function __construct(int $aiBotId)
    {
        $this->aiBotId = $aiBotId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['urun_adi'] ?? ''));

            if ($name === '') {
                continue;
            }

            Product::updateOrCreate(
                [
                    'ai_bot_id' => $this->aiBotId,
                    'name' => $name,
                ],
                [
                    'category' => $row['kategori'] ?? null,

                    'price' => isset($row['fiyat']) && $row['fiyat'] !== ''
                        ? (float) str_replace(',', '.', (string) $row['fiyat'])
                        : null,

                    'description' => $row['aciklama'] ?? null,

                    'stock_status' => match (
                        strtolower(trim((string) ($row['stok_durumu'] ?? 'stokta')))
                    ) {
                        'stokta yok', 'yok', 'out_of_stock' => 'out_of_stock',
                        'ön sipariş', 'on siparis', 'pre_order' => 'pre_order',
                        default => 'in_stock',
                    },

                    'is_active' => match (
                        strtolower(trim((string) ($row['aktif'] ?? 'evet')))
                    ) {
                        'hayır', 'hayir', '0', 'false', 'pasif' => false,
                        default => true,
                    },
                ]
            );
        }
    }
}