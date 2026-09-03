<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateInvestorPresentationService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakYatirimciSunumlari extends Page
{
    protected string $view = 'filament.pages.emlak-yatirimci-sunumlari';
    protected static ?string $title = 'Yatırımcı Sunumları';
    protected static ?string $navigationLabel = 'Yatırımcı Sunumları';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;
    protected static ?int $navigationSort = 34;
    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public ?int $selectedProfileId = null;
    public string $search = '';

    public static function canAccess(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $requested = request()->integer('profile');

        if ($requested > 0 && $this->profileQuery()->whereKey($requested)->exists()) {
            $this->selectedProfileId = $requested;
            return;
        }

        $this->selectedProfileId = $this->profileQuery()->latest('updated_at')->value('id');
    }

    public function selectProfile(int $profileId): void
    {
        if ($this->profileQuery()->whereKey($profileId)->exists()) {
            $this->selectedProfileId = $profileId;
        }
    }

    public function getProfilesProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $this->profileQuery()
            ->with('conversation')
            ->latest('updated_at')
            ->limit(250)
            ->get()
            ->filter(function (RealEstateProfile $profile) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $data = is_array($profile->data) ? $profile->data : [];

                return str_contains(mb_strtolower(implode(' ', [
                    (string) ($profile->conversation?->customer_name ?? ''),
                    (string) data_get($data, 'city', ''),
                    (string) data_get($data, 'district', ''),
                    (string) data_get($data, 'neighborhood', ''),
                    (string) data_get($data, 'location', ''),
                    (string) data_get($data, 'property_type', ''),
                ])), $search);
            })
            ->values();
    }

    public function getSelectedProfileProperty(): ?RealEstateProfile
    {
        if (! $this->selectedProfileId) {
            return null;
        }

        return $this->profileQuery()
            ->with('conversation')
            ->whereKey($this->selectedProfileId)
            ->first();
    }

    public function getPresentationProperty(): array
    {
        $profile = $this->selectedProfile;

        return $profile
            ? app(RealEstateInvestorPresentationService::class)->build($profile)
            : [];
    }

    private function profileQuery()
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller');
    }

    public function getHeading(): string
    {
        return '';
    }
}
