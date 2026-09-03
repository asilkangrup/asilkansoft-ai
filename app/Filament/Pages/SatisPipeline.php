<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Services\CrmActivityService;
use App\Services\OrganizationAccessService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SatisPipeline extends Page
{
    protected string $view = 'filament.pages.satis-pipeline';

    protected static ?string $title = 'Satış Pipeline';

    protected static ?string $navigationLabel = 'Satış Pipeline';

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 32;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    public string $search = '';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

    public static function canAccess(): bool
    {
        $isolation = app(RealEstateIsolationService::class);

        // Keep legacy WAI CRM outside the isolated Emlak workspace.
        if ($isolation->currentOperatorHasAccess()) {
            return false;
        }

        return app(OrganizationAccessService::class)->can('pipeline');
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

    protected function canWritePipeline(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess()
            || $this->accessService()->canWriteCrm();
    }

    protected function scopedConversationQuery(): Builder
    {
        $user = auth()->user();

        $query = ConversationControl::query();

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

        $query->where(
            'organization_id',
            $organization->id
        );

        if ($this->currentRole() === 'sales') {
            $query->where(
                'assigned_user_id',
                $user->id
            );
        }

        return $query;
    }

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

    /*
    |--------------------------------------------------------------------------
    | ANA QUERY
    |--------------------------------------------------------------------------
    */

    protected function baseQuery(): Builder
    {
        return $this->scopedConversationQuery()
            ->with([
                'aiBot',
                'assignedUser',
            ])
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

    public function getPipelineCustomersProperty(): Collection
    {
        return $this->baseQuery()
            ->orderByDesc(
                'lead_score'
            )
            ->orderByDesc(
                'last_contact_at'
            )
            ->orderByDesc(
                'updated_at'
            )
            ->limit(600)
            ->get();
    }

    protected function getStageCustomers(
        string $status
    ): Collection {
        return $this
            ->pipelineCustomers
            ->where(
                'lead_status',
                $status
            )
            ->take(100)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | KOLONLAR
    |--------------------------------------------------------------------------
    */

    public function getNewLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'new'
        );
    }

    public function getContactedLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'contacted'
        );
    }

    public function getQualifiedLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'qualified'
        );
    }

    public function getProposalLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'proposal'
        );
    }

    public function getWonLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'won'
        );
    }

    public function getLostLeadsProperty(): Collection
    {
        return $this->getStageCustomers(
            'lost'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AÅAMA DEÄÄ°ÅTÄ°R
    |--------------------------------------------------------------------------
    */

    public function moveLead(
        int $customerId,
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

        if (! $this->canWritePipeline()) {
            abort(403);
        }

        $customer = $this->scopedConversationQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $oldStatus =
            $customer->lead_status
            ?: 'new';

        if ($oldStatus === $status) {
            return;
        }

        $data = [
            'lead_status' =>
                $status,
        ];

        if ($status === 'won') {
            $data['won_at'] =
                $customer->won_at
                    ?: now();

            $data['lost_at'] =
                null;

            $data['lost_reason'] =
                null;
        }

        if ($status === 'lost') {
            $data['lost_at'] =
                $customer->lost_at
                    ?: now();

            $data['won_at'] =
                null;
        }

        if (
            ! in_array(
                $status,
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

        $customer->update(
            $data
        );

        $customer->refresh();

        /*
        |--------------------------------------------------------------------------
        | CRM AKTİVİTE KAYDI
        |--------------------------------------------------------------------------
        |
        | Kart sürükle-bırak veya hızlı aksiyon ile başka kolona taşındığında
        | müşteri geçmişine satış aşaması değişikliği yazılır.
        |
        */

        $this->activityService()
            ->leadStatusChanged(
                conversation: $customer,
                oldStatus: $oldStatus,
                newStatus: $customer->lead_status ?: 'new',
                performedBy: auth()->user(),
            );

        if (
            $oldStatus !== 'won'
            && $customer->lead_status === 'won'
        ) {
            $this->activityService()
                ->won(
                    conversation: $customer,
                    performedBy: auth()->user(),
                );
        }

        if (
            $oldStatus !== 'lost'
            && $customer->lead_status === 'lost'
        ) {
            $this->activityService()
                ->lost(
                    conversation: $customer,
                    reason: $customer->lost_reason,
                    performedBy: auth()->user(),
                );
        }

        $this->dispatch(
            'pipeline-updated',
            customerId: $customer->id,
            status: $status,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD PUANI
    |--------------------------------------------------------------------------
    */

    public function setScore(
        int $customerId,
        int $score
    ): void {
        if (! $this->canWritePipeline()) {
            abort(403);
        }

        $customer = $this->scopedConversationQuery()
            ->whereKey(
                $customerId
            )
            ->first();

        if (! $customer) {
            return;
        }

        $oldScore =
            (int) $customer->lead_score;

        $oldTemperature =
            $customer->lead_temperature
            ?: 'cold';

        $customer->leadSkoruGuncelle(
            $score
        );

        $customer->refresh();

        $activityService =
            $this->activityService();

        $activityService->leadScoreChanged(
            conversation: $customer,
            oldScore: $oldScore,
            newScore: (int) $customer->lead_score,
            performedBy: auth()->user(),
        );

        $activityService->temperatureChanged(
            conversation: $customer,
            oldTemperature: $oldTemperature,
            newTemperature: $customer->lead_temperature ?: 'cold',
            performedBy: auth()->user(),
        );

        $this->dispatch(
            'pipeline-updated',
            customerId: $customer->id,
            status: $customer->lead_status ?: 'new',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KPI
    |--------------------------------------------------------------------------
    */

    public function getTotalOpenLeadsProperty(): int
    {
        return $this->baseQuery()
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->count();
    }

    public function getHotLeadsProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'lead_temperature',
                'hot'
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

    public function getProposalCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'lead_status',
                'proposal'
            )
            ->count();
    }

    public function getWonCountProperty(): int
    {
        return $this->baseQuery()
            ->where(
                'lead_status',
                'won'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | GERÇEK ZAMANLI KPI MİNİ GRAFİKLERİ
    |--------------------------------------------------------------------------
    |
    | Grafikler dekoratif değildir.
    |
    | Açık Fırsat:
    |   Son 7 günde oluşturulmuş ve şu anda açık olan fırsatlar.
    |
    | Sıcak Lead:
    |   Son 7 günde oluşturulmuş ve şu anda sıcak olan açık lead'ler.
    |
    | Teklif:
    |   Son 7 günde oluşturulmuş ve şu anda teklif aşamasında olan lead'ler.
    |
    | Kazanılan:
    |   Son 7 günde gerçekten kazanılmış satışlar (won_at).
    |
    | Sayfa wire:poll ile 60 saniyede bir tekrar render edildiği için grafik
    | verileri de veritabanından yeniden okunur.
    |
    */

    public function getKpiTrendDataProperty(): array
    {
        $start =
            now()
                ->subDays(6)
                ->startOfDay();

        $days =
            collect(
                range(
                    0,
                    6
                )
            )
                ->map(
                    fn (int $offset): Carbon =>
                        $start
                            ->copy()
                            ->addDays(
                                $offset
                            )
                );

        /*
        |--------------------------------------------------------------------------
        | SON 7 GÃœNDE OLUÅTURULAN MEVCUT LEAD'LER
        |--------------------------------------------------------------------------
        */

        $recentCustomers =
            $this->baseQuery()
                ->where(
                    'created_at',
                    '>=',
                    $start
                )
                ->get([
                    'id',
                    'created_at',
                    'lead_status',
                    'lead_temperature',
                ]);

        /*
        |--------------------------------------------------------------------------
        | SON 7 GÜNDE KAZANILANLAR
        |--------------------------------------------------------------------------
        */

        $recentWon =
            $this->baseQuery()
                ->where(
                    'lead_status',
                    'won'
                )
                ->whereNotNull(
                    'won_at'
                )
                ->where(
                    'won_at',
                    '>=',
                    $start
                )
                ->get([
                    'id',
                    'won_at',
                ]);

        $openValues =
            $days
                ->map(
                    fn (Carbon $day): int =>
                        $recentCustomers
                            ->filter(
                                fn (ConversationControl $customer): bool =>
                                    ! in_array(
                                        $customer->lead_status,
                                        [
                                            'won',
                                            'lost',
                                        ],
                                        true
                                    )
                                    && $customer
                                        ->created_at
                                        ?->isSameDay(
                                            $day
                                        )
                            )
                            ->count()
                )
                ->values()
                ->all();

        $hotValues =
            $days
                ->map(
                    fn (Carbon $day): int =>
                        $recentCustomers
                            ->filter(
                                fn (ConversationControl $customer): bool =>
                                    $customer->lead_temperature === 'hot'
                                    && ! in_array(
                                        $customer->lead_status,
                                        [
                                            'won',
                                            'lost',
                                        ],
                                        true
                                    )
                                    && $customer
                                        ->created_at
                                        ?->isSameDay(
                                            $day
                                        )
                            )
                            ->count()
                )
                ->values()
                ->all();

        $proposalValues =
            $days
                ->map(
                    fn (Carbon $day): int =>
                        $recentCustomers
                            ->filter(
                                fn (ConversationControl $customer): bool =>
                                    $customer->lead_status === 'proposal'
                                    && $customer
                                        ->created_at
                                        ?->isSameDay(
                                            $day
                                        )
                            )
                            ->count()
                )
                ->values()
                ->all();

        $wonValues =
            $days
                ->map(
                    fn (Carbon $day): int =>
                        $recentWon
                            ->filter(
                                fn (ConversationControl $customer): bool =>
                                    $customer
                                        ->won_at
                                        ?->isSameDay(
                                            $day
                                        )
                            )
                            ->count()
                )
                ->values()
                ->all();

        $labels =
            $days
                ->map(
                    fn (Carbon $day): string =>
                        $day->format(
                            'd.m'
                        )
                )
                ->values()
                ->all();

        return [
            'open' =>
                $this->makeTrendSeries(
                    $openValues,
                    $labels
                ),

            'hot' =>
                $this->makeTrendSeries(
                    $hotValues,
                    $labels
                ),

            'proposal' =>
                $this->makeTrendSeries(
                    $proposalValues,
                    $labels
                ),

            'won' =>
                $this->makeTrendSeries(
                    $wonValues,
                    $labels
                ),
        ];
    }

    protected function makeTrendSeries(
        array $values,
        array $labels
    ): array {
        $values =
            array_map(
                fn ($value): int =>
                    max(
                        0,
                        (int) $value
                    ),
                $values
            );

        $lastIndex =
            count(
                $values
            )
            - 1;

        $today =
            $lastIndex >= 0
                ? (
                    $values[
                        $lastIndex
                    ]
                    ?? 0
                )
                : 0;

        $yesterday =
            $lastIndex >= 1
                ? (
                    $values[
                        $lastIndex - 1
                    ]
                    ?? 0
                )
                : 0;

        $trend =
            $this->trendChange(
                $today,
                $yesterday
            );

        return [
            'values' =>
                $values,

            'labels' =>
                $labels,

            'points' =>
                $this->sparklinePoints(
                    $values
                ),

            'today' =>
                $today,

            'seven_day_total' =>
                array_sum(
                    $values
                ),

            'trend_label' =>
                $trend['label'],

            'trend_direction' =>
                $trend['direction'],
        ];
    }

    protected function trendChange(
        int $today,
        int $yesterday
    ): array {
        if (
            $today === 0
            && $yesterday === 0
        ) {
            return [
                'label' =>
                    '0%',

                'direction' =>
                    'flat',
            ];
        }

        if ($yesterday === 0) {
            return [
                'label' =>
                    $today > 0
                        ? '+100%'
                        : '0%',

                'direction' =>
                    $today > 0
                        ? 'up'
                        : 'flat',
            ];
        }

        $percentage =
            (int) round(
                (
                    (
                        $today
                        - $yesterday
                    )
                    / $yesterday
                )
                * 100
            );

        return [
            'label' =>
                (
                    $percentage > 0
                        ? '+'
                        : ''
                )
                .$percentage
                .'%',

            'direction' =>
                match (true) {
                    $percentage > 0 =>
                        'up',

                    $percentage < 0 =>
                        'down',

                    default =>
                        'flat',
                },
        ];
    }

    protected function sparklinePoints(
        array $values,
        int $width = 108,
        int $height = 42
    ): string {
        $count =
            count(
                $values
            );

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return '4,21 104,21';
        }

        $min =
            min(
                $values
            );

        $max =
            max(
                $values
            );

        $isFlat =
            $min === $max;

        $range =
            max(
                1,
                $max - $min
            );

        $paddingX =
            4;

        $paddingY =
            5;

        $usableWidth =
            $width
            - (
                $paddingX
                * 2
            );

        $usableHeight =
            $height
            - (
                $paddingY
                * 2
            );

        return collect(
            $values
        )
            ->map(
                function (
                    int $value,
                    int $index
                ) use (
                    $count,
                    $paddingX,
                    $paddingY,
                    $usableWidth,
                    $usableHeight,
                    $min,
                    $range,
                    $isFlat
                ): string {
                    $x =
                        $paddingX
                        + (
                            $index
                            * (
                                $usableWidth
                                / (
                                    $count - 1
                                )
                            )
                        );

                    if ($isFlat) {
                        $y =
                            $paddingY
                            + (
                                $usableHeight
                                / 2
                            );
                    } else {
                        $normalized =
                            (
                                $value
                                - $min
                            )
                            / $range;

                        $y =
                            $paddingY
                            + (
                                (
                                    1
                                    - $normalized
                                )
                                * $usableHeight
                            );
                    }

                    return round(
                        $x,
                        1
                    )
                        .','
                        .round(
                            $y,
                            1
                        );
                }
            )
            ->implode(
                ' '
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

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}