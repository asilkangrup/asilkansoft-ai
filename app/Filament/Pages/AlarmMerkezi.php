<?php

namespace App\Filament\Pages;

use App\Models\CrmAlarm;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AlarmMerkezi extends Page
{
    protected string $view =
        'filament.pages.alarm-merkezi';

    protected static ?string $title =
        'Alarm Merkezi';

    protected static ?string $navigationLabel =
        'Alarm Merkezi';

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort =
        33;

    /*
    |--------------------------------------------------------------------------
    | FİLTRELER
    |--------------------------------------------------------------------------
    */

    public string $statusFilter =
        'active';

    public string $severityFilter =
        'all';

    public string $typeFilter =
        'all';

    /*
    |--------------------------------------------------------------------------
    | NAVIGATION BADGE
    |--------------------------------------------------------------------------
    |
    | Sol menüde aktif alarm sayısını gösterir.
    |
    */

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->check()) {
            return null;
        }

        $count =
            CrmAlarm::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where(
                    'is_resolved',
                    false
                )
                ->count();

        return $count > 0
            ? (string) $count
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | NAVIGATION BADGE RENGİ
    |--------------------------------------------------------------------------
    |
    | En az bir kritik alarm varsa kırmızı.
    | Yalnızca warning varsa sarı.
    |
    */

    public static function getNavigationBadgeColor(): string|array|null
    {
        if (! auth()->check()) {
            return null;
        }

        $criticalCount =
            CrmAlarm::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where(
                    'is_resolved',
                    false
                )
                ->where(
                    'severity',
                    'critical'
                )
                ->count();

        return $criticalCount > 0
            ? 'danger'
            : 'warning';
    }

    /*
    |--------------------------------------------------------------------------
    | ANA ALARM SORGUSU
    |--------------------------------------------------------------------------
    */

    protected function baseQuery(): Builder
    {
        return CrmAlarm::query()
            ->with([
                'conversation.aiBot',
                'conversation.assignedUser',
            ])
            ->where(
                'user_id',
                auth()->id()
            )
            ->when(
                $this->statusFilter === 'active',
                fn (Builder $query) =>
                    $query->where(
                        'is_resolved',
                        false
                    )
            )
            ->when(
                $this->statusFilter === 'resolved',
                fn (Builder $query) =>
                    $query->where(
                        'is_resolved',
                        true
                    )
            )
            ->when(
                $this->severityFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'severity',
                        $this->severityFilter
                    )
            )
            ->when(
                $this->typeFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'type',
                        $this->typeFilter
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ALARMLAR
    |--------------------------------------------------------------------------
    */

    public function getAlarmsProperty(): Collection
    {
        return $this->baseQuery()
            ->orderByRaw(
                "
                CASE severity
                    WHEN 'critical' THEN 1
                    WHEN 'warning' THEN 2
                    ELSE 3
                END
                "
            )
            ->latest(
                'id'
            )
            ->limit(
                200
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF ALARM SAYISI
    |--------------------------------------------------------------------------
    */

    public function getActiveCountProperty(): int
    {
        return CrmAlarm::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->where(
                'is_resolved',
                false
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | KRİTİK ALARM SAYISI
    |--------------------------------------------------------------------------
    */

    public function getCriticalCountProperty(): int
    {
        return CrmAlarm::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->where(
                'is_resolved',
                false
            )
            ->where(
                'severity',
                'critical'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | BUGÜN ÇÖZÜLEN ALARM SAYISI
    |--------------------------------------------------------------------------
    */

    public function getResolvedTodayCountProperty(): int
    {
        return CrmAlarm::query()
            ->where(
                'user_id',
                auth()->id()
            )
            ->where(
                'is_resolved',
                true
            )
            ->whereDate(
                'resolved_at',
                today()
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | ALARMI ÇÖZ
    |--------------------------------------------------------------------------
    */

    public function resolveAlarm(
        int $alarmId
    ): void {
        $alarm =
            CrmAlarm::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->find(
                    $alarmId
                );

        if (! $alarm) {
            return;
        }

        $alarm->resolve();
    }

    /*
    |--------------------------------------------------------------------------
    | ALARMI YENİDEN AÇ
    |--------------------------------------------------------------------------
    */

    public function reopenAlarm(
        int $alarmId
    ): void {
        $alarm =
            CrmAlarm::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->find(
                    $alarmId
                );

        if (! $alarm) {
            return;
        }

        $alarm->reopen();
    }

    /*
    |--------------------------------------------------------------------------
    | FİLTRELERİ TEMİZLE
    |--------------------------------------------------------------------------
    */

    public function resetFilters(): void
    {
        $this->statusFilter =
            'active';

        $this->severityFilter =
            'all';

        $this->typeFilter =
            'all';
    }

    /*
    |--------------------------------------------------------------------------
    | FILAMENT BAŞLIK
    |--------------------------------------------------------------------------
    */

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}