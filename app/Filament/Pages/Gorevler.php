<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Gorevler extends Page
{
    protected string $view = 'filament.pages.gorevler';

    protected static ?string $title = 'Görevler';

    protected static ?string $navigationLabel = 'Görevler';

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 33;

    public string $search = '';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

    /*
    |--------------------------------------------------------------------------
    | ANA QUERY
    |--------------------------------------------------------------------------
    */

    protected function baseQuery(): Builder
    {
        return ConversationControl::query()
            ->with([
                'aiBot',
                'assignedUser',
            ])
            ->where(
                'user_id',
                auth()->id()
            )
            ->whereNotNull(
                'next_follow_up_at'
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = trim(
                        $this->search
                    );

                    $query->where(
                        function (Builder $query) use ($search): void {
                            $query
                                ->where(
                                    'customer_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'whatsapp_number',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'company_name',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->when(
                $this->temperatureFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'lead_temperature',
                        $this->temperatureFilter
                    )
            )
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'channel',
                        $this->channelFilter
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | GECİKENLER
    |--------------------------------------------------------------------------
    */

    public function getOverdueTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '<',
                now()->startOfDay()
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | BUGÜN
    |--------------------------------------------------------------------------
    */

    public function getTodayTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->whereBetween(
                'next_follow_up_at',
                [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ]
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | YAKLAŞAN
    |--------------------------------------------------------------------------
    */

    public function getUpcomingTasksProperty(): Collection
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '>',
                now()->endOfDay()
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->limit(100)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    public function getOverdueCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '<',
                now()->startOfDay()
            )
            ->count();
    }

    public function getTodayCountProperty(): int
    {
        return $this->baseQuery()
            ->whereBetween(
                'next_follow_up_at',
                [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ]
            )
            ->count();
    }

    public function getUpcomingCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'next_follow_up_at',
                '>',
                now()->endOfDay()
            )
            ->count();
    }

    public function getHotFollowUpsProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'lead_temperature',
                'hot'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | TAKİBİ TAMAMLA
    |--------------------------------------------------------------------------
    */

    public function completeTask(
        int $customerId
    ): void {
        $customer = ConversationControl::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $customer->update([
            'next_follow_up_at' => null,
        ]);

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | YARINA ERTELE
    |--------------------------------------------------------------------------
    */

    public function postponeToTomorrow(
        int $customerId
    ): void {
        $customer = ConversationControl::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $current =
            $customer->next_follow_up_at
            ?: now();

        $newTime = Carbon::parse(
            $current
        )
            ->addDay();

        $customer->update([
            'next_follow_up_at' =>
                $newTime,
        ]);

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 1 SAAT ERTELE
    |--------------------------------------------------------------------------
    */

    public function postponeOneHour(
        int $customerId
    ): void {
        $customer = ConversationControl::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $current =
            $customer->next_follow_up_at
            ?: now();

        $newTime = Carbon::parse(
            $current
        )
            ->addHour();

        $customer->update([
            'next_follow_up_at' =>
                $newTime,
        ]);

        $this->dispatch(
            'task-updated'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FİLTRELER
    |--------------------------------------------------------------------------
    */

    public function resetFilters(): void
    {
        $this->search = '';

        $this->temperatureFilter =
            'all';

        $this->channelFilter =
            'all';
    }

    /*
    |--------------------------------------------------------------------------
    | LABEL
    |--------------------------------------------------------------------------
    */

    public function temperatureLabel(
        ?string $temperature
    ): string {
        return match ($temperature) {
            'hot' =>
                'Sıcak',

            'warm' =>
                'Ilık',

            default =>
                'Soğuk',
        };
    }

    public function channelLabel(
        ?string $channel
    ): string {
        return match ($channel) {
            'instagram' =>
                'Instagram',

            'facebook' =>
                'Facebook',

            'web' =>
                'Web',

            default =>
                'WhatsApp',
        };
    }

    public function customerUrl(
        int $customerId
    ): string {
        return url(
            '/admin/musteriler'
            .'?customer='
            .$customerId
        );
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}