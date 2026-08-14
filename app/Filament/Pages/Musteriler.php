<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Models\User;
use App\Services\CrmActivityService;
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

    /*
    |--------------------------------------------------------------------------
    | FİLTRELER
    |--------------------------------------------------------------------------
    */

    public string $search = '';

    public string $statusFilter = 'all';

    public string $temperatureFilter = 'all';

    public string $channelFilter = 'all';

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
                $this->assignedUserId,

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
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONELLER
    |--------------------------------------------------------------------------
    */

    public function getTeamMembersProperty(): Collection
    {
        return User::query()
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

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}