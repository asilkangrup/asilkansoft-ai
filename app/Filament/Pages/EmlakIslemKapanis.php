<?php

namespace App\Filament\Pages;

use App\Models\RealEstateClosingCase;
use App\Models\RealEstateProfile;
use App\Services\RealEstateClosingService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakIslemKapanis extends Page
{
    protected string $view = 'filament.pages.emlak-islem-kapanis';
    protected static ?string $title = 'Tapu ve İşlem Kapanışı';
    protected static ?string $navigationLabel = 'İşlem Kapanışı';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;
    protected static ?int $navigationSort = 36;
    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public ?string $selectedKey = null;
    public string $search = '';
    public ?string $agreedPrice = null;
    public bool $titleDeedVerified = false;
    public bool $identityAuthorityVerified = false;
    public bool $encumbranceChecked = false;
    public bool $taxFeeChecked = false;
    public bool $paymentMethodConfirmed = false;
    public ?string $appointmentAt = null;
    public string $appointmentLocation = '';
    public ?string $depositAmount = null;
    public bool $depositReceived = false;
    public bool $finalPaymentVerified = false;
    public bool $deedTransferCompleted = false;
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

        app(RealEstateClosingService::class)->save($seller, $investor, [
            'agreed_price' => $this->agreedPrice,
            'title_deed_verified' => $this->titleDeedVerified,
            'identity_authority_verified' => $this->identityAuthorityVerified,
            'encumbrance_checked' => $this->encumbranceChecked,
            'tax_fee_checked' => $this->taxFeeChecked,
            'payment_method_confirmed' => $this->paymentMethodConfirmed,
            'appointment_at' => $this->appointmentAt,
            'appointment_location' => $this->appointmentLocation,
            'deposit_amount' => $this->depositAmount,
            'deposit_received' => $this->depositReceived,
            'final_payment_verified' => $this->finalPaymentVerified,
            'deed_transfer_completed' => $this->deedTransferCompleted,
            'operator_note' => $this->operatorNote,
        ], auth()->user());

        $this->loadSelected();
        Notification::make()->title('Kapanış dosyası CRM’e kaydedildi')->success()->send();
    }

    public function getDealsProperty(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return app(RealEstateClosingService::class)
            ->acceptedDeals()
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

    public function getClosingCaseProperty(): ?RealEstateClosingCase
    {
        if (! $this->selectedDeal) {
            return null;
        }

        return app(RealEstateClosingService::class)->caseForPair(
            (int) $this->selectedDeal['seller_profile_id'],
            (int) $this->selectedDeal['investor_profile_id'],
        );
    }

    public function getStatusLabelProperty(): string
    {
        return app(RealEstateClosingService::class)->statusLabel(
            $this->closingCase?->status ?? 'document_review'
        );
    }

    public function getChecklistProperty(): array
    {
        return [
            'Tapu belgesi doğrulandı' => $this->titleDeedVerified,
            'Taraf kimliği ve işlem yetkisi doğrulandı' => $this->identityAuthorityVerified,
            'İpotek, haciz ve takyidat kontrol edildi' => $this->encumbranceChecked,
            'Harç, vergi ve masraf kontrolü yapıldı' => $this->taxFeeChecked,
            'Ödeme yöntemi ve güvenli ödeme planı teyit edildi' => $this->paymentMethodConfirmed,
            'Tapu randevusu planlandı' => filled($this->appointmentAt),
            'Nihai ödeme doğrulandı' => $this->finalPaymentVerified,
            'Tapu devri tamamlandı' => $this->deedTransferCompleted,
        ];
    }

    private function loadSelected(): void
    {
        $case = $this->closingCase;
        $deal = $this->selectedDeal;

        $this->agreedPrice = (string) ($case?->agreed_price ?? $deal['agreed_price'] ?? '');
        $this->titleDeedVerified = (bool) ($case?->title_deed_verified ?? false);
        $this->identityAuthorityVerified = (bool) ($case?->identity_authority_verified ?? false);
        $this->encumbranceChecked = (bool) ($case?->encumbrance_checked ?? false);
        $this->taxFeeChecked = (bool) ($case?->tax_fee_checked ?? false);
        $this->paymentMethodConfirmed = (bool) ($case?->payment_method_confirmed ?? false);
        $this->appointmentAt = $case?->appointment_at?->format('Y-m-d\TH:i');
        $this->appointmentLocation = (string) ($case?->appointment_location ?? '');
        $this->depositAmount = $case?->deposit_amount ? (string) $case->deposit_amount : null;
        $this->depositReceived = (bool) ($case?->deposit_received ?? false);
        $this->finalPaymentVerified = (bool) ($case?->final_payment_verified ?? false);
        $this->deedTransferCompleted = (bool) ($case?->deed_transfer_completed ?? false);
        $this->operatorNote = (string) ($case?->operator_note ?? '');
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
