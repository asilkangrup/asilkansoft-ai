<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class WeeklyRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Son 7 Günlük Ciro';

    protected ?string $description =
        'Bugün dahil son 7 günde teslim edilen siparişlerden elde edilen ciro.';

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        /*
        |--------------------------------------------------------------------------
        | SON 7 GÜN
        |--------------------------------------------------------------------------
        */

        for ($i = 6; $i >= 0; $i--) {

            $date = Carbon::today()->subDays($i);

            $labels[] = $date->format('d.m');

            /*
            |--------------------------------------------------------------------------
            | O GÜNÜN CİROSU
            |--------------------------------------------------------------------------
            |
            | Sadece teslim edilmiş siparişleri ciroya dahil ediyoruz.
            |
            */

            $revenue = Order::query()
                ->where('status', 'delivered')
                ->whereDate('updated_at', $date)
                ->sum('total_amount');

            $data[] = (float) $revenue;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ciro',

                    'data' => $data,

                    'borderColor' => '#22C55E',

                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',

                    'borderWidth' => 3,

                    'pointRadius' => 4,

                    'pointHoverRadius' => 6,

                    'tension' => 0.35,

                    'fill' => true,
                ],
            ],

            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => false,
                ],

                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            let value = context.raw || 0;
                            return '₺' + Number(value).toLocaleString('tr-TR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }",
                    ],
                ],
            ],

            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}