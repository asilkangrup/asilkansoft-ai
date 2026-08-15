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

    public string $statusFilter =
        'active';

    public string $severityFilter =
        'all';

    public string $typeFilter =
        'all';

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
            ->latest('id')
            ->limit(200)
            ->get();
    }

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

    public function resetFilters(): void
    {
        $this->statusFilter =
            'active';

        $this->severityFilter =
            'all';

        $this->typeFilter =
            'all';
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