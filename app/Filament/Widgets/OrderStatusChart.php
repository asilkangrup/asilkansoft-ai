<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class OrderStatusChart extends ChartWidget
{
    protected ?string $heading = 'Sipariş Durumları';

    protected ?string $description =
        'Siparişlerin mevcut durumlara göre dağılımı.';

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA AİT SİPARİŞ SORGUSU
    |--------------------------------------------------------------------------
    |
    | Admin tüm siparişleri görür.
    | Normal müşteri yalnızca kendi yapay zekâ botlarına ait siparişleri görür.
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
        $pending = $this->ordersQuery()
            ->where('status', 'pending')
            ->count();

        $confirmed = $this->ordersQuery()
            ->where('status', 'confirmed')
            ->count();

        $preparing = $this->ordersQuery()
            ->where('status', 'preparing')
            ->count();

        $shipped = $this->ordersQuery()
            ->where('status', 'shipped')
            ->count();

        $delivered = $this->ordersQuery()
            ->where('status', 'delivered')
            ->count();

        $cancelled = $this->ordersQuery()
            ->where('status', 'cancelled')
            ->count();

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