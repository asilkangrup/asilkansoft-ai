<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Models\CrmAlarm;
use App\Services\CrmAnalyticsService;
use App\Services\CrmForecastService;
use App\Services\CrmManagerSummaryService;
use App\Services\CrmSalesGoalService;
use App\Services\CrmStaffSalesGoalService;
use App\Services\OrganizationAccessService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Raporlar extends Page
{
    protected string $view = 'filament.pages.raporlar';
    protected static ?string $title = 'Raporlar';
    protected static ?string $navigationLabel = 'Raporlar';
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static ?int $navigationSort = 34;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';


    /*
    |--------------------------------------------------------------------------
    | ROL BAZLI ERİŞİM
    |--------------------------------------------------------------------------
    */

    public static function canAccess(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess()
            || app(OrganizationAccessService::class)->can('reports');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public string $period = '30';
    public string $channelFilter = 'all';
    public string $botFilter = 'all';

    public string $monthlySalesTarget = '';

    public array $staffSalesTargets = [];

    protected function analytics(): CrmAnalyticsService
    {
        return app(CrmAnalyticsService::class);
    }

    protected function forecastService(): CrmForecastService
    {
        return app(CrmForecastService::class);
    }

    protected function managerSummaryService(): CrmManagerSummaryService
    {
        return app(
            CrmManagerSummaryService::class
        );
    }

    protected function salesGoalService(): CrmSalesGoalService
    {
        return app(CrmSalesGoalService::class);
    }

    protected function staffSalesGoalService(): CrmStaffSalesGoalService
    {
        return app(CrmStaffSalesGoalService::class);
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

    protected function reportOwnerUser()
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->is_admin) {
            return $user;
        }

        $organization =
            $this->currentOrganization();

        if (! $organization) {
            return $user;
        }

        return $organization->owner
            ?? \App\Models\User::query()
                ->find(
                    $organization->owner_user_id
                )
            ?? $user;
    }

    protected function reportOwnerUserId(): int
    {
        return (int) (
            $this->reportOwnerUser()?->id
            ?? auth()->id()
            ?? 0
        );
    }

    protected function reportOrganizationId(): ?int
    {
        if (auth()->user()?->is_admin) {
            return null;
        }

        return $this->currentOrganization()?->id;
    }

    protected function canManageReportGoals(): bool
    {
        return in_array(
            $this->currentRole(),
            [
                'admin',
                'owner',
                'manager',
            ],
            true
        );
    }

    protected function conversationScopeQuery(): Builder
    {
        $query =
            ConversationControl::query();

        $organizationId =
            $this->reportOrganizationId();

        if ($organizationId !== null) {
            return $query->where(
                'organization_id',
                $organizationId
            );
        }

        return $query->where(
            'user_id',
            $this->reportOwnerUserId()
        );
    }

    protected function alarmScopeQuery(): Builder
    {
        $query =
            CrmAlarm::query();

        $organizationId =
            $this->reportOrganizationId();

        if ($organizationId !== null) {
            return $query->whereHas(
                'conversation',
                fn (Builder $conversationQuery) =>
                    $conversationQuery->where(
                        'organization_id',
                        $organizationId
                    )
            );
        }

        return $query->where(
            'user_id',
            $this->reportOwnerUserId()
        );
    }

    public function mount(): void
    {
        $target =
            (float) (
                $this->reportOwnerUser()?->monthly_sales_target
                ?? 0
            );

        $this->monthlySalesTarget =
            $target > 0
                ? number_format(
                    $target,
                    2,
                    '.',
                    ''
                )
                : '';

        $this->loadStaffSalesTargets();
    }

    protected function startDate(): Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(6)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            'all' => Carbon::create(2000, 1, 1)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
    }

    protected function baseQuery(): Builder
    {
        return $this->conversationScopeQuery()
            ->with(['aiBot', 'assignedUser'])
            ->where('created_at', '>=', $this->startDate())
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            );
    }

    public function getTotalLeadsProperty(): int
    {
        return $this->baseQuery()->count();
    }

    public function getOpenLeadsProperty(): int
    {
        return $this->baseQuery()->whereNotIn('lead_status', ['won', 'lost'])->count();
    }

    public function getHotLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_temperature', 'hot')->count();
    }

    public function getProposalLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'proposal')->count();
    }

    public function getWonLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'won')->count();
    }

    public function getLostLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'lost')->count();
    }

    public function getConversionRateProperty(): float
    {
        $total = $this->wonLeads + $this->lostLeads;

        if ($total === 0) {
            return 0;
        }

        return round(($this->wonLeads / $total) * 100, 1);
    }

    public function getTodayLeadsProperty(): int
    {
        return $this->conversationScopeQuery()
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | FÄ°NANSAL PERFORMANS
    |--------------------------------------------------------------------------
    */

    public function getOpenPipelineValueProperty(): float
    {
        return round(
            (float) $this->baseQuery()
                ->whereNotIn('lead_status', ['won', 'lost'])
                ->whereNotNull('estimated_value')
                ->sum('estimated_value'),
            2
        );
    }

    public function getWeightedPipelineValueProperty(): float
    {
        return round(
            $this->baseQuery()
                ->whereNotIn('lead_status', ['won', 'lost'])
                ->whereNotNull('estimated_value')
                ->where('estimated_value', '>', 0)
                ->get()
                ->sum(
                    fn (ConversationControl $conversation): float =>
                        $this->forecastService()->weightedValue($conversation)
                ),
            2
        );
    }

    public function getRealizedRevenueProperty(): float
    {
        return round(
            (float) $this->baseQuery()
                ->where('lead_status', 'won')
                ->whereNotNull('actual_value')
                ->sum('actual_value'),
            2
        );
    }

    public function getAverageSaleValueProperty(): float
    {
        return round(
            (float) $this->baseQuery()
                ->where('lead_status', 'won')
                ->whereNotNull('actual_value')
                ->where('actual_value', '>', 0)
                ->avg('actual_value'),
            2
        );
    }

    public function getForecastComparisonProperty(): array
    {
        $sales = $this->baseQuery()
            ->where('lead_status', 'won')
            ->whereNotNull('estimated_value')
            ->whereNotNull('actual_value')
            ->get([
                'estimated_value',
                'actual_value',
            ]);

        if ($sales->isEmpty()) {
            return [
                'estimated' => 0.0,
                'actual' => 0.0,
                'difference' => 0.0,
                'accuracy' => null,
                'count' => 0,
            ];
        }

        $estimated = round(
            (float) $sales->sum('estimated_value'),
            2
        );

        $actual = round(
            (float) $sales->sum('actual_value'),
            2
        );

        $difference = round(
            $actual - $estimated,
            2
        );

        $accuracy = null;

        if ($estimated > 0) {
            $accuracy = (int) round(
                max(
                    0,
                    min(
                        100,
                        100 - (
                            abs($actual - $estimated)
                            / $estimated
                            * 100
                        )
                    )
                )
            );
        }

        return [
            'estimated' => $estimated,
            'actual' => $actual,
            'difference' => $difference,
            'accuracy' => $accuracy,
            'count' => $sales->count(),
        ];
    }

    public function getAiMessageCountProperty(): int
    {
        return $this->analytics()->aiMessageCount($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getHumanMessageCountProperty(): int
    {
        return $this->analytics()->humanMessageCount($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getCustomerMessageCountProperty(): int
    {
        return $this->analytics()->customerMessageCount($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getAiResponseRateProperty(): float
    {
        return $this->analytics()->aiResponseRate($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getHumanResponseRateProperty(): float
    {
        return $this->analytics()->humanResponseRate($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getTakeoverCountProperty(): int
    {
        return $this->analytics()->takeoverCount($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getActiveHumanTakeoversProperty(): int
    {
        return $this->analytics()->activeHumanTakeovers($this->reportOwnerUserId(), $this->reportOrganizationId());
    }

    public function getAverageResponseSecondsProperty(): float
    {
        return $this->analytics()->averageFirstResponseSeconds($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getAverageAiResponseSecondsProperty(): float
    {
        return $this->analytics()->averageAiResponseSeconds($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getAverageHumanResponseSecondsProperty(): float
    {
        return $this->analytics()->averageHumanResponseSeconds($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getAverageResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageResponseSeconds);
    }

    public function getAverageAiResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageAiResponseSeconds);
    }

    public function getAverageHumanResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageHumanResponseSeconds);
    }

    public function getStaffPerformanceProperty(): Collection
    {
        return $this->analytics()->staffPerformance($this->reportOwnerUserId(), $this->startDate(), $this->reportOrganizationId());
    }

    public function getSevenDayTrendProperty(): array
    {
        $result = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            $count = $this->conversationScopeQuery()
                ->whereBetween('created_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ])
                ->when(
                    $this->channelFilter !== 'all',
                    fn (Builder $query) => $query->where('channel', $this->channelFilter)
                )
                ->when(
                    $this->botFilter !== 'all',
                    fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
                )
                ->count();

            $result[] = [
                'label' => $date->format('d.m'),
                'count' => $count,
            ];
        }

        return $result;
    }

    public function getChannelStatsProperty(): array
    {
        $channels = [
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'web' => 'Web',
        ];

        $total = max(1, $this->totalLeads);
        $rows = [];

        foreach ($channels as $key => $label) {
            $count = $this->baseQuery()->where('channel', $key)->count();

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | SATIÅ HUNÄ°SÄ°
    |--------------------------------------------------------------------------
    */

    public function getSalesFunnelProperty(): array
    {
        $stages = [
            'new' => 'Yeni',
            'contacted' => 'GÃ¶rÃ¼ÅŸÃ¼lÃ¼yor',
            'qualified' => 'Nitelikli',
            'proposal' => 'Teklif',
            'won' => 'KazanÄ±ldÄ±',
            'lost' => 'Kaybedildi',
        ];

        $total =
            max(
                1,
                $this->totalLeads
            );

        $rows = [];

        foreach ($stages as $key => $label) {
            $count =
                $this->baseQuery()
                    ->where(
                        'lead_status',
                        $key
                    )
                    ->count();

            $rows[] = [
                'key' =>
                    $key,

                'label' =>
                    $label,

                'count' =>
                    $count,

                'percent' =>
                    round(
                        (
                            $count
                            / $total
                        )
                        * 100,
                        1
                    ),
            ];
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | KAYIP NEDENLERÄ°
    |--------------------------------------------------------------------------
    */

    public function getLostReasonStatsProperty(): Collection
    {
        return $this->baseQuery()
            ->where(
                'lead_status',
                'lost'
            )
            ->selectRaw(
                "
                COALESCE(NULLIF(TRIM(lost_reason), ''), 'BelirtilmemiÅŸ') as reason,
                COUNT(*) as total
                "
            )
            ->groupBy(
                'reason'
            )
            ->orderByDesc(
                'total'
            )
            ->limit(10)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KANAL BAZLI CÄ°RO
    |--------------------------------------------------------------------------
    */

    public function getChannelRevenueStatsProperty(): array
    {
        $channels = [
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'web' => 'Web',
        ];

        $rows = [];

        foreach ($channels as $key => $label) {
            $won =
                $this->baseQuery()
                    ->where(
                        'channel',
                        $key
                    )
                    ->where(
                        'lead_status',
                        'won'
                    );

            $revenue =
                round(
                    (float) (
                        clone $won
                    )
                        ->whereNotNull(
                            'actual_value'
                        )
                        ->sum(
                            'actual_value'
                        ),
                    2
                );

            $sales =
                (clone $won)
                    ->count();

            $leads =
                $this->baseQuery()
                    ->where(
                        'channel',
                        $key
                    )
                    ->count();

            $conversion =
                $leads > 0
                    ? round(
                        (
                            $sales
                            / $leads
                        )
                        * 100,
                        1
                    )
                    : 0;

            $rows[] = [
                'key' =>
                    $key,

                'label' =>
                    $label,

                'leads' =>
                    $leads,

                'sales' =>
                    $sales,

                'revenue' =>
                    $revenue,

                'conversion' =>
                    $conversion,
            ];
        }

        usort(
            $rows,
            fn (array $a, array $b): int =>
                $b['revenue']
                <=>
                $a['revenue']
        );

        return $rows;
    }

    public function getBotStatsProperty(): Collection
    {
        return $this->conversationScopeQuery()
            ->selectRaw(
                "
                ai_bot_id,
                COUNT(*) as total_leads,
                SUM(CASE WHEN lead_status = 'won' THEN 1 ELSE 0 END) as won_leads,
                SUM(CASE WHEN lead_temperature = 'hot' THEN 1 ELSE 0 END) as hot_leads,
                COALESCE(
                    SUM(
                        CASE
                            WHEN lead_status NOT IN ('won', 'lost')
                            THEN estimated_value
                            ELSE 0
                        END
                    ),
                    0
                ) as pipeline_value,
                COALESCE(
                    SUM(
                        CASE
                            WHEN lead_status = 'won'
                            THEN actual_value
                            ELSE 0
                        END
                    ),
                    0
                ) as realized_revenue
                "
            )
            ->where('created_at', '>=', $this->startDate())
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            )
            ->groupBy('ai_bot_id')
            ->orderByDesc('total_leads')
            ->with('aiBot')
            ->get();
    }

    public function getBotsProperty(): Collection
    {
        return $this->conversationScopeQuery()
            ->with('aiBot')
            ->whereNotNull('ai_bot_id')
            ->get()
            ->pluck('aiBot')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | WAI ALARM MERKEZÄ° Ã–ZETÄ°
    |--------------------------------------------------------------------------
    */

    public function getActiveAlarmCountProperty(): int
    {
        return $this->alarmScopeQuery()
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

    public function getCriticalAlarmCountProperty(): int
    {
        return $this->alarmScopeQuery()
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

    public function getSnoozedAlarmCountProperty(): int
    {
        return $this->alarmScopeQuery()
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

    public function getResolvedAlarmTodayCountProperty(): int
    {
        return $this->alarmScopeQuery()
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

    public function getRecentCriticalAlarmsProperty(): Collection
    {
        return $this->alarmScopeQuery()
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
            ->limit(5)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | AYLIK SATIÅ HEDEFÄ°
    |--------------------------------------------------------------------------
    */

    public function getMonthlyGoalProgressProperty(): array
    {
        $user =
            $this->reportOwnerUser();

        if (! $user) {
            return [
                'target' => 0.0,
                'realized' => 0.0,
                'remaining' => 0.0,
                'exceeded' => 0.0,
                'percent' => 0.0,
                'completed' => false,
            ];
        }

        return $this->salesGoalService()
            ->progress(
                $user,
                $this->reportOrganizationId()
            );
    }

    public function saveMonthlySalesTarget(): void
    {
        if (! $this->canManageReportGoals()) {
            abort(403);
        }

        $user =
            $this->reportOwnerUser();

        if (! $user) {
            return;
        }

        $target =
            trim(
                $this->monthlySalesTarget
            );

        $value =
            $target !== ''
                ? max(
                    0,
                    (float) str_replace(
                        ',',
                        '.',
                        $target
                    )
                )
                : null;

        $this->salesGoalService()
            ->saveTarget(
                user: $user,
                target: $value,
            );

        $user->refresh();

        $this->monthlySalesTarget =
            $value !== null
                ? number_format(
                    $value,
                    2,
                    '.',
                    ''
                )
                : '';

        $this->dispatch(
            'monthly-sales-target-saved'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONEL SATIÅ HEDEFLERÄ°
    |--------------------------------------------------------------------------
    */

    public function getStaffGoalRowsProperty(): Collection
    {
        return $this->staffSalesGoalService()
            ->rows(
                $this->reportOwnerUserId(),
                $this->reportOrganizationId()
            );
    }

    protected function loadStaffSalesTargets(): void
    {
        $this->staffSalesTargets = [];

        foreach (
            $this->staffSalesGoalService()
                ->rows(
                    $this->reportOwnerUserId(),
                    $this->reportOrganizationId()
                )
            as $row
        ) {
            $staffId =
                (int) $row['staff_user_id'];

            $target =
                (float) $row['target'];

            $this->staffSalesTargets[
                $staffId
            ] =
                $target > 0
                    ? number_format(
                        $target,
                        2,
                        '.',
                        ''
                    )
                    : '';
        }
    }

    public function saveStaffSalesTarget(
        int $staffUserId
    ): void {
        if (! $this->canManageReportGoals()) {
            abort(403);
        }

        $raw =
            trim(
                (string) (
                    $this->staffSalesTargets[
                        $staffUserId
                    ]
                    ?? ''
                )
            );

        $value =
            $raw !== ''
                ? max(
                    0,
                    (float) str_replace(
                        ',',
                        '.',
                        $raw
                    )
                )
                : 0;

        $saved =
            $this->staffSalesGoalService()
                ->saveTarget(
                    ownerUserId:
                        $this->reportOwnerUserId(),

                    staffUserId:
                        $staffUserId,

                    target:
                        $value,

                    organizationId:
                        $this->reportOrganizationId(),
                );

        if (! $saved) {
            return;
        }

        $this->staffSalesTargets[
            $staffUserId
        ] =
            $value > 0
                ? number_format(
                    $value,
                    2,
                    '.',
                    ''
                )
                : '';

        $this->dispatch(
            'staff-sales-target-saved'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | WAI YÃ–NETÄ°CÄ° Ã–ZETÄ°
    |--------------------------------------------------------------------------
    */

    public function getManagerSummaryProperty()
    {
        $summary =
            $this->managerSummaryService()
                ->latest(
                    $this->reportOwnerUserId()
                );

        if (
            ! $summary
            || ! $summary->summary_date
            || ! $summary->summary_date->isToday()
        ) {
            try {
                $summary =
                    $this->managerSummaryService()
                        ->generate(
                            $this->reportOwnerUser()
                        );
            } catch (\Throwable $exception) {
                report(
                    $exception
                );
            }
        }

        return $summary;
    }

    public function refreshManagerSummary(): void
    {
        $user =
            $this->reportOwnerUser();

        if (! $user) {
            return;
        }

        try {
            $this->managerSummaryService()
                ->generate(
                    $user
                );

            unset(
                $this->managerSummary
            );

            $this->dispatch(
                'manager-summary-refreshed'
            );
        } catch (\Throwable $exception) {
            report(
                $exception
            );

            $this->dispatch(
                'manager-summary-failed'
            );
        }
    }

    public function resetFilters(): void
    {
        $this->period = '30';
        $this->channelFilter = 'all';
        $this->botFilter = 'all';
    }


    public function getLiveKpiTrendsProperty(): array
    {
        $s=now()->subDays(6)->startOfDay();
        $rows=$this->conversationScopeQuery()->where('created_at','>=',$s)->get(['created_at','lead_status','lead_temperature']);
        $days=collect(range(0,6))->map(fn($i)=>$s->copy()->addDays($i));
        $daily=fn($f)=>$days->map(fn($d)=>$rows->filter(fn(ConversationControl $c)=>$c->created_at?->isSameDay($d)&&$f($c))->count())->all();
        return [
            'total'=>$this->liveSeries($daily(fn(ConversationControl $c)=>true)),
            'open'=>$this->liveSeries($daily(fn(ConversationControl $c)=>!in_array($c->lead_status,['won','lost'],true))),
            'hot'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->lead_temperature==='hot')),
            'proposal'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->lead_status==='proposal')),
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