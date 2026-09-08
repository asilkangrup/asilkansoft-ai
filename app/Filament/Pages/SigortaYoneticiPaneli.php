<?php

namespace App\Filament\Pages;

use App\Models\InsuranceCase;
use App\Models\InsuranceQuoteResult;
use App\Models\User;
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
        $user = auth()->user();
        return (bool) $user?->is_admin || strtolower((string) $user?->email) === 'dogustopcu@gmail.com';
    }

    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    public function getSummaryProperty(): array
    {
        $today = today();
        $month = now()->startOfMonth();
        $all = InsuranceCase::query();
        $issuedMonth = (clone $all)->where('status','issued')->where('updated_at','>=',$month)->count();
        $createdMonth = (clone $all)->where('created_at','>=',$month)->count();

        $avgMinutes = InsuranceCase::query()
            ->where('status','issued')
            ->where('updated_at','>=',$month)
            ->get(['created_at','updated_at'])
            ->avg(fn ($case) => $case->created_at && $case->updated_at ? $case->created_at->diffInMinutes($case->updated_at) : null);

        return [
            'today_inbound' => (clone $all)->whereDate('created_at',$today)->count(),
            'today_issued' => (clone $all)->where('status','issued')->whereDate('updated_at',$today)->count(),
            'active' => (clone $all)->active()->count(),
            'attention' => (clone $all)->whereIn('status',['needs_attention','failed'])->count(),
            'month_created' => $createdMonth,
            'month_issued' => $issuedMonth,
            'conversion' => $createdMonth > 0 ? round(($issuedMonth / $createdMonth) * 100, 1) : 0,
            'avg_minutes' => $avgMinutes !== null ? round((float) $avgMinutes, 1) : null,
            'quoted_value' => (float) InsuranceQuoteResult::query()->where('created_at','>=',$month)->min('premium'),
        ];
    }

    public function getTeamProperty(): Collection
    {
        $organizationIds = auth()->user()?->activeOrganizations()->pluck('organizations.id') ?? collect();
        if ($organizationIds->isEmpty()) return collect();

        $members = User::query()
            ->whereHas('organizations', fn ($q) => $q->whereIn('organizations.id',$organizationIds)->where('organization_user.status','active'))
            ->orderBy('name')
            ->get(['id','name','email']);

        return $members->map(function (User $member): array {
            $active = InsuranceCase::query()->where('assigned_user_id',$member->id)->active()->count();
            $issuedToday = InsuranceCase::query()->where('assigned_user_id',$member->id)->where('status','issued')->whereDate('updated_at',today())->count();
            $issuedMonth = InsuranceCase::query()->where('assigned_user_id',$member->id)->where('status','issued')->where('updated_at','>=',now()->startOfMonth())->count();
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
        return InsuranceCase::query()->with('assignedUser')->latest()->limit(8)->get();
    }
}
