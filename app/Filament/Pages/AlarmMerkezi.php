<?php

namespace App\Filament\Pages;

use App\Models\CrmAlarm;
use App\Services\CrmAlarmAuditService;
use BackedEnum;
use Carbon\Carbon;
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

    public string $search = '';

    public array $customSnoozeUntil = [];

    public array $expandedHistories = [];

    /*
    |--------------------------------------------------------------------------
    | MENÜ ROZETİ
    |--------------------------------------------------------------------------
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
                ->where(
                    function (
                        Builder $query
                    ): void {
                        $query
                            ->whereNull(
                                'snoozed_until'
                            )
                            ->orWhere(
                                'snoozed_until',
                                '<=',
                                now()
                            );
                    }
                )
                ->count();

        return $count > 0
            ? (string) $count
            : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        if (! auth()->check()) {
            return null;
        }

        $critical =
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
                ->where(
                    function (
                        Builder $query
                    ): void {
                        $query
                            ->whereNull(
                                'snoozed_until'
                            )
                            ->orWhere(
                                'snoozed_until',
                                '<=',
                                now()
                            );
                    }
                )
                ->exists();

        return $critical
            ? 'danger'
            : 'warning';
    }

    protected function baseQuery(): Builder
    {
        return CrmAlarm::query()
            ->with([
                'conversation.aiBot',
                'conversation.assignedUser',
                'events.user',
            ])
            ->where(
                'user_id',
                auth()->id()
            )
            ->when(
                $this->statusFilter === 'active',
                fn (Builder $query) =>
                    $query
                        ->where(
                            'is_resolved',
                            false
                        )
                        ->where(
                            function (
                                Builder $query
                            ): void {
                                $query
                                    ->whereNull(
                                        'snoozed_until'
                                    )
                                    ->orWhere(
                                        'snoozed_until',
                                        '<=',
                                        now()
                                    );
                            }
                        )
            )
            ->when(
                $this->statusFilter === 'snoozed',
                fn (Builder $query) =>
                    $query
                        ->where(
                            'is_resolved',
                            false
                        )
                        ->whereNotNull(
                            'snoozed_until'
                        )
                        ->where(
                            'snoozed_until',
                            '>',
                            now()
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
            )
            ->when(
                trim($this->search) !== '',
                function (
                    Builder $query
                ): void {
                    $search =
                        trim(
                            $this->search
                        );

                    $query->where(
                        function (
                            Builder $query
                        ) use (
                            $search
                        ): void {
                            $query
                                ->where(
                                    'title',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'message',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhereHas(
                                    'conversation',
                                    function (
                                        Builder $query
                                    ) use (
                                        $search
                                    ): void {
                                        $query
                                            ->where(
                                                'customer_name',
                                                'like',
                                                '%'.$search.'%'
                                            )
                                            ->orWhere(
                                                'whatsapp_number',
                                                'like',
                                                '%'.$search.'%'
                                            );
                                    }
                                );
                        }
                    );
                }
            );
    }

    public function getAlarmsProperty(): Collection
    {
        return $this->baseQuery()
            ->orderByDesc(
                'priority_score'
            )
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
            ->where(
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereNull(
                            'snoozed_until'
                        )
                        ->orWhere(
                            'snoozed_until',
                            '<=',
                            now()
                        );
                }
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
            ->where(
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereNull(
                            'snoozed_until'
                        )
                        ->orWhere(
                            'snoozed_until',
                            '<=',
                            now()
                        );
                }
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

    /*
    |--------------------------------------------------------------------------
    | ALARM SLA
    |--------------------------------------------------------------------------
    |
    | Kritik: 2 saat
    | Uyarı: 8 saat
    | Bilgi: 24 saat
    |
    */

    public function alarmSlaHours(
        CrmAlarm $alarm
    ): int {
        return match (
            $alarm->severity
        ) {
            'critical' =>
                2,

            'warning' =>
                8,

            default =>
                24,
        };
    }

    public function alarmAgeMinutes(
        CrmAlarm $alarm
    ): int {
        $end =
            $alarm->resolved_at
            ?: now();

        return max(
            0,
            (int) $alarm
                ->created_at
                ->diffInMinutes(
                    $end
                )
        );
    }

    public function alarmAgeLabel(
        CrmAlarm $alarm
    ): string {
        $minutes =
            $this->alarmAgeMinutes(
                $alarm
            );

        if ($minutes < 60) {
            return $minutes
                .' dk';
        }

        $hours =
            (int) floor(
                $minutes / 60
            );

        if ($hours < 24) {
            return $hours
                .' sa';
        }

        $days =
            (int) floor(
                $hours / 24
            );

        $remainingHours =
            $hours % 24;

        return $days
            .' gün '
            .$remainingHours
            .' sa';
    }

    public function alarmSlaBreached(
        CrmAlarm $alarm
    ): bool {
        if (
            $alarm->is_resolved
            || $alarm->isSnoozed()
        ) {
            return false;
        }

        $slaMinutes =
            $this->alarmSlaHours(
                $alarm
            )
            * 60;

        return
            $this->alarmAgeMinutes(
                $alarm
            )
            >
            $slaMinutes;
    }

    public function getSlaBreachedCountProperty(): int
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
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereNull(
                            'snoozed_until'
                        )
                        ->orWhere(
                            'snoozed_until',
                            '<=',
                            now()
                        );
                }
            )
            ->get()
            ->filter(
                fn (CrmAlarm $alarm): bool =>
                    $this->alarmSlaBreached(
                        $alarm
                    )
            )
            ->count();
    }

    public function getAverageResolutionMinutesProperty(): int
    {
        $alarms =
            CrmAlarm::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where(
                    'is_resolved',
                    true
                )
                ->whereNotNull(
                    'resolved_at'
                )
                ->where(
                    'resolved_at',
                    '>=',
                    now()->subDays(30)
                )
                ->get([
                    'created_at',
                    'resolved_at',
                ]);

        if ($alarms->isEmpty()) {
            return 0;
        }

        return (int) round(
            $alarms->avg(
                fn (CrmAlarm $alarm): int =>
                    (int) $alarm
                        ->created_at
                        ->diffInMinutes(
                            $alarm->resolved_at
                        )
            )
        );
    }

    public function getAverageResolutionLabelProperty(): string
    {
        $minutes =
            $this->averageResolutionMinutes;

        if ($minutes <= 0) {
            return '-';
        }

        if ($minutes < 60) {
            return $minutes
                .' dk';
        }

        $hours =
            round(
                $minutes / 60,
                1
            );

        if ($hours < 24) {
            return $hours
                .' sa';
        }

        return round(
            $hours / 24,
            1
        )
            .' gün';
    }

    public function getOldestActiveAlarmProperty(): ?CrmAlarm
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
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereNull(
                            'snoozed_until'
                        )
                        ->orWhere(
                            'snoozed_until',
                            '<=',
                            now()
                        );
                }
            )
            ->oldest(
                'created_at'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONEL ALARM PERFORMANSI
    |--------------------------------------------------------------------------
    */

    public function getStaffAlarmPerformanceProperty(): Collection
    {
        $alarms =
            CrmAlarm::query()
                ->with([
                    'conversation.assignedUser',
                ])
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->whereHas(
                    'conversation',
                    fn (Builder $query) =>
                        $query->whereNotNull(
                            'assigned_user_id'
                        )
                )
                ->get();

        if ($alarms->isEmpty()) {
            return collect();
        }

        return $alarms
            ->groupBy(
                fn (CrmAlarm $alarm): int =>
                    (int) (
                        $alarm
                            ->conversation
                            ?->assigned_user_id
                        ?? 0
                    )
            )
            ->map(
                function (
                    Collection $staffAlarms
                ): array {
                    $first =
                        $staffAlarms
                            ->first();

                    $staff =
                        $first
                            ?->conversation
                            ?->assignedUser;

                    $active =
                        $staffAlarms
                            ->filter(
                                fn (CrmAlarm $alarm): bool =>
                                    ! $alarm->is_resolved
                                    && ! $alarm->isSnoozed()
                            );

                    $critical =
                        $active
                            ->where(
                                'severity',
                                'critical'
                            )
                            ->count();

                    $slaBreached =
                        $active
                            ->filter(
                                fn (CrmAlarm $alarm): bool =>
                                    $this->alarmSlaBreached(
                                        $alarm
                                    )
                            )
                            ->count();

                    $resolved =
                        $staffAlarms
                            ->where(
                                'is_resolved',
                                true
                            )
                            ->filter(
                                fn (CrmAlarm $alarm): bool =>
                                    $alarm->resolved_at
                                    && $alarm->resolved_at->gte(
                                        now()->subDays(30)
                                    )
                            );

                    $averageResolutionMinutes =
                        0;

                    if ($resolved->isNotEmpty()) {
                        $averageResolutionMinutes =
                            (int) round(
                                $resolved->avg(
                                    fn (CrmAlarm $alarm): int =>
                                        (int) $alarm
                                            ->created_at
                                            ->diffInMinutes(
                                                $alarm->resolved_at
                                            )
                                )
                            );
                    }

                    return [
                        'staff_user_id' =>
                            (int) (
                                $staff?->id
                                ?? 0
                            ),

                        'name' =>
                            $staff?->name
                            ?: $staff?->email
                            ?: 'Personel',

                        'active' =>
                            $active->count(),

                        'critical' =>
                            $critical,

                        'sla_breached' =>
                            $slaBreached,

                        'resolved_30_days' =>
                            $resolved->count(),

                        'average_resolution_minutes' =>
                            $averageResolutionMinutes,

                        'average_resolution_label' =>
                            $this->formatDurationMinutes(
                                $averageResolutionMinutes
                            ),
                    ];
                }
            )
            ->sortByDesc(
                fn (array $row): int =>
                    (
                        $row['sla_breached']
                        * 1000
                    )
                    + (
                        $row['critical']
                        * 100
                    )
                    + $row['active']
            )
            ->values();
    }

    protected function formatDurationMinutes(
        int $minutes
    ): string {
        if ($minutes <= 0) {
            return '-';
        }

        if ($minutes < 60) {
            return $minutes
                .' dk';
        }

        $hours =
            round(
                $minutes / 60,
                1
            );

        if ($hours < 24) {
            return $hours
                .' sa';
        }

        return round(
            $hours / 24,
            1
        )
            .' gün';
    }

    /*
    |--------------------------------------------------------------------------
    | GÜNÜN EN RİSKLİ MÜŞTERİLERİ
    |--------------------------------------------------------------------------
    |
    | Risk puanı:
    | - Alarm önceliği
    | - Lead skoru
    | - Açık fırsatlar içindeki göreli tahmini değer
    | - Geciken takip / sessizlik süresi
    |
    */

    public function getTopRiskCustomersProperty(): Collection
    {
        $alarms =
            CrmAlarm::query()
                ->with([
                    'conversation.aiBot',
                    'conversation.assignedUser',
                ])
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->where(
                    'is_resolved',
                    false
                )
                ->where(
                    function (
                        Builder $query
                    ): void {
                        $query
                            ->whereNull(
                                'snoozed_until'
                            )
                            ->orWhere(
                                'snoozed_until',
                                '<=',
                                now()
                            );
                    }
                )
                ->get();

        if ($alarms->isEmpty()) {
            return collect();
        }

        $maxEstimatedValue =
            max(
                1,
                (float) $alarms
                    ->map(
                        fn (CrmAlarm $alarm): float =>
                            (float) (
                                $alarm
                                    ->conversation
                                    ?->estimated_value
                                ?? 0
                            )
                    )
                    ->max()
            );

        return $alarms
            ->groupBy(
                'conversation_control_id'
            )
            ->map(
                function (
                    Collection $customerAlarms
                ) use (
                    $maxEstimatedValue
                ): array {
                    /** @var CrmAlarm $primaryAlarm */
                    $primaryAlarm =
                        $customerAlarms
                            ->sortByDesc(
                                'priority_score'
                            )
                            ->first();

                    $conversation =
                        $primaryAlarm
                            ->conversation;

                    $leadScore =
                        max(
                            0,
                            min(
                                100,
                                (int) (
                                    $conversation
                                        ?->lead_score
                                    ?? 0
                                )
                            )
                        );

                    $alarmPriority =
                        max(
                            0,
                            min(
                                100,
                                (int) $customerAlarms
                                    ->max(
                                        'priority_score'
                                    )
                            )
                        );

                    $estimatedValue =
                        max(
                            0,
                            (float) (
                                $conversation
                                    ?->estimated_value
                                ?? 0
                            )
                        );

                    $valueScore =
                        $estimatedValue > 0
                            ? min(
                                100,
                                (int) round(
                                    (
                                        $estimatedValue
                                        / $maxEstimatedValue
                                    )
                                    * 100
                                )
                            )
                            : 0;

                    $delayScore =
                        $this->conversationDelayScore(
                            $conversation
                        );

                    $riskScore =
                        (int) round(
                            (
                                $alarmPriority
                                * 0.45
                            )
                            +
                            (
                                $leadScore
                                * 0.30
                            )
                            +
                            (
                                $valueScore
                                * 0.15
                            )
                            +
                            (
                                $delayScore
                                * 0.10
                            )
                        );

                    $riskScore =
                        max(
                            0,
                            min(
                                100,
                                $riskScore
                            )
                        );

                    return [
                        'conversation_id' =>
                            (int) (
                                $conversation?->id
                                ?? 0
                            ),

                        'customer_name' =>
                            $conversation?->customer_name
                            ?: $conversation?->whatsapp_number
                            ?: 'Müşteri',

                        'risk_score' =>
                            $riskScore,

                        'lead_score' =>
                            $leadScore,

                        'alarm_priority' =>
                            $alarmPriority,

                        'estimated_value' =>
                            $estimatedValue,

                        'delay_score' =>
                            $delayScore,

                        'alarm_count' =>
                            $customerAlarms->count(),

                        'alarm_type' =>
                            $primaryAlarm
                                ->typeLabel(),

                        'recommended_action' =>
                            $primaryAlarm
                                ->recommended_action,

                        'assigned_user' =>
                            $conversation
                                ?->assignedUser
                                ?->name,

                        'bot_name' =>
                            $conversation
                                ?->aiBot
                                ?->name,
                    ];
                }
            )
            ->sortByDesc(
                'risk_score'
            )
            ->take(5)
            ->values();
    }

    protected function conversationDelayScore(
        ?\App\Models\ConversationControl $conversation
    ): int {
        if (! $conversation) {
            return 0;
        }

        $score =
            0;

        if (
            $conversation->next_follow_up_at
            && $conversation->next_follow_up_at->isPast()
        ) {
            $hours =
                max(
                    0,
                    (int) floor(
                        $conversation
                            ->next_follow_up_at
                            ->diffInMinutes(
                                now()
                            )
                        / 60
                    )
                );

            $score =
                max(
                    $score,
                    min(
                        100,
                        40
                        + (
                            $hours
                            * 5
                        )
                    )
                );
        }

        if (
            $conversation->last_contact_at
            && $conversation->lead_status === 'proposal'
        ) {
            $hours =
                max(
                    0,
                    (int) floor(
                        $conversation
                            ->last_contact_at
                            ->diffInMinutes(
                                now()
                            )
                        / 60
                    )
                );

            if ($hours >= 24) {
                $score =
                    max(
                        $score,
                        min(
                            100,
                            45
                            + (
                                (int) floor(
                                    (
                                        $hours
                                        - 24
                                    )
                                    / 3
                                )
                                * 4
                            )
                        )
                    );
            }
        }

        return $score;
    }

    public function getSnoozedCountProperty(): int
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
            ->whereNotNull(
                'snoozed_until'
            )
            ->where(
                'snoozed_until',
                '>',
                now()
            )
            ->count();
    }

    public function snoozeAlarm(
        int $alarmId,
        string $duration
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

        $until =
            match ($duration) {
                '1h' =>
                    now()->addHour(),

                '3h' =>
                    now()->addHours(3),

                'tomorrow' =>
                    now()
                        ->addDay()
                        ->setTime(
                            9,
                            0
                        ),

                default =>
                    null,
            };

        if (! $until) {
            return;
        }

        $alarm->update([
            'snoozed_until' =>
                $until,

            /*
            | Erteleme bittiğinde yeniden bir kez bildirim gönderebilsin.
            */
            'notified_at' =>
                null,
        ]);

        $this->auditService()
            ->record(
                alarm: $alarm,
                eventType: 'snoozed',
                user: auth()->user(),
                description: 'Alarm ertelendi.',
                meta: [
                    'snoozed_until' =>
                        $until->toDateTimeString(),
                ],
            );
    }

    public function snoozeAlarmCustom(
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

        $raw =
            trim(
                (string) (
                    $this->customSnoozeUntil[
                        $alarmId
                    ]
                    ?? ''
                )
            );

        if ($raw === '') {
            return;
        }

        try {
            $until =
                Carbon::parse(
                    $raw
                );
        } catch (\Throwable) {
            return;
        }

        if ($until->isPast()) {
            return;
        }

        $alarm->update([
            'snoozed_until' =>
                $until,

            'notified_at' =>
                null,
        ]);

        $this->auditService()
            ->record(
                alarm: $alarm,
                eventType: 'snoozed',
                user: auth()->user(),
                description: 'Alarm özel tarihe ertelendi.',
                meta: [
                    'snoozed_until' =>
                        $until->toDateTimeString(),
                ],
            );
    }

    public function clearAlarmSnooze(
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

        $alarm->clearSnooze();

        $this->auditService()
            ->record(
                alarm: $alarm,
                eventType: 'snooze_cleared',
                user: auth()->user(),
                description: 'Alarm ertelemesi kaldırıldı.',
            );
    }

    protected function auditService(): CrmAlarmAuditService
    {
        return app(
            CrmAlarmAuditService::class
        );
    }

    public function toggleAlarmHistory(
        int $alarmId
    ): void {
        if (
            in_array(
                $alarmId,
                $this->expandedHistories,
                true
            )
        ) {
            $this->expandedHistories =
                array_values(
                    array_filter(
                        $this->expandedHistories,
                        fn (int $id): bool =>
                            $id !== $alarmId
                    )
                );

            return;
        }

        $this->expandedHistories[] =
            $alarmId;
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

        $this->auditService()
            ->record(
                alarm: $alarm,
                eventType: 'resolved',
                user: auth()->user(),
                description: 'Alarm manuel olarak çözüldü.',
            );
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

        $this->auditService()
            ->record(
                alarm: $alarm,
                eventType: 'reopened',
                user: auth()->user(),
                description: 'Alarm yeniden açıldı.',
            );
    }

    public function resetFilters(): void
    {
        $this->statusFilter =
            'active';

        $this->severityFilter =
            'all';

        $this->typeFilter =
            'all';

        $this->search =
            '';
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