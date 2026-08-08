<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class WeeklyOrdersChart extends ChartWidget
{
    protected ?string $heading = 'Son 7 Günlük Siparişler';

    protected ?string $description =
        'Bugün dahil son 7 günde alınan sipariş sayıları.';

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

            /*
            |--------------------------------------------------------------------------
            | GRAFİK ALTINDA GÖRÜNECEK TARİH
            |--------------------------------------------------------------------------
            */

            $labels[] = $date->format('d.m');

            /*
            |--------------------------------------------------------------------------
            | O GÜN GELEN SİPARİŞ SAYISI
            |--------------------------------------------------------------------------
            |
            | draft olan siparişler henüz tamamlanmadığı için sayılmıyor.
            |
            */

            $orderCount = Order::query()
                ->whereDate('created_at', $date)
                ->where('status', '!=', 'draft')
                ->count();

            $data[] = $orderCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sipariş Sayısı',

                    'data' => $data,

                    'backgroundColor' => '#3B82F6',

                    'borderColor' => '#2563EB',

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
            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],

            'scales' => [
                'y' => [
                    'beginAtZero' => true,

                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}