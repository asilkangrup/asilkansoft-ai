<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Models\User;
use App\Services\CrmActivityService;
use App\Services\CrmConversationSummaryService;
use App\Services\CrmDailySalesService;
use App\Services\CrmForecastService;
use App\Services\CrmRevenueService;
use App\Services\RealEstateIsolationService;
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

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_admin
            || app(RealEstateIsolationService::class)->currentOperatorHasAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function activityService(): CrmActivityService
    {
        return app(CrmActivityService::class);
    }

    protected function dailySalesService(): CrmDailySalesService
    {
        return app(CrmDailySalesService::class);
    }

    protected function forecastService(): CrmForecastService
    {
        return app(CrmForecastService::class);
    }

    protected function revenueService(): CrmRevenueService
    {
        return app(CrmRevenueService::class);
    }

    public function refreshAiSummary(): void
    {
        $customer = $this->selectedCustomer;

        if (! $customer) {
            return;
        }

        try {
            app(CrmConversationSummaryService::class)->updateIfNeeded(
                conversation: $customer,
                force: true
            );

            $this->selectCustomer($customer->id);
            $this->dispatch('crm-ai-summary-refreshed');
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('crm-ai-summary-failed');
        }
    }

    public string $search = '';
    public string $statusFilter = 'all';
    public string $temperatureFilter = 'all';
    public string $channelFilter = 'all';
    public string $opportunityFilter = 'all';
    public ?int $selectedCustomerId = null;
    public string $activityFilter = 'all';
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

    public function mount(): void
    {
        $customerId = request()->integer('customer');

        if ($customerId > 0) {
            $customer = $this->customerQuery()->whereKey($customerId)->first();

            if ($customer) {
                $this->selectCustomer($customer->id);
                return;
            }
        }

        $firstCustomer = $this->customerQuery()->latest('updated_at')->first();

        if ($firstCustomer) {
            $this->selectCustomer($firstCustomer->id);
        }
    }

    protected function customerQuery(): Builder
    {
        $query = ConversationControl::query()->with(['aiBot', 'assignedUser']);

        if (app(RealEstateIsolationService::class)->currentOperatorHasAccess()) {
            return $query
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID);
        }

        return $query->where('user_id', auth()->id());
    }

    public function getCustomersProperty(): Collection
    {
        return $this->customerQuery()
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    $search = trim($this->search);
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('whatsapp_number', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->statusFilter !== 'all',
                fn (Builder $query) => $query->where('lead_status', $this->statusFilter)
            )
            ->when(
                $this->temperatureFilter !== 'all',
                fn (Builder $query) => $query->where('lead_temperature', $this->temperatureFilter)
            )
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->opportunityFilter !== 'all',
                function (Builder $query): void {
                    match ($this->opportunityFilter) {
                        'priority' => $query
                            ->where('lead_score', '>=', 85)
                            ->whereNotIn('lead_status', ['won', 'lost']),
                        'risk' => $query
                            ->whereJsonContains('tags', 'Riskli Lead')
                            ->whereNotIn('lead_status', ['won', 'lost']),
                        'price_objection' => $query
                            ->whereJsonContains('tags', 'Fiyat İtirazı')
                            ->whereNotIn('lead_status', ['won', 'lost']),
                        'follow_up' => $query
                            ->whereNotNull('next_follow_up_at')
                            ->whereNotIn('lead_status', ['won', 'lost']),
                        default => null,
                    };
                }
            )
            ->orderByRaw("CASE WHEN lead_temperature = 'hot' THEN 1 WHEN lead_temperature = 'warm' THEN 2 ELSE 3 END")
            ->orderByDesc('lead_score')
            ->orderByDesc('last_contact_at')
            ->orderByDesc('updated_at')
            ->limit(250)
            ->get();
    }

    public function getSelectedCustomerProperty(): ?ConversationControl
    {
        if (! $this->selectedCustomerId) {
            return null;
        }

        return $this->customerQuery()->whereKey($this->selectedCustomerId)->first();
    }

    public function getSelectedActivitiesProperty(): Collection
    {
        $customer = $this->selectedCustomer;

        if (! $customer) {
            return collect();
        }

        $query = $customer->activities()->with(['performedByUser']);

        match ($this->activityFilter) {
            'ai' => $query->whereIn('type', ['ai_score', 'ai_status', 'ai_action']),
            'staff' => $query->whereNotNull('performed_by_user_id'),
            'sales' => $query->whereIn('type', ['lead_status', 'won', 'lost', 'lead_score', 'lead_temperature']),
            'follow_up' => $query->where('type', 'follow_up'),
            'notes' => $query->where('type', 'note'),
            'control' => $query->whereIn('type', ['human_takeover', 'ai_release', 'assignment']),
            default => null,
        };

        return $query->limit(50)->get();
    }

    public function setActivityFilter(string $filter): void
    {
        $allowed = ['all', 'ai', 'staff', 'sales', 'follow_up', 'notes', 'control'];

        if (! in_array($filter, $allowed, true)) {
            return;
        }

        $this->activityFilter = $filter;
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = $this->customerQuery()->whereKey($customerId)->first();

        if (! $customer) {
            return;
        }

        $this->selectedCustomerId = $customer->id;
        $this->customerName = (string) $customer->customer_name;
        $this->customerEmail = (string) $customer->customer_email;
        $this->companyName = (string) $customer->company_name;
        $this->leadStatus = $customer->lead_status ?: 'new';
        $this->leadScore = (int) $customer->lead_score;
        $this->notes = (string) $customer->notes;
        $this->assignedUserId = $customer->assigned_user_id ? (int) $customer->assigned_user_id : null;
        $this->nextFollowUpAt = $customer->next_follow_up_at ? $customer->next_follow_up_at->format('Y-m-d\TH:i') : '';
        $this->lostReason = (string) $customer->lost_reason;
        $this->estimatedValue = $customer->estimated_value !== null ? number_format((float) $customer->estimated_value, 2, '.', '') : '';
        $this->actualValue = $customer->actual_value !== null ? number_format((float) $customer->actual_value, 2, '.', '') : '';
    }

    public function saveCustomer(): void
    {
        $activityService = $this->activityService();
        $customer = $this->selectedCustomer;

        if (! $customer) {
            return;
        }

        $oldStatus = $customer->lead_status ?: 'new';
        $oldScore = (int) $customer->lead_score;
        $oldTemperature = $customer->lead_temperature ?: 'cold';
        $oldAssignedUserId = $customer->assigned_user_id ? (int) $customer->assigned_user_id : null;
        $oldNotes = (string) $customer->notes;
        $oldFollowUp = $customer->next_follow_up_at ? $customer->next_follow_up_at->copy() : null;

        $score = max(0, min(100, (int) $this->leadScore));
        $temperature = match (true) {
            $score >= 70 => 'hot',
            $score >= 40 => 'warm',
            default => 'cold',
        };

        $data = [
            'customer_name' => trim($this->customerName) ?: null,
            'customer_email' => trim($this->customerEmail) ?: null,
            'company_name' => trim($this->companyName) ?: null,
            'lead_status' => $this->leadStatus,
            'lead_score' => $score,
            'lead_temperature' => $temperature,
            'assigned_user_id' => $this->assignedUserId,
            'notes' => trim($this->notes) ?: null,
            'next_follow_up_at' => $this->nextFollowUpAt !== '' ? $this->nextFollowUpAt : null,
            'lost_reason' => $this->leadStatus === 'lost' ? (trim($this->lostReason) ?: null) : null,
        ];

        if ($this->leadStatus === 'won' && ! $customer->won_at) {
            $data['won_at'] = now();
            $data['lost_at'] = null;
        }

        if ($this->leadStatus === 'lost' && ! $customer->lost_at) {
            $data['lost_at'] = now();
            $data['won_at'] = null;
        }

        if (! in_array($this->leadStatus, ['won', 'lost'], true)) {
            $data['won_at'] = null;
            $data['lost_at'] = null;
            $data['lost_reason'] = null;
        }

        $customer->update($data);

        $estimatedValue = $this->estimatedValue !== ''
            ? max(0, (float) str_replace(',', '.', $this->estimatedValue))
            : null;
        $actualValue = $this->actualValue !== ''
            ? max(0, (float) str_replace(',', '.', $this->actualValue))
            : null;

        $customer->forceFill([
            'estimated_value' => $estimatedValue,
            'actual_value' => $customer->lead_status === 'won' ? $actualValue : null,
        ])->save();

        $customer->refresh();
        $performedBy = auth()->user();

        $activityService->leadStatusChanged(
            conversation: $customer,
            oldStatus: $oldStatus,
            newStatus: $customer->lead_status ?: 'new',
            performedBy: $performedBy,
        );

        if ($oldStatus !== 'won' && $customer->lead_status === 'won') {
            $activityService->won(conversation: $customer, performedBy: $performedBy);
        }

        if ($oldStatus !== 'lost' && $customer->lead_status === 'lost') {
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
            newUserId: $customer->assigned_user_id ? (int) $customer->assigned_user_id : null,
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

        $this->selectCustomer($customer->id);
        $this->dispatch('crm-customer-saved');
    }

    public function setLeadStatus(string $status): void
    {
        $allowed = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];

        if (! in_array($status, $allowed, true)) {
            return;
        }

        $this->leadStatus = $status;
        $this->saveCustomer();
    }

    public function setLeadScore(int $score): void
    {
        $this->leadScore = max(0, min(100, $score));
        $this->saveCustomer();
    }

    public function assignToMe(): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->assignedUserId = auth()->id();
        $this->saveCustomer();
    }

    public function clearAssignment(): void
    {
        $this->assignedUserId = null;
        $this->saveCustomer();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->temperatureFilter = 'all';
        $this->channelFilter = 'all';
        $this->opportunityFilter = 'all';
    }

    public function getTeamMembersProperty(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    public function getTodayFollowUpsProperty(): Collection
    {
        return $this->dailySalesService()->todayFollowUps(auth()->id(), 10);
    }

    public function getUnattended24hProperty(): Collection
    {
        return $this->dailySalesService()->unattendedFor24Hours(auth()->id(), 10);
    }

    public function getSilent7dProperty(): Collection
    {
        return $this->dailySalesService()->silentFor7Days(auth()->id(), 10);
    }

    public function getClosestToSaleProperty(): Collection
    {
        return $this->dailySalesService()->closestToSale(auth()->id(), 10);
    }

    public function getSelectedProbabilityProperty(): int
    {
        $customer = $this->selectedCustomer;
        return $customer ? $this->forecastService()->probability($customer) : 0;
    }

    public function getSelectedWeightedValueProperty(): float
    {
        $customer = $this->selectedCustomer;
        return $customer ? $this->forecastService()->weightedValue($customer) : 0;
    }

    public function getTotalPipelineValueProperty(): float
    {
        return $this->forecastService()->totalPipelineValue(auth()->id());
    }

    public function getWeightedPipelineValueProperty(): float
    {
        return $this->forecastService()->weightedPipelineValue(auth()->id());
    }

    public function getValuedOpportunitiesCountProperty(): int
    {
        return $this->forecastService()->valuedOpportunitiesCount(auth()->id());
    }

    public function getTotalWonRevenueProperty(): float
    {
        return $this->revenueService()->totalWonRevenue(auth()->id());
    }

    public function getAverageWonValueProperty(): float
    {
        return $this->revenueService()->averageWonValue(auth()->id());
    }

    public function getValuedWonSalesCountProperty(): int
    {
        return $this->revenueService()->valuedWonSalesCount(auth()->id());
    }

    public function getForecastComparisonProperty(): array
    {
        return $this->revenueService()->forecastComparison(auth()->id());
    }

    public function getTotalCustomersProperty(): int
    {
        return $this->customerQuery()->count();
    }

    public function getHotCustomersProperty(): int
    {
        return $this->customerQuery()->where('lead_temperature', 'hot')->count();
    }

    public function getProposalCustomersProperty(): int
    {
        return $this->customerQuery()->where('lead_status', 'proposal')->count();
    }

    public function getWonCustomersProperty(): int
    {
        return $this->customerQuery()->where('lead_status', 'won')->count();
    }

    public function getFollowUpCustomersProperty(): int
    {
        return $this->customerQuery()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->addDay())
            ->count();
    }

    public function getPriorityCustomersProperty(): int
    {
        return $this->customerQuery()
            ->where('lead_score', '>=', 85)
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->count();
    }

    public function getRiskCustomersProperty(): int
    {
        return $this->customerQuery()
            ->whereJsonContains('tags', 'Riskli Lead')
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->count();
    }

    /**
     * Existing CRM blade expects four small sparkline series. Keep the
     * computation tenant-scoped through customerQuery() so the Emlak account
     * can never pull generic WAI customer data into its CRM dashboard.
     *
     * The legacy blade internally names the priority/risk slots `proposal`
     * and `won`; these aliases intentionally preserve that contract while the
     * values themselves represent the cards actually displayed.
     */
    public function getLiveKpiTrendsProperty(): array
    {
        $start = now()->subDays(6)->startOfDay();
        $rows = $this->customerQuery()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'lead_status', 'lead_temperature', 'lead_score', 'tags']);
        $days = collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset));

        $daily = fn (callable $filter): array => $days
            ->map(fn ($day): int => $rows
                ->filter(fn (ConversationControl $customer): bool =>
                    $customer->created_at?->isSameDay($day) && $filter($customer)
                )
                ->count())
            ->all();

        $isOpen = fn (ConversationControl $customer): bool =>
            ! in_array($customer->lead_status, ['won', 'lost'], true);

        return [
            'new' => $this->liveSeries($daily(fn (ConversationControl $customer): bool => true)),
            'hot' => $this->liveSeries($daily(fn (ConversationControl $customer): bool =>
                $customer->lead_temperature === 'hot' && $isOpen($customer)
            )),
            'proposal' => $this->liveSeries($daily(fn (ConversationControl $customer): bool =>
                (int) $customer->lead_score >= 85 && $isOpen($customer)
            )),
            'won' => $this->liveSeries($daily(fn (ConversationControl $customer): bool =>
                in_array('Riskli Lead', (array) $customer->tags, true) && $isOpen($customer)
            )),
        ];
    }

    protected function liveSeries(array $values): array
    {
        while (count($values) < 7) {
            array_unshift($values, 0);
        }

        $values = array_slice(array_map('intval', $values), -7);
        $today = $values[6] ?? 0;
        $yesterday = $values[5] ?? 0;

        if ($today === 0 && $yesterday === 0) {
            $percent = 0;
            $direction = 'flat';
        } elseif ($yesterday === 0) {
            $percent = $today > 0 ? 100 : 0;
            $direction = $today > 0 ? 'up' : 'flat';
        } else {
            $percent = (int) round((($today - $yesterday) / $yesterday) * 100);
            $direction = $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat');
        }

        $min = min($values);
        $max = max($values);
        $flat = $min === $max;
        $range = max(1, $max - $min);
        $points = collect($values)->map(function (int $value, int $index) use ($min, $range, $flat): string {
            $x = 4 + $index * (100 / 6);
            $y = $flat ? 21 : 5 + (1 - (($value - $min) / $range)) * 32;
            return round($x, 1).','.round($y, 1);
        })->implode(' ');

        return [
            'points' => $points,
            'trend_label' => ($percent > 0 ? '+' : '').$percent.'%',
            'trend_direction' => $direction,
        ];
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'contacted' => 'Görüşülüyor',
            'qualified' => 'Nitelikli',
            'proposal' => 'Teklif',
            'won' => 'Kazanıldı',
            'lost' => 'Kaybedildi',
            default => 'Yeni Lead',
        };
    }

    public function temperatureLabel(?string $temperature): string
    {
        return match ($temperature) {
            'hot' => 'Sıcak',
            'warm' => 'Ilık',
            default => 'Soğuk',
        };
    }

    public function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'web' => 'Web',
            default => 'WhatsApp',
        };
    }

    public function inboxUrl(): string
    {
        return url('/admin/conversation-controls');
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
