<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Services\CrmActivityService;
use BackedEnum;
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

    public string $search = '';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

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
        return ConversationControl::query()
            ->with([
                'aiBot',
                'assignedUser',
            ])
            ->where(
                'user_id',
                auth()->id()
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

    protected function getStageCustomers(
        string $status
    ): Collection {
        return $this->baseQuery()
            ->where(
                'lead_status',
                $status
            )
            ->orderByDesc('lead_score')
            ->orderByDesc('last_contact_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | AŞAMA DEĞİŞTİR
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