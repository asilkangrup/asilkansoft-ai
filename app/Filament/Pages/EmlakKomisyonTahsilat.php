<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateCommissionService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakKomisyonTahsilat extends Page
{
    protected string $view = 'filament.pages.emlak-komisyon-tahsilat';
    protected static ?string $title = 'Komisyon ve Tahsilat';
    protected static ?string $navigationLabel = 'Komisyon / Tahsilat';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;
    protected static ?int $navigationSort = 37;
    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public ?string $selectedKey = null;
    public string $search = '';
    public string $sellerCollectedAmount = '0';
    public string $buyerCollectedAmount = '0';
    public ?string $sellerCollectedAt = null;
    public ?string $buyerCollectedAt = null;
    public string $sellerReceiptReference = '';
    public string $buyerReceiptReference = '';
    public string $operatorNote = '';

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
        $requested = trim((string) request()->query('deal', ''));
        $this->selectedKey = $this->dealExists($requested)
            ? $requested
            : $this->deals->first()['key'] ?? null;

        $this->loadSelected();
    }

    public function selectDeal(string $key): void
    {
        if (! $this->dealExists($key)) {
            return;
        }

        $this->selectedKey = $key;
        $this->loadSelected();
    }

    public function save(): void
    {
        abort_unless(static::canAccess() && $this->selectedDeal, 403);

        $seller = $this->profile((int) $this->selectedDeal['seller_profile_id']);
        $investor = $this->profile((int) $this->selectedDeal['investor_profile_id']);
        abort_unless($seller && $investor, 404);

        app(RealEstateCommissionService::class)->save($seller, $investor, [
            'seller_collected_amount' => $this->sellerCollectedAmount,
            'buyer_collected_amount' => $this->buyerCollectedAmount,
            'seller_collected_at' => $this->sellerCollectedAt,
            'buyer_collected_at' => $this->buyerCollectedAt,
            'seller_receipt_reference' => $this->sellerReceiptReference,
            'buyer_receipt_reference' => $this->buyerReceiptReference,
            'operator_note' => $this->operatorNote,
        ], auth()->user());

        $this->loadSelected();
        Notification::make()->title('Komisyon ve tahsilat kaydı güncellendi')->success()->send();
    }

    public function getDealsProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return app(RealEstateCommissionService::class)
            ->eligibleDeals()
            ->filter(function (array $deal) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower(implode(' ', [
                    (string) ($deal['seller']->conversation?->customer_name ?? ''),
                    (string) ($deal['investor']->conversation?->customer_name ?? ''),
                    $this->propertyLabel((array) $deal['seller']->data),
                ])), $search);
            })
            ->values();
    }

    public function getSelectedDealProperty(): ?array
    {
        if (! $this->selectedKey) {
            return null;
        }

        return $this->deals->first(
            fn (array $deal): bool => $deal['key'] === $this->selectedKey
        );
    }

    public function getTotalsProperty(): array
    {
        return app(RealEstateCommissionService::class)->totals();
    }

    public function getCommissionProperty(): array
    {
        return $this->selectedDeal['commission'] ?? [];
    }

    public function getRecordProperty(): array
    {
        if (! $this->selectedDeal) {
            return [];
        }

        return app(RealEstateCommissionService::class)->recordForPair(
            (int) $this->selectedDeal['seller_profile_id'],
            (int) $this->selectedDeal['investor_profile_id'],
        ) ?? [];
    }

    private function loadSelected(): void
    {
        $record = $this->record;

        $this->sellerCollectedAmount = (string) ($record['seller_collected_amount'] ?? 0);
        $this->buyerCollectedAmount = (string) ($record['buyer_collected_amount'] ?? 0);
        $this->sellerCollectedAt = $this->dateInput($record['seller_collected_at'] ?? null);
        $this->buyerCollectedAt = $this->dateInput($record['buyer_collected_at'] ?? null);
        $this->sellerReceiptReference = (string) ($record['seller_receipt_reference'] ?? '');
        $this->buyerReceiptReference = (string) ($record['buyer_receipt_reference'] ?? '');
        $this->operatorNote = (string) ($record['operator_note'] ?? '');
    }

    private function dateInput(mixed $value): ?string
    {
        return filled($value)
            ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d')
            : null;
    }

    private function dealExists(string $key): bool
    {
        return $key !== '' && $this->deals->contains(
            fn (array $deal): bool => $deal['key'] === $key
        );
    }

    private function profile(int $id): ?RealEstateProfile
    {
        return RealEstateProfile::query()->isolatedProduction()->whereKey($id)->first();
    }

    private function propertyLabel(array $data): string
    {
        return collect([
            data_get($data, 'city'),
            data_get($data, 'district'),
            data_get($data, 'location'),
            data_get($data, 'property_type') ?: data_get($data, 'property.type'),
        ])->filter()->unique()->implode(' · ') ?: 'Taşınmaz';
    }

    public function getHeading(): string
    {
        return '';
    }
}
