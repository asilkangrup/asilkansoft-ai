<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class WeeklyRevenueChart extends ChartWidget
{
    protected ?string $heading = 'Son 7 Günlük Ciro';

    protected ?string $description =
        'Bugün dahil son 7 günde teslim edilen siparişlerden elde edilen ciro.';

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AİT SİPARİŞ SORGUSU
    |--------------------------------------------------------------------------
    |
    | Admin:
    | Tüm siparişlerin cirosunu görür.
    |
    | Normal müşteri:
    | Yalnızca kendi yapay zekâ botlarına ait siparişlerin cirosunu görür.
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
            | O GÜNÜN CİROSU
            |--------------------------------------------------------------------------
            |
            | Sadece teslim edilmiş siparişler ciroya dahil edilir.
            |
            */

            $revenue = $this->ordersQuery()
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