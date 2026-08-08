<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class WeeklyOrdersChart extends ChartWidget
{
    protected ?string $heading = 'Son 7 Günlük Siparişler';

    protected ?string $description =
        'Bugün dahil son 7 günde alınan sipariş sayıları.';

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AİT SİPARİŞ SORGUSU
    |--------------------------------------------------------------------------
    |
    | Admin:
    | Tüm siparişleri görür.
    |
    | Normal müşteri:
    | Yalnızca kendi yapay zekâ botlarına ait siparişleri görür.
    |
    */

    private function ordersQuery(): Builder
    {
        $query = Order::query();

        $user = Filament::auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        return $query->whereHas('aiBot', function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);
        });
    }

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
            | O GÜN GELEN SİPARİŞ SAYISI
            |--------------------------------------------------------------------------
            |
            | Taslak siparişler tamamlanmadığı için dahil edilmez.
            |
            */

            $orderCount = $this->ordersQuery()
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