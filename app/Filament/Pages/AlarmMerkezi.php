<?php

namespace App\Filament\Pages;

use App\Models\CrmAlarm;
use App\Services\CrmAlarmAuditService;
use App\Services\OrganizationAccessService;
use App\Services\RealEstateIsolationService;
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

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    public string $statusFilter =
        'active';

    public string $severityFilter =
        'all';

    public string $typeFilter =
        'all';

    public array $customSnoozeUntil = [];

    public array $expandedHistories = [];

    public static function canAccess(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess()
            || app(OrganizationAccessService::class)->can('alarms');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function currentOrganization()
    {
        return $this
            ->accessService()
            ->currentOrganization();
    }

    protected function currentRole(): ?string
    {
        return $this
            ->accessService()
            ->currentRole();
    }

    protected function canWriteAlarms(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess()
            || $this->accessService()->canWriteCrm();
    }

    protected function alarmScopeQuery(): Builder
    {
        $user = auth()->user();

        $query = CrmAlarm::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        $organization =
            $this->currentOrganization();

        if (! $organization) {
            return $query->where(
                'user_id',
                $user->id
            );
        }

        $role =
            $this->currentRole();

        return $query->whereHas(
            'conversation',
            function (Builder $conversationQuery) use (
                $organization,
                $role,
                $user
            ): void {
                $conversationQuery->where(
                    'organization_id',
                    $organization->id
                );

                if ($role === 'sales') {
                    $conversationQuery->where(
                        'assigned_user_id',
                        $user->id
                    );
                }
            }
        );
    }

    protected function baseQuery(): Builder
    {
        return $this->alarmScopeQuery()
            ->with([
                'conversation.aiBot',
                'conversation.assignedUser',
                'events.user',
            ])
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
        return $this->alarmScopeQuery()
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
        return $this->alarmScopeQuery()
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
        return $this->alarmScopeQuery()
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
    | UyarÄ±: 8 saat
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
            .' gÃ¼n '
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
        return $this->alarmScopeQuery()
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
            $this->alarmScopeQuery()
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
            .' gÃ¼n';
    }

    public function getOldestActiveAlarmProperty(): ?CrmAlarm
    {
        return $this->alarmScopeQuery()
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
            $this->alarmScopeQuery()
                ->with([
                    'conversation.assignedUser',
                ])
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
            .' gÃ¼n';
    }

    /*
    |--------------------------------------------------------------------------
    | GÃœNÃœN EN RÄ°SKLÄ° MÃœÅTERÄ°LERÄ°
    |--------------------------------------------------------------------------
    |
    | Risk puanÄ±:
    | - Alarm Ã¶nceliÄŸi
    | - Lead skoru
    | - AÃ§Ä±k fÄ±rsatlar iÃ§indeki gÃ¶reli tahmini deÄŸer
    | - Geciken takip / sessizlik sÃ¼resi
    |
    */

    public function getTopRiskCustomersProperty(): Collection
    {
        $alarms =
            $this->alarmScopeQuery()
                ->with([
                    'conversation.aiBot',
                    'conversation.assignedUser',
                ])
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
                            ?: 'MÃ¼ÅŸteri',

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
        return $this->alarmScopeQuery()
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
        if (! $this->canWriteAlarms()) {
            abort(403);
        }

        $alarm =
            $this->alarmScopeQuery()
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
            | Erteleme bittiÄŸinde yeniden bir kez bildirim gÃ¶nderebilsin.
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
        if (! $this->canWriteAlarms()) {
            abort(403);
        }

        $alarm =
            $this->alarmScopeQuery()
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
                description: 'Alarm Ã¶zel tarihe ertelendi.',
                meta: [
                    'snoozed_until' =>
                        $until->toDateTimeString(),
                ],
            );
    }

    public function clearAlarmSnooze(
        int $alarmId
    ): void {
        if (! $this->canWriteAlarms()) {
            abort(403);
        }

        $alarm =
            $this->alarmScopeQuery()
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
                description: 'Alarm ertelemesi kaldÄ±rÄ±ldÄ±.',
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
        if (! $this->canWriteAlarms()) {
            abort(403);
        }

        $alarm =
            $this->alarmScopeQuery()
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
                description: 'Alarm manuel olarak Ã§Ã¶zÃ¼ldÃ¼.',
            );
    }

    public function reopenAlarm(
        int $alarmId
    ): void {
        if (! $this->canWriteAlarms()) {
            abort(403);
        }

        $alarm =
            $this->alarmScopeQuery()
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
                description: 'Alarm yeniden aÃ§Ä±ldÄ±.',
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
    }


    public function getLiveKpiTrendsProperty(): array
    {
        $s=now()->subDays(6)->startOfDay();
        $rows=$this->alarmScopeQuery()->where(fn(Builder $q)=>$q->where('created_at','>=',$s)->orWhere('resolved_at','>=',$s))->get(['created_at','resolved_at','severity','snoozed_until']);
        $days=collect(range(0,6))->map(fn($i)=>$s->copy()->addDays($i));
        return [
            'active'=>$this->liveSeries($days->map(fn($d)=>$rows->filter(fn(CrmAlarm $a)=>$a->created_at?->isSameDay($d)&&!$a->resolved_at)->count())->all()),
            'critical'=>$this->liveSeries($days->map(fn($d)=>$rows->filter(fn(CrmAlarm $a)=>$a->created_at?->isSameDay($d)&&$a->severity==='critical')->count())->all()),
            'resolved'=>$this->liveSeries($days->map(fn($d)=>$rows->filter(fn(CrmAlarm $a)=>$a->resolved_at?->isSameDay($d))->count())->all()),
            'snoozed'=>$this->liveSeries($days->map(fn($d)=>$rows->filter(fn(CrmAlarm $a)=>$a->snoozed_until?->isSameDay($d))->count())->all()),
        ];
    }


    protected function liveSeries(array $v): array
    {
        while (count($v)<7) array_unshift($v,0);
        $v=array_slice(array_map('intval',$v),-7);
        $a=$v[6]??0; $b=$v[5]??0;
        if($a===0&&$b===0){$pct=0;$dir='flat';}
        elseif($b===0){$pct=$a>0?100:0;$dir=$a>0?'up':'flat';}
        else{$pct=(int)round((($a-$b)/$b)*100);$dir=$pct>0?'up':($pct<0?'down':'flat');}
        $min=min($v);$max=max($v);$flat=$min===$max;$range=max(1,$max-$min);
        $pts=collect($v)->map(function($n,$i)use($min,$range,$flat){
            $x=4+$i*(100/6);$y=$flat?21:5+(1-(($n-$min)/$range))*32;
            return round($x,1).','.round($y,1);
        })->implode(' ');
        return ['points'=>$pts,'trend_label'=>($pct>0?'+':'').$pct.'%','trend_direction'=>$dir];
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