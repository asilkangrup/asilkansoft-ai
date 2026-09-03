<?php

namespace App\Filament\Pages;

use App\Jobs\RunRealEstateOperatorMediaAnalysis;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOperatorMediaAnalysisService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakMedyaAnalizi extends Page
{
    protected string $view = 'filament.pages.emlak-medya-analizi';

    protected static ?string $title = 'Medya Analizi';

    protected static ?string $navigationLabel = 'Medya Analizi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 39;

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
        return app(RealEstateOperatorMediaAnalysisService::class)->summary();
    }

    public function getItemsProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));
        $items = app(RealEstateOperatorMediaAnalysisService::class)->queueItems(100);

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
                    (string) ($item['media_type'] ?? ''),
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

    public function queueAnalysis(int $chatMessageId): void
    {
        abort_unless($this->canWrite(), 403);

        $state = app(RealEstateOperatorMediaAnalysisService::class)->request(
            chatMessageId: $chatMessageId,
            operatorUserId: (int) auth()->id(),
        );

        if (($state['status'] ?? null) === 'completed') {
            Notification::make()
                ->title('Bu medya daha önce analiz edilmiş')
                ->success()
                ->send();

            return;
        }

        if (($state['status'] ?? null) !== 'queued') {
            Notification::make()
                ->title('Medya analizi kuyruğa alınamadı')
                ->warning()
                ->send();

            return;
        }

        RunRealEstateOperatorMediaAnalysis::dispatch(
            chatMessageId: $chatMessageId,
            operatorUserId: (int) auth()->id(),
        );

        Notification::make()
            ->title('Medya analizi kuyruğa alındı')
            ->body('Yalnız bu dosya için bot 35’in kendi OpenAI anahtarı kullanılır. Müşteriye WhatsApp veya follow-up gönderilmez.')
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
