<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstatePrivateValuationService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakOzelDegerlemeVerisi extends Page
{
    protected string $view = 'filament.pages.emlak-ozel-degerleme-verisi';
    protected static ?string $title = 'Özel Değerleme Verisi';
    protected static ?string $navigationLabel = 'Özel Değerleme';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;
    protected static ?int $navigationSort = 39;
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

        $this->selectedProfileId = $this->candidates->contains('id', $requested)
            ? $requested
            : $this->candidates->first()?->id;
    }

    public function selectProfile(int $profileId): void
    {
        if ($this->candidates->contains('id', $profileId)) {
            $this->selectedProfileId = $profileId;
        }
    }

    public function getSummaryProperty(): array
    {
        return app(RealEstatePrivateValuationService::class)->summary();
    }

    public function getTransactionsProperty(): Collection
    {
        return app(RealEstatePrivateValuationService::class)
            ->safeTransactions()
            ->sortByDesc('closed_at')
            ->take(50)
            ->values();
    }

    public function getCandidatesProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return app(RealEstatePrivateValuationService::class)
            ->candidates()
            ->filter(function (RealEstateProfile $profile) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $data = is_array($profile->data) ? $profile->data : [];

                return str_contains(mb_strtolower(implode(' ', [
                    (string) $profile->conversation?->customer_name,
                    (string) ($data['city'] ?? ''),
                    (string) ($data['district'] ?? ''),
                    (string) ($data['neighborhood'] ?? ''),
                    (string) ($data['property_type'] ?? ''),
                ])), $search);
            })
            ->take(250)
            ->values();
    }

    public function getSelectedProfileProperty(): ?RealEstateProfile
    {
        return $this->selectedProfileId
            ? $this->candidates->firstWhere('id', $this->selectedProfileId)
            : null;
    }

    public function getEstimateProperty(): array
    {
        return $this->selectedProfile
            ? app(RealEstatePrivateValuationService::class)->estimateFor($this->selectedProfile)
            : [];
    }

    public function getHeading(): string
    {
        return '';
    }
}
