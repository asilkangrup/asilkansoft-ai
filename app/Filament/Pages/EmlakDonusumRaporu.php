<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateConversionReportService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakDonusumRaporu extends Page
{
    protected string $view = 'filament.pages.emlak-donusum-raporu';
    protected static ?string $title = 'Reklam ve Satış Dönüşümü';
    protected static ?string $navigationLabel = 'Dönüşüm Raporu';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;
    protected static ?int $navigationSort = 38;
    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public string $from = '';
    public string $until = '';
    public ?int $selectedProfileId = null;
    public string $source = 'whatsapp';
    public string $campaign = '';
    public string $adSet = '';
    public string $creative = '';

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
        $this->until = now()->toDateString();
        $this->from = now()->subDays(29)->toDateString();
        $this->selectedProfileId = $this->candidates->first()?->id;
        $this->loadAttribution();
    }

    public function usePeriod(int $days): void
    {
        abort_unless(in_array($days, [7, 30, 90], true), 422);

        $this->until = now()->toDateString();
        $this->from = now()->subDays($days - 1)->toDateString();
    }

    public function selectProfile(int $profileId): void
    {
        if (! $this->candidates->contains('id', $profileId)) {
            return;
        }

        $this->selectedProfileId = $profileId;
        $this->loadAttribution();
    }

    public function saveAttribution(): void
    {
        abort_unless(static::canAccess() && $this->selectedProfile, 403);

        app(RealEstateConversionReportService::class)->updateAttribution(
            $this->selectedProfile,
            [
                'source' => $this->source,
                'campaign' => $this->campaign,
                'ad_set' => $this->adSet,
                'creative' => $this->creative,
            ],
            auth()->user(),
        );

        $this->loadAttribution();
        Notification::make()->title('Reklam kaynağı güncellendi')->success()->send();
    }

    public function getReportProperty(): array
    {
        return app(RealEstateConversionReportService::class)->report($this->from, $this->until);
    }

    public function getSourcesProperty(): array
    {
        return RealEstateConversionReportService::SOURCES;
    }

    public function getCandidatesProperty(): Collection
    {
        return app(RealEstateConversionReportService::class)
            ->attributionCandidates()
            ->take(250)
            ->values();
    }

    public function getSelectedProfileProperty(): ?RealEstateProfile
    {
        if (! $this->selectedProfileId) {
            return null;
        }

        return $this->candidates->firstWhere('id', $this->selectedProfileId);
    }

    private function loadAttribution(): void
    {
        $profile = $this->selectedProfile;
        $stored = $profile
            ? app(RealEstateConversionReportService::class)->storedAttribution($profile)
            : [];

        $this->source = (string) ($stored['source'] ?? $this->defaultSource($profile));
        $this->campaign = (string) ($stored['campaign'] ?? '');
        $this->adSet = (string) ($stored['ad_set'] ?? '');
        $this->creative = (string) ($stored['creative'] ?? '');
    }

    private function defaultSource(?RealEstateProfile $profile): string
    {
        $channel = mb_strtolower((string) ($profile?->conversation?->channel ?? 'whatsapp'));

        return match ($channel) {
            'instagram' => 'instagram',
            'facebook' => 'facebook',
            'web' => 'web',
            default => 'whatsapp',
        };
    }

    public function getHeading(): string
    {
        return '';
    }
}
