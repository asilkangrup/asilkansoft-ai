<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusChart extends ChartWidget
{
    protected ?string $heading = 'Sipariş Durumları';

    protected ?string $description =
        'Siparişlerin mevcut durumlara göre dağılımı.';

    protected function getData(): array
    {
        $pending = Order::where('status', 'pending')->count();
        $confirmed = Order::where('status', 'confirmed')->count();
        $preparing = Order::where('status', 'preparing')->count();
        $shipped = Order::where('status', 'shipped')->count();
        $delivered = Order::where('status', 'delivered')->count();
        $cancelled = Order::where('status', 'cancelled')->count();

        return [
            'datasets' => [
                [
                    'label' => 'Sipariş Sayısı',

                    'data' => [
                        $pending,
                        $confirmed,
                        $preparing,
                        $shipped,
                        $delivered,
                        $cancelled,
                    ],

                    'backgroundColor' => [
                        '#F59E0B',
                        '#3B82F6',
                        '#8B5CF6',
                        '#06B6D4',
                        '#22C55E',
                        '#EF4444',
                    ],

                    'borderColor' => [
                        '#FFFFFF',
                        '#FFFFFF',
                        '#FFFFFF',
                        '#FFFFFF',
                        '#FFFFFF',
                        '#FFFFFF',
                    ],

                    'borderWidth' => 2,

                    'hoverOffset' => 8,
                ],
            ],

            'labels' => [
                'Yeni Sipariş',
                'Onaylandı',
                'Hazırlanıyor',
                'Kargoda',
                'Teslim Edildi',
                'İptal Edildi',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,

            'cutout' => '60%',

            'plugins' => [
                'legend' => [
                    'position' => 'bottom',

                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
            ],
        ];
    }
}