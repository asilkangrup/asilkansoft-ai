<?php

namespace App\Filament\Pages;

use App\Jobs\RunRealEstateOperatorValuationResearch;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOperatorValuationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakDegerlemeKuyrugu extends Page
{
    protected string $view = 'filament.pages.emlak-degerleme-kuyrugu';

    protected static ?string $title = 'Değerleme Kuyruğu';

    protected static ?string $navigationLabel = 'Değerleme Kuyruğu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 38;

    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public string $search = '';

    public static function canAccess(): bool
    {
        return app(RealEstateIsolationService::class)->currentOperatorHasAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSummaryProperty(): array
    {
        return app(RealEstateOperatorValuationService::class)->summary();
    }

    public function getItemsProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));
        $items = app(RealEstateOperatorValuationService::class)->queueItems(100);

        if ($search === '') {
            return $items;
        }

        $profiles = $this->profiles;

        return $items
            ->filter(function (array $item) use ($search, $profiles): bool {
                $profile = $profiles->get((int) ($item['profile_id'] ?? 0));
                $data = is_array($profile?->data) ? $profile->data : [];

                $haystack = mb_strtolower(implode(' ', [
                    (string) $profile?->conversation?->customer_name,
                    (string) ($data['city'] ?? ''),
                    (string) ($data['district'] ?? ''),
                    (string) ($data['neighborhood'] ?? ''),
                    (string) ($data['property_type'] ?? ''),
                ]));

                return str_contains($haystack, $search);
            })
            ->values();
    }

    public function getProfilesProperty(): Collection
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->where('profile_type', 'seller')
            ->get()
            ->keyBy('id');
    }

    public function queueResearch(int $profileId): void
    {
        abort_unless($this->canWrite(), 403);

        $state = app(RealEstateOperatorValuationService::class)->request(
            profileId: $profileId,
            operatorUserId: (int) auth()->id(),
        );

        if (($state['status'] ?? null) !== 'queued') {
            Notification::make()
                ->title('Değerleme araştırması kuyruğa alınamadı')
                ->warning()
                ->send();

            return;
        }

        RunRealEstateOperatorValuationResearch::dispatch(
            profileId: $profileId,
            operatorUserId: (int) auth()->id(),
        );

        Notification::make()
            ->title('Güncel emsal araştırması kuyruğa alındı')
            ->body('Yalnız bu operatör talebi için botun kendi OpenAI anahtarı kullanılır. Müşteriye WhatsApp veya follow-up gönderilmez.')
            ->success()
            ->send();
    }

    public function getHeading(): string
    {
        return '';
    }

    private function canWrite(): bool
    {
        $user = auth()->user();

        return static::canAccess()
            && $user
            && $user->canManageOrganization(RealEstateIsolationService::ORGANIZATION_ID);
    }
}
