<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Models\User;
use App\Support\InsuranceTenantContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class SigortaEkipMerkezi extends Page
{
    protected string $view = 'filament.pages.sigorta-ekip-merkezi';
    protected static ?string $title = 'Sigorta Ekip Merkezi';
    protected static ?string $navigationLabel = 'Ekip Merkezi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|\UnitEnum|null $navigationGroup = 'Sigorta';
    protected static ?int $navigationSort = 22;
    protected static ?string $slug = 'sigorta-ekip';

    public static function canAccess(): bool
    {
        return app(InsuranceTenantContext::class)->canUseInsurance();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private function tenant(): InsuranceTenantContext
    {
        return app(InsuranceTenantContext::class);
    }

    public function getMembersProperty(): Collection
    {
        $organizationIds = $this->tenant()->organizationIds();

        if ($organizationIds->isEmpty()) {
            return collect();
        }

        $members = User::query()
            ->whereHas('organizations', fn ($q) => $q
                ->whereIn('organizations.id', $organizationIds)
                ->where('organization_user.status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $workloadQuery = $this->tenant()->scope(InsuranceCase::query());
        $workload = $workloadQuery
            ->selectRaw('assigned_user_id, COUNT(*) as total')
            ->whereNotNull('assigned_user_id')
            ->whereNotIn('status', ['issued', 'cancelled'])
            ->groupBy('assigned_user_id')
            ->pluck('total', 'assigned_user_id');

        $issuedTodayQuery = $this->tenant()->scope(InsuranceCase::query());
        $issuedToday = $issuedTodayQuery
            ->selectRaw('assigned_user_id, COUNT(*) as total')
            ->whereNotNull('assigned_user_id')
            ->where('status', 'issued')
            ->whereDate('issued_at', today())
            ->groupBy('assigned_user_id')
            ->pluck('total', 'assigned_user_id');

        return $members->map(fn (User $member) => [
            'id' => $member->id,
            'name' => $member->name ?: 'Ekip Üyesi',
            'email' => $member->email,
            'active_cases' => (int) ($workload[$member->id] ?? 0),
            'issued_today' => (int) ($issuedToday[$member->id] ?? 0),
        ]);
    }

    public function getSummaryProperty(): array
    {
        $members = $this->members;
        $unassigned = $this->tenant()->scope(InsuranceCase::query())
            ->active()
            ->whereNull('assigned_user_id')
            ->count();

        return [
            'members' => $members->count(),
            'active_cases' => $members->sum('active_cases'),
            'issued_today' => $members->sum('issued_today'),
            'unassigned' => $unassigned,
        ];
    }
}
