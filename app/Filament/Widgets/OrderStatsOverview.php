<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class OrderStatsOverview extends StatsOverviewWidget
{
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

    protected function getStats(): array
    {
        $newOrders = $this->ordersQuery()
            ->where('status', 'pending')
            ->count();

        $confirmedOrders = $this->ordersQuery()
            ->where('status', 'confirmed')
            ->count();

        $preparingOrders = $this->ordersQuery()
            ->where('status', 'preparing')
            ->count();

        $shippedOrders = $this->ordersQuery()
            ->where('status', 'shipped')
            ->count();

        $deliveredOrders = $this->ordersQuery()
            ->where('status', 'delivered')
            ->count();

        $cancelledOrders = $this->ordersQuery()
            ->where('status', 'cancelled')
            ->count();

        $totalOrders = $this->ordersQuery()
            ->where('status', '!=', 'draft')
            ->count();

        $todayOrders = $this->ordersQuery()
            ->whereDate('created_at', today())
            ->where('status', '!=', 'draft')
            ->count();

        $totalRevenue = $this->ordersQuery()
            ->where('status', 'delivered')
            ->sum('total_amount');

        $todayRevenue = $this->ordersQuery()
            ->where('status', 'delivered')
            ->whereDate('updated_at', today())
            ->sum('total_amount');

        $monthlyRevenue = $this->ordersQuery()
            ->where('status', 'delivered')
            ->whereYear('updated_at', now()->year)
            ->whereMonth('updated_at', now()->month)
            ->sum('total_amount');

        $baseUrl = OrderResource::getUrl('index');

        return [

            Stat::make('Yeni Siparişler', $newOrders)
                ->description('İşlem bekleyen siparişler')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning')
                ->url($baseUrl.'?status=pending'),

            Stat::make('Onaylananlar', $confirmedOrders)
                ->description('Onaylandı, hazırlama bekliyor')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info')
                ->url($baseUrl.'?status=confirmed'),

            Stat::make('Hazırlananlar', $preparingOrders)
                ->description('Hazırlanmakta olan siparişler')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary')
                ->url($baseUrl.'?status=preparing'),

            Stat::make('Kargodakiler', $shippedOrders)
                ->description('Kargoya verilen siparişler')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->url($baseUrl.'?status=shipped'),

            Stat::make('Teslim Edilenler', $deliveredOrders)
                ->description('Başarıyla teslim edilenler')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->url($baseUrl.'?status=delivered'),

            Stat::make('İptal Edilenler', $cancelledOrders)
                ->description('İptal edilen siparişler')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->url($baseUrl.'?status=cancelled'),

            Stat::make('Toplam Sipariş', $totalOrders)
                ->description('Taslaklar hariç toplam sipariş')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary')
                ->url($baseUrl),

            Stat::make('Bugünkü Siparişler', $todayOrders)
                ->description('Bugün alınan siparişler')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->url($baseUrl),

            Stat::make(
                'Bugünkü Ciro',
                '₺'.number_format(
                    (float) $todayRevenue,
                    2,
                    ',',
                    '.'
                )
            )
                ->description('Bugün teslim edilen siparişlerden')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url($baseUrl.'?status=delivered'),

            Stat::make(
                'Bu Ayki Ciro',
                '₺'.number_format(
                    (float) $monthlyRevenue,
                    2,
                    ',',
                    '.'
                )
            )
                ->description('Bu ay teslim edilen siparişlerden')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success')
                ->url($baseUrl.'?status=delivered'),

            Stat::make(
                'Toplam Ciro',
                '₺'.number_format(
                    (float) $totalRevenue,
                    2,
                    ',',
                    '.'
                )
            )
                ->description('Teslim edilen tüm siparişlerden')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->url($baseUrl.'?status=delivered'),
        ];
    }
}