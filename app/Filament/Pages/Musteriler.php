<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Models\User;
use App\Services\CrmActivityService;
use App\Services\CrmConversationSummaryService;
use App\Services\CrmDailySalesService;
use App\Services\CrmForecastService;
use App\Services\CrmRevenueService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Musteriler extends Page
{
    protected string $view = 'filament.pages.musteriler';

    protected static ?string $title = 'Müşteriler';

    protected static ?string $navigationLabel = 'Müşteriler';

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 31;

    /*
    |--------------------------------------------------------------------------
    | CRM AKTİVİTE SERVİSİ
    |--------------------------------------------------------------------------
    */

    protected function activityService(): CrmActivityService
    {
        return app(
            CrmActivityService::class
        );
    }

    protected function dailySalesService(): CrmDailySalesService
    {
        return app(
            CrmDailySalesService::class
        );
    }

    protected function forecastService(): CrmForecastService
    {
        return app(
            CrmForecastService::class
        );
    }

    protected function revenueService(): CrmRevenueService
    {
        return app(
            CrmRevenueService::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | WAI CRM ÖZETİNİ MANUEL YENİLE
    |--------------------------------------------------------------------------
    */

    public function refreshAiSummary(): void
    {
        $customer =
            $this->selectedCustomer;

        if (! $customer) {
            return;
        }

        try {
            app(
                CrmConversationSummaryService::class
            )->updateIfNeeded(
                conversation: $customer,
                force: true
            );

            $this->selectCustomer(
                $customer->id
            );

            $this->dispatch(
                'crm-ai-summary-refreshed'
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->dispatch(
                'crm-ai-summary-failed'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FİLTRELER
    |--------------------------------------------------------------------------
    */

    public string $search = '';

    public string $statusFilter = 'all';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

    public string $opportunityFilter = 'all';

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ MÜŞTERİ
    |--------------------------------------------------------------------------
    */

    public ?int $selectedCustomerId = null;

    /*
    |--------------------------------------------------------------------------
    | AKTİVİTE FİLTRESİ
    |--------------------------------------------------------------------------
    */

    public string $activityFilter = 'all';

    /*
    |--------------------------------------------------------------------------
    | CRM FORMU
    |--------------------------------------------------------------------------
    */

    public string $customerName = '';

    public string $customerEmail = '';

    public string $companyName = '';

    public string $leadStatus = 'new';

    public int $leadScore = 0;

    public string $notes = '';

    public ?int $assignedUserId = null;

    public string $nextFollowUpAt = '';

    public string $lostReason = '';

    public string $estimatedValue = '';

    public string $actualValue = '';

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $customerId =
            request()->integer(
                'customer'
            );

        if ($customerId > 0) {
            $customer = $this->customerQuery()
                ->whereKey(
                    $customerId
                )
                ->first();

            if ($customer) {
                $this->selectCustomer(
                    $customer->id
                );

                return;
            }
        }

        $firstCustomer = $this->customerQuery()
            ->latest('updated_at')
            ->first();

        if ($firstCustomer) {
            $this->selectCustomer(
                $firstCustomer->id
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ANA QUERY
    |--------------------------------------------------------------------------
    */

    protected function customerQuery(): Builder
    {
        return ConversationControl::query()
            ->with([
                'aiBot',
                'assignedUser',
            ])
            ->where(
                'user_id',
                auth()->id()
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİLER
    |--------------------------------------------------------------------------
    */

    public function getCustomersProperty(): Collection
    {
        return $this->customerQuery()
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
                                    'customer_email',
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
                $this->statusFilter !== 'all',
                fn (Builder $query) =>
                    $query->where(
                        'lead_status',
                        $this->statusFilter
                    )
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
            )
            ->when(
                $this->opportunityFilter !== 'all',
                function (Builder $query): void {
                    match ($this->opportunityFilter) {
                        'priority' =>
                            $query
                                ->where(
                                    'lead_score',
                                    '>=',
                                    85
                                )
                                ->whereNotIn(
                                    'lead_status',
                                    [
                                        'won',
                                        'lost',
                                    ]
                                ),

                        'risk' =>
                            $query
                                ->whereJsonContains(
                                    'tags',
                                    'Riskli Lead'
                                )
                                ->whereNotIn(
                                    'lead_status',
                                    [
                                        'won',
                                        'lost',
                                    ]
                                ),

                        'price_objection' =>
                            $query
                                ->whereJsonContains(
                                    'tags',
                                    'Fiyat İtirazı'
                                )
                                ->whereNotIn(
                                    'lead_status',
                                    [
                                        'won',
                                        'lost',
                                    ]
                                ),

                        'follow_up' =>
                            $query
                                ->whereNotNull(
                                    'next_follow_up_at'
                                )
                                ->whereNotIn(
                                    'lead_status',
                                    [
                                        'won',
                                        'lost',
                                    ]
                                ),

                        default =>
                            null,
                    };
                }
            )
            ->orderByRaw(
                "
                CASE
                    WHEN lead_temperature = 'hot' THEN 1
                    WHEN lead_temperature = 'warm' THEN 2
                    ELSE 3
                END
                "
            )
            ->orderByDesc('lead_score')
            ->orderByDesc('last_contact_at')
            ->orderByDesc('updated_at')
            ->limit(250)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ MÜŞTERİ
    |--------------------------------------------------------------------------
    */

    public function getSelectedCustomerProperty(): ?ConversationControl
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        return $this->customerQuery()
            ->whereKey(
                $this->selectedCustomerId
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ MÜŞTERİ AKTİVİTELERİ
    |--------------------------------------------------------------------------
    */

    public function getSelectedActivitiesProperty(): Collection
    {
        $customer =
            $this->selectedCustomer;

        if (! $customer) {
            return collect();
        }

        $query =
            $customer
                ->activities()
                ->with([
                    'performedByUser',
                ]);

        /*
        |--------------------------------------------------------------------------
        | TIMELINE FİLTRELERİ
        |--------------------------------------------------------------------------
        */

        match ($this->activityFilter) {
            'ai' =>
                $query->whereIn(
                    'type',
                    [
                        'ai_score',
                        'ai_status',
                        'ai_action',
                    ]
                ),

            'staff' =>
                $query->whereNotNull(
                    'performed_by_user_id'
                ),

            'sales' =>
                $query->whereIn(
                    'type',
                    [
                        'lead_status',
                        'won',
                        'lost',
                        'lead_score',
                        'lead_temperature',
                    ]
                ),

            'follow_up' =>
                $query->where(
                    'type',
                    'follow_up'
                ),

            'notes' =>
                $query->where(
                    'type',
                    'note'
                ),

            'control' =>
                $query->whereIn(
                    'type',
                    [
                        'human_takeover',
                        'ai_release',
                        'assignment',
                    ]
                ),

            default =>
                null,
        };

        return $query
            ->limit(50)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİVİTE FİLTRESİ AYARLA
    |--------------------------------------------------------------------------
    */

    public function setActivityFilter(
        string $filter
    ): void {
        $allowed = [
            'all',
            'ai',
            'staff',
            'sales',
            'follow_up',
            'notes',
            'control',
        ];

        if (
            ! in_array(
                $filter,
                $allowed,
                true
            )
        ) {
            return;
        }

        $this->activityFilter =
            $filter;
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ SEÇ
    |--------------------------------------------------------------------------
    */

    public function selectCustomer(
        int $customerId
    ): void {
        $customer = $this->customerQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $this->selectedCustomerId =
            $customer->id;

        $this->customerName =
            (string) $customer->customer_name;

        $this->customerEmail =
            (string) $customer->customer_email;

        $this->companyName =
            (string) $customer->company_name;

        $this->leadStatus =
            $customer->lead_status
            ?: 'new';

        $this->leadScore =
            (int) $customer->lead_score;

        $this->notes =
            (string) $customer->notes;

        $this->assignedUserId =
            $customer->assigned_user_id
                ? (int) $customer->assigned_user_id
                : null;

        $this->nextFollowUpAt =
            $customer->next_follow_up_at
                ? $customer->next_follow_up_at
                    ->format('Y-m-d\TH:i')
                : '';

        $this->lostReason =
            (string) $customer->lost_reason;

        $this->estimatedValue =
            $customer->estimated_value !== null
                ? number_format(
                    (float) $customer->estimated_value,
                    2,
                    '.',
                    ''
                )
                : '';

        $this->actualValue =
            $customer->actual_value !== null
                ? number_format(
                    (float) $customer->actual_value,
                    2,
                    '.',
                    ''
                )
                : '';
    }

    /*
    |--------------------------------------------------------------------------
    | CRM KAYDET
    |--------------------------------------------------------------------------
    */

    public function saveCustomer(): void
    {
        $activityService =
            $this->activityService();

        $customer =
            $this->selectedCustomer;

        if (! $customer) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ESKİ DEĞERLERİ AL
        |--------------------------------------------------------------------------
        */

        $oldStatus =
            $customer->lead_status
            ?: 'new';

        $oldScore =
            (int) $customer->lead_score;

        $oldTemperature =
            $customer->lead_temperature
            ?: 'cold';

        $oldAssignedUserId =
            $customer->assigned_user_id
                ? (int) $customer->assigned_user_id
                : null;

        $oldNotes =
            (string) $customer->notes;

        $oldFollowUp =
            $customer->next_follow_up_at
                ? $customer->next_follow_up_at->copy()
                : null;

        /*
        |--------------------------------------------------------------------------
        | YENİ PUAN / SICAKLIK
        |--------------------------------------------------------------------------
        */

        $score = max(
            0,
            min(
                100,
                (int) $this->leadScore
            )
        );

        $temperature = match (true) {
            $score >= 70 =>
                'hot',

            $score >= 40 =>
                'warm',

            default =>
                'cold',
        };

        /*
        |--------------------------------------------------------------------------
        | KAYIT VERİSİ
        |--------------------------------------------------------------------------
        */

        $allowedAssignedUserId =
            $this->assignedUserId;

        if ($allowedAssignedUserId !== null) {
            $allowedAssignedUserId =
                $this->teamMembers
                    ->contains(
                        'id',
                        (int) $allowedAssignedUserId
                    )
                        ? (int) $allowedAssignedUserId
                        : null;
        }

        $data = [
            'customer_name' =>
                trim($this->customerName)
                    ?: null,

            'customer_email' =>
                trim($this->customerEmail)
                    ?: null,

            'company_name' =>
                trim($this->companyName)
                    ?: null,

            'lead_status' =>
                $this->leadStatus,

            'lead_score' =>
                $score,

            'lead_temperature' =>
                $temperature,

            'assigned_user_id' =>
                $allowedAssignedUserId,

            'notes' =>
                trim($this->notes)
                    ?: null,

            'next_follow_up_at' =>
                $this->nextFollowUpAt !== ''
                    ? $this->nextFollowUpAt
                    : null,

            'lost_reason' =>
                $this->leadStatus === 'lost'
                    ? (
                        trim($this->lostReason)
                        ?: null
                    )
                    : null,
        ];

        if (
            $this->leadStatus === 'won'
            && ! $customer->won_at
        ) {
            $data['won_at'] = now();
            $data['lost_at'] = null;
        }

        if (
            $this->leadStatus === 'lost'
            && ! $customer->lost_at
        ) {
            $data['lost_at'] = now();
            $data['won_at'] = null;
        }

        if (
            ! in_array(
                $this->leadStatus,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            $data['won_at'] = null;
            $data['lost_at'] = null;
            $data['lost_reason'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | CRM KAYDI
        |--------------------------------------------------------------------------
        */

        $customer->update(
            $data
        );

        /*
        |--------------------------------------------------------------------------
        | TAHMİNİ SATIŞ DEĞERİ
        |--------------------------------------------------------------------------
        |
        | ConversationControl model fillable listesine bağımlı kalmadan
        | güvenli biçimde kaydedilir.
        |
        */

        $estimatedValue =
            $this->estimatedValue !== ''
                ? max(
                    0,
                    (float) str_replace(
                        ',',
                        '.',
                        $this->estimatedValue
                    )
                )
                : null;

        $actualValue =
            $this->actualValue !== ''
                ? max(
                    0,
                    (float) str_replace(
                        ',',
                        '.',
                        $this->actualValue
                    )
                )
                : null;

        $customer->forceFill([
            'estimated_value' =>
                $estimatedValue,

            'actual_value' =>
                $customer->lead_status === 'won'
                    ? $actualValue
                    : null,
        ])->save();

        $customer->refresh();

        /*
        |--------------------------------------------------------------------------
        | CRM AKTİVİTE GEÇMİŞİ
        |--------------------------------------------------------------------------
        |
        | Sadece gerçekten değişen değerler activity tablosuna yazılır.
        |
        */

        $performedBy =
            auth()->user();

        $activityService->leadStatusChanged(
            conversation: $customer,
            oldStatus: $oldStatus,
            newStatus: $customer->lead_status ?: 'new',
            performedBy: $performedBy,
        );

        if (
            $oldStatus !== 'won'
            && $customer->lead_status === 'won'
        ) {
            $activityService->won(
                conversation: $customer,
                performedBy: $performedBy,
            );
        }

        if (
            $oldStatus !== 'lost'
            && $customer->lead_status === 'lost'
        ) {
            $activityService->lost(
                conversation: $customer,
                reason: $customer->lost_reason,
                performedBy: $performedBy,
            );
        }

        $activityService->leadScoreChanged(
            conversation: $customer,
            oldScore: $oldScore,
            newScore: (int) $customer->lead_score,
            performedBy: $performedBy,
        );

        $activityService->temperatureChanged(
            conversation: $customer,
            oldTemperature: $oldTemperature,
            newTemperature: $customer->lead_temperature ?: 'cold',
            performedBy: $performedBy,
        );

        $activityService->assignmentChanged(
            conversation: $customer,
            oldUserId: $oldAssignedUserId,
            newUserId: $customer->assigned_user_id
                ? (int) $customer->assigned_user_id
                : null,
            performedBy: $performedBy,
        );

        $activityService->followUpChanged(
            conversation: $customer,
            oldDate: $oldFollowUp,
            newDate: $customer->next_follow_up_at,
            performedBy: $performedBy,
        );

        $activityService->notesChanged(
            conversation: $customer,
            oldNotes: $oldNotes,
            newNotes: (string) $customer->notes,
            performedBy: $performedBy,
        );

        /*
        |--------------------------------------------------------------------------
        | FORMU YENİLE
        |--------------------------------------------------------------------------
        */

        $this->selectCustomer(
            $customer->id
        );

        $this->dispatch(
            'crm-customer-saved'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HIZLI DURUM DEĞİŞTİR
    |--------------------------------------------------------------------------
    */

    public function setLeadStatus(
        string $status
    ): void {
        $allowed = [
            'new',
            'contacted',
            'qualified',
            'proposal',
            'won',
            'lost',
        ];

        if (
            ! in_array(
                $status,
                $allowed,
                true
            )
        ) {
            return;
        }

        $this->leadStatus =
            $status;

        $this->saveCustomer();
    }

    /*
    |--------------------------------------------------------------------------
    | PUAN DEĞİŞTİR
    |--------------------------------------------------------------------------
    */

    public function setLeadScore(
        int $score
    ): void {
        $this->leadScore =
            max(
                0,
                min(
                    100,
                    $score
                )
            );

        $this->saveCustomer();
    }

    /*
    |--------------------------------------------------------------------------
    | KENDİME ATA
    |--------------------------------------------------------------------------
    */

    public function assignToMe(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->assignedUserId =
            auth()->id();

        $this->saveCustomer();
    }

    /*
    |--------------------------------------------------------------------------
    | ATAMAYI KALDIR
    |--------------------------------------------------------------------------
    */

    public function clearAssignment(): void
    {
        $this->assignedUserId = null;

        $this->saveCustomer();
    }

    /*
    |--------------------------------------------------------------------------
    | FİLTRELERİ TEMİZLE
    |--------------------------------------------------------------------------
    */

    public function resetFilters(): void
    {
        $this->search = '';

        $this->statusFilter = 'all';

        $this->temperatureFilter = 'all';

        $this->channelFilter = 'all';

        $this->opportunityFilter = 'all';
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONELLER
    |--------------------------------------------------------------------------
    */

    public function getTeamMembersProperty(): Collection
    {
        $assignedUserIds =
            ConversationControl::query()
                ->where(
                    'user_id',
                    auth()->id()
                )
                ->whereNotNull(
                    'assigned_user_id'
                )
                ->distinct()
                ->pluck(
                    'assigned_user_id'
                )
                ->push(
                    auth()->id()
                )
                ->filter()
                ->unique()
                ->values();

        return User::query()
            ->whereIn(
                'id',
                $assignedUserIds
            )
            ->orderBy(
                'name'
            )
            ->get([
                'id',
                'name',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GÜNLÜK SATIŞ MERKEZİ
    |--------------------------------------------------------------------------
    */

    public function getTodayFollowUpsProperty(): Collection
    {
        return $this->dailySalesService()
            ->todayFollowUps(
                auth()->id(),
                10
            );
    }

    public function getUnattended24hProperty(): Collection
    {
        return $this->dailySalesService()
            ->unattendedFor24Hours(
                auth()->id(),
                10
            );
    }

    public function getSilent7dProperty(): Collection
    {
        return $this->dailySalesService()
            ->silentFor7Days(
                auth()->id(),
                10
            );
    }

    public function getClosestToSaleProperty(): Collection
    {
        return $this->dailySalesService()
            ->closestToSale(
                auth()->id(),
                10
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SATIŞ TAHMİNİ / PIPELINE
    |--------------------------------------------------------------------------
    */

    public function getSelectedProbabilityProperty(): int
    {
        $customer =
            $this->selectedCustomer;

        if (! $customer) {
            return 0;
        }

        return $this->forecastService()
            ->probability(
                $customer
            );
    }

    public function getSelectedWeightedValueProperty(): float
    {
        $customer =
            $this->selectedCustomer;

        if (! $customer) {
            return 0;
        }

        return $this->forecastService()
            ->weightedValue(
                $customer
            );
    }

    public function getTotalPipelineValueProperty(): float
    {
        return $this->forecastService()
            ->totalPipelineValue(
                auth()->id()
            );
    }

    public function getWeightedPipelineValueProperty(): float
    {
        return $this->forecastService()
            ->weightedPipelineValue(
                auth()->id()
            );
    }

    public function getValuedOpportunitiesCountProperty(): int
    {
        return $this->forecastService()
            ->valuedOpportunitiesCount(
                auth()->id()
            );
    }

    /*
    |--------------------------------------------------------------------------
    | GERÇEK CİRO
    |--------------------------------------------------------------------------
    */

    public function getTotalWonRevenueProperty(): float
    {
        return $this->revenueService()
            ->totalWonRevenue(
                auth()->id()
            );
    }

    public function getAverageWonValueProperty(): float
    {
        return $this->revenueService()
            ->averageWonValue(
                auth()->id()
            );
    }

    public function getValuedWonSalesCountProperty(): int
    {
        return $this->revenueService()
            ->valuedWonSalesCount(
                auth()->id()
            );
    }

    public function getForecastComparisonProperty(): array
    {
        return $this->revenueService()
            ->forecastComparison(
                auth()->id()
            );
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    public function getTotalCustomersProperty(): int
    {
        return $this->customerQuery()
            ->count();
    }

    public function getHotCustomersProperty(): int
    {
        return $this->customerQuery()
            ->where(
                'lead_temperature',
                'hot'
            )
            ->count();
    }

    public function getProposalCustomersProperty(): int
    {
        return $this->customerQuery()
            ->where(
                'lead_status',
                'proposal'
            )
            ->count();
    }

    public function getWonCustomersProperty(): int
    {
        return $this->customerQuery()
            ->where(
                'lead_status',
                'won'
            )
            ->count();
    }

    public function getFollowUpCustomersProperty(): int
    {
        return $this->customerQuery()
            ->whereNotNull(
                'next_follow_up_at'
            )
            ->where(
                'next_follow_up_at',
                '<=',
                now()->addDay()
            )
            ->count();
    }

    public function getPriorityCustomersProperty(): int
    {
        return $this->customerQuery()
            ->where(
                'lead_score',
                '>=',
                85
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->count();
    }

    public function getRiskCustomersProperty(): int
    {
        return $this->customerQuery()
            ->whereJsonContains(
                'tags',
                'Riskli Lead'
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | LABEL YARDIMCILARI
    |--------------------------------------------------------------------------
    */

    public function statusLabel(
        ?string $status
    ): string {
        return match ($status) {
            'contacted' =>
                'Görüşülüyor',

            'qualified' =>
                'Nitelikli',

            'proposal' =>
                'Teklif',

            'won' =>
                'Kazanıldı',

            'lost' =>
                'Kaybedildi',

            default =>
                'Yeni Lead',
        };
    }

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

    /*
    |--------------------------------------------------------------------------
    | GELEN KUTUSU URL
    |--------------------------------------------------------------------------
    */

    public function inboxUrl(): string
    {
        return url(
            '/admin/conversation-controls'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BAŞLIK
    |--------------------------------------------------------------------------
    */


    public function getLiveKpiTrendsProperty(): array
    {
        $s=now()->subDays(6)->startOfDay();
        $rows=ConversationControl::query()->where('user_id',auth()->id())->where('created_at','>=',$s)->get(['created_at','lead_status','lead_temperature']);
        $won=ConversationControl::query()->where('user_id',auth()->id())->whereNotNull('won_at')->where('won_at','>=',$s)->get(['won_at']);
        $days=collect(range(0,6))->map(fn($i)=>$s->copy()->addDays($i));
        $daily=fn($f)=>$days->map(fn($d)=>$rows->filter(fn(ConversationControl $c)=>$c->created_at?->isSameDay($d)&&$f($c))->count())->all();
        return [
            'new'=>$this->liveSeries($daily(fn(ConversationControl $c)=>($c->lead_status?:'new')==='new')),
            'hot'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->lead_temperature==='hot')),
            'proposal'=>$this->liveSeries($daily(fn(ConversationControl $c)=>$c->lead_status==='proposal')),
            'won'=>$this->liveSeries($days->map(fn($d)=>$won->filter(fn(ConversationControl $c)=>$c->won_at?->isSameDay($d))->count())->all()),
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