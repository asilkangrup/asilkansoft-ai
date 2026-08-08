<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class TopSellingProductsChart extends ChartWidget
{
    protected ?string $heading = 'En Çok Satan Ürünler';

    protected ?string $description =
        'Teslim edilen siparişlere göre en çok satılan ürünler.';

    protected function getData(): array
    {
        /*
        |--------------------------------------------------------------------------
        | EN ÇOK SATAN ÜRÜNLER
        |--------------------------------------------------------------------------
        |
        | Sadece teslim edilmiş siparişleri dikkate alıyoruz.
        | En çok sipariş edilen ilk 10 ürünü getiriyoruz.
        |
        */

        $products = Order::query()
            ->selectRaw('products, COUNT(*) as total')
            ->where('status', 'delivered')
            ->whereNotNull('products')
            ->where('products', '!=', '')
            ->groupBy('products')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | GRAFİK VERİLERİ
        |--------------------------------------------------------------------------
        */

        $labels = $products
            ->pluck('products')
            ->map(
                fn ($product) => mb_strlen($product) > 30
                    ? mb_substr($product, 0, 30).'...'
                    : $product
            )
            ->values()
            ->toArray();

        $data = $products
            ->pluck('total')
            ->map(fn ($total) => (int) $total)
            ->values()
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Sipariş Sayısı',

                    'data' => $data,

                    'backgroundColor' => '#8B5CF6',

                    'borderColor' => '#7C3AED',

                    'borderWidth' => 1,

                    'borderRadius' => 6,
                ],
            ],

            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',

            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],

            'scales' => [
                'x' => [
                    'beginAtZero' => true,

                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}