<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Models\InsuranceQuoteResult;
use App\Models\User;
use App\Support\InsuranceTenantContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class SigortaYoneticiPaneli extends Page
{
    protected string $view = 'filament.pages.sigorta-yonetici-paneli';
    protected static ?string $title = 'Sigorta Yönetici Paneli';
    protected static ?string $navigationLabel = 'Yönetici';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;
    protected static string|\UnitEnum|null $navigationGroup = 'Sigorta';
    protected static ?int $navigationSort = 23;
    protected static ?string $slug = 'sigorta-yonetici';

    public static function canAccess(): bool
    {
        return app(InsuranceTenantContext::class)->canUseInsurance();
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    private function tenant(): InsuranceTenantContext
    {
        return app(InsuranceTenantContext::class);
    }

    private function tenantCases()
    {
        return $this->tenant()->scope(InsuranceCase::query());
    }

    public function getSummaryProperty(): array
    {
        $today = today();
        $month = now()->startOfMonth();
        $all = $this->tenantCases();

        $createdMonth = (clone $all)->where('created_at', '>=', $month)->count();
        $cohortIssuedMonth = (clone $all)
            ->where('created_at', '>=', $month)
            ->where('status', 'issued')
            ->count();
        $issuedMonth = (clone $all)
            ->where('status', 'issued')
            ->where('issued_at', '>=', $month)
            ->count();

        $avgMinutes = (clone $all)
            ->where('status', 'issued')
            ->where('issued_at', '>=', $month)
            ->whereNotNull('issued_at')
            ->get(['created_at', 'issued_at'])
            ->avg(fn ($case) => $case->created_at && $case->issued_at
                ? $case->created_at->diffInMinutes($case->issued_at)
                : null);

        $caseIds = (clone $all)->where('created_at', '>=', $month)->pluck('id');
        $bestQuotedPremium = $caseIds->isEmpty()
            ? null
            : InsuranceQuoteResult::query()->whereIn('insurance_case_id', $caseIds)->min('premium');

        return [
            'today_inbound' => (clone $all)->whereDate('created_at', $today)->count(),
            'today_issued' => (clone $all)->where('status', 'issued')->whereDate('issued_at', $today)->count(),
            'active' => (clone $all)->active()->count(),
            'attention' => (clone $all)->whereIn('status', ['needs_attention', 'failed'])->count(),
            'month_created' => $createdMonth,
            'month_issued' => $issuedMonth,
            'conversion' => $createdMonth > 0 ? round(($cohortIssuedMonth / $createdMonth) * 100, 1) : 0,
            'avg_minutes' => $avgMinutes !== null ? round((float) $avgMinutes, 1) : null,
            'quoted_value' => $bestQuotedPremium !== null ? (float) $bestQuotedPremium : 0,
        ];
    }

    public function getTeamProperty(): Collection
    {
        $organizationIds = $this->tenant()->organizationIds();
        if ($organizationIds->isEmpty()) return collect();

        $members = User::query()
            ->whereHas('organizations', fn ($q) => $q
                ->whereIn('organizations.id', $organizationIds)
                ->where('organization_user.status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return $members->map(function (User $member): array {
            $base = $this->tenant()->scope(InsuranceCase::query())
                ->where('assigned_user_id', $member->id);

            $active = (clone $base)->active()->count();
            $issuedToday = (clone $base)->where('status', 'issued')->whereDate('issued_at', today())->count();
            $issuedMonth = (clone $base)->where('status', 'issued')->where('issued_at', '>=', now()->startOfMonth())->count();

            return [
                'id' => $member->id,
                'name' => $member->name ?: 'Ekip Üyesi',
                'email' => $member->email,
                'active' => $active,
                'issued_today' => $issuedToday,
                'issued_month' => $issuedMonth,
            ];
        })->sortByDesc('issued_month')->values();
    }

    public function getRecentCasesProperty(): Collection
    {
        return $this->tenant()->scope(InsuranceCase::query())
            ->with('assignedUser')
            ->latest()
            ->limit(8)
            ->get();
    }
}
