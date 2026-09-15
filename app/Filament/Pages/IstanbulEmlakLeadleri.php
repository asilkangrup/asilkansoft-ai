<?php

namespace App\Filament\Pages;

use App\Models\OutreachLead;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IstanbulEmlakLeadleri extends Page
{
    protected string $view = 'filament.pages.istanbul-emlak-leadleri';

    protected static ?string $title = 'İstanbul Emlak';

    protected static ?string $navigationLabel = 'İstanbul Emlak';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 5;

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin
            || strtolower(trim((string) $user->email)) === 'soykan@gmail.com';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    private function baseQuery(): Builder
    {
        $user = Filament::auth()->user();

        return OutreachLead::query()
            ->when(! $user?->is_admin, fn (Builder $query) => $query->where('user_id', $user?->id ?? 0))
            ->where('sector', 'Emlak')
            ->where(function (Builder $query): void {
                $query
                    ->where('notes', 'like', '%Şehir: İstanbul%')
                    ->orWhere('notes', 'like', '%Şehir: Istanbul%');
            });
    }

    public function getLeadsProperty(): Collection
    {
        $search = trim($this->search);

        return $this->baseQuery()
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('company_name', 'ilike', '%'.$search.'%')
                    ->orWhere('phone_e164', 'like', '%'.$search.'%');
            }))
            ->freshFirst()
            ->limit(500)
            ->get();
    }

    public function getStatsProperty(): array
    {
        $base = $this->baseQuery();

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'verified' => (clone $base)->where('whatsapp_status', 'verified')->count(),
            'opened' => (clone $base)->where('status', 'opened')->count(),
            'replied' => (clone $base)->whereIn('status', ['replied', 'ai_active'])->count(),
        ];
    }

    public function getHeading(): string
    {
        return '';
    }
}
