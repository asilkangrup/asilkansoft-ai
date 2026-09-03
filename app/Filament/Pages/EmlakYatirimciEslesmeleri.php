<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateInvestorShortlistService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakYatirimciEslesmeleri extends Page
{
    protected string $view = 'filament.pages.emlak-yatirimci-eslesmeleri';

    protected static ?string $title = 'Yatırımcı Eşleşmeleri';

    protected static ?string $navigationLabel = 'Yatırımcı Eşleşmeleri';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 35;

    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public ?int $selectedSellerProfileId = null;

    public string $search = '';

    public string $readinessFilter = 'all';

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
        $requested = request()->integer('seller');

        if ($requested > 0 && $this->sellerQuery()->whereKey($requested)->exists()) {
            $this->selectedSellerProfileId = $requested;
            return;
        }

        $this->selectedSellerProfileId = $this->sellerQuery()
            ->latest('updated_at')
            ->value('id');
    }

    public function selectSeller(int $profileId): void
    {
        if ($this->sellerQuery()->whereKey($profileId)->exists()) {
            $this->selectedSellerProfileId = $profileId;
        }
    }

    public function setReadinessFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'ready_to_call', 'criteria_incomplete', 'review_match'], true)) {
            $this->readinessFilter = $filter;
        }
    }

    public function getSellersProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $this->sellerQuery()
            ->with('conversation')
            ->latest('updated_at')
            ->limit(250)
            ->get()
            ->filter(function (RealEstateProfile $profile) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $data = is_array($profile->data) ? $profile->data : [];
                $haystack = mb_strtolower(implode(' ', [
                    $profile->conversation?->customer_name ?? '',
                    $profile->conversation?->whatsapp_number ?? '',
                    $data['city'] ?? '',
                    $data['district'] ?? '',
                    $data['neighborhood'] ?? '',
                    $data['property_type'] ?? '',
                ]));

                return str_contains($haystack, $search);
            })
            ->values();
    }

    public function getSelectedSellerProperty(): ?RealEstateProfile
    {
        if (! $this->selectedSellerProfileId) {
            return null;
        }

        return $this->sellerQuery()
            ->with('conversation')
            ->whereKey($this->selectedSellerProfileId)
            ->first();
    }

    public function getShortlistProperty(): array
    {
        $seller = $this->selectedSeller;

        if (! $seller) {
            return [];
        }

        $shortlist = app(RealEstateInvestorShortlistService::class)
            ->buildForSeller($seller);

        if ($this->readinessFilter === 'all' || ! is_array($shortlist['candidates'] ?? null)) {
            return $shortlist;
        }

        $shortlist['candidates'] = collect($shortlist['candidates'])
            ->filter(fn (array $candidate): bool =>
                ($candidate['readiness'] ?? null) === $this->readinessFilter
            )
            ->values()
            ->all();
        $shortlist['candidate_count'] = count($shortlist['candidates']);
        $shortlist['ready_to_call_count'] = collect($shortlist['candidates'])
            ->where('readiness', 'ready_to_call')
            ->count();

        return $shortlist;
    }

    public function refreshMatches(): void
    {
        abort_unless(
            static::canAccess()
                && auth()->user()?->canManageOrganization(RealEstateIsolationService::ORGANIZATION_ID),
            403
        );

        $seller = $this->selectedSeller;

        if (! $seller || ! $seller->conversation) {
            Notification::make()
                ->title('Satıcı dosyası bulunamadı')
                ->warning()
                ->send();
            return;
        }

        $matches = app(RealEstateMatchService::class)->process($seller->conversation);

        Notification::make()
            ->title('Yatırımcı eşleşmeleri güncellendi')
            ->body(count($matches).' uygun aday bulundu. Otomatik mesaj gönderilmedi.')
            ->success()
            ->send();
    }

    private function sellerQuery()
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
