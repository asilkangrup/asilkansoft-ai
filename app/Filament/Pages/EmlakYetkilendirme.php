<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateAuthorizationService;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakYetkilendirme extends Page
{
    protected string $view = 'filament.pages.emlak-yetkilendirme';
    protected static ?string $title = 'Yetkilendirme ve Sözleşme';
    protected static ?string $navigationLabel = 'Yetkilendirme';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;
    protected static ?int $navigationSort = 35;
    protected static string|\UnitEnum|null $navigationGroup = 'Emlak';

    public ?int $selectedProfileId = null;
    public string $search = '';
    public string $mandateType = 'none';
    public ?string $signedAt = null;
    public ?string $expiresAt = null;
    public bool $sellerPresentationConsent = false;
    public bool $commissionTermsAcknowledged = false;
    public bool $titleOwnerConfirmed = false;
    public bool $authorizationDocumentPresent = false;
    public bool $legalReviewRequired = false;
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
        $requested = request()->integer('profile');
        $this->selectedProfileId = $requested > 0 && $this->profileQuery()->whereKey($requested)->exists()
            ? $requested
            : $this->profileQuery()->latest('updated_at')->value('id');

        $this->loadSelected();
    }

    public function selectProfile(int $profileId): void
    {
        if (! $this->profileQuery()->whereKey($profileId)->exists()) {
            return;
        }

        $this->selectedProfileId = $profileId;
        $this->loadSelected();
    }

    public function save(): void
    {
        $profile = $this->selectedProfile;
        abort_unless($profile && static::canAccess(), 403);

        app(RealEstateAuthorizationService::class)->update($profile, [
            'mandate_type' => $this->mandateType,
            'signed_at' => $this->signedAt,
            'expires_at' => $this->expiresAt,
            'seller_presentation_consent' => $this->sellerPresentationConsent,
            'commission_terms_acknowledged' => $this->commissionTermsAcknowledged,
            'title_owner_confirmed' => $this->titleOwnerConfirmed,
            'authorization_document_present' => $this->authorizationDocumentPresent,
            'legal_review_required' => $this->legalReviewRequired,
            'operator_note' => $this->operatorNote,
        ], auth()->user());

        $this->loadSelected();
        Notification::make()
            ->title('Yetkilendirme kontrolü CRM’e kaydedildi')
            ->success()
            ->send();
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
                    (string) ($profile->conversation?->whatsapp_number ?? ''),
                    (string) data_get($data, 'city', ''),
                    (string) data_get($data, 'district', ''),
                    (string) data_get($data, 'location', ''),
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

    public function getControlProperty(): array
    {
        return $this->selectedProfile
            ? app(RealEstateAuthorizationService::class)->summarize($this->selectedProfile)
            : [];
    }

    public function getChecklistProperty(): array
    {
        $control = $this->control;

        return [
            'Yetkilendirme türü seçildi' => ($control['mandate_type'] ?? 'none') !== 'none',
            'İmza ve geçerlilik tarihleri girildi' => filled($control['signed_at'] ?? null) && filled($control['expires_at'] ?? null),
            'Satıcı yatırımcı sunumuna yazılı onay verdi' => (bool) ($control['seller_presentation_consent'] ?? false),
            'Satıcı %2 hizmet bedelini kabul etti' => (bool) ($control['commission_terms_acknowledged'] ?? false),
            'Tapu sahibi ilişkisi doğrulandı' => (bool) ($control['title_owner_confirmed'] ?? false),
            'Yetkilendirme belgesi dosyada mevcut' => (bool) ($control['authorization_document_present'] ?? false),
            'Hukuki inceleme engeli bulunmuyor' => ! (bool) ($control['legal_review_required'] ?? false),
        ];
    }

    private function loadSelected(): void
    {
        $control = $this->control;

        $this->mandateType = (string) ($control['mandate_type'] ?? 'none');
        $this->signedAt = $control['signed_at'] ?? null;
        $this->expiresAt = $control['expires_at'] ?? null;
        $this->sellerPresentationConsent = (bool) ($control['seller_presentation_consent'] ?? false);
        $this->commissionTermsAcknowledged = (bool) ($control['commission_terms_acknowledged'] ?? false);
        $this->titleOwnerConfirmed = (bool) ($control['title_owner_confirmed'] ?? false);
        $this->authorizationDocumentPresent = (bool) ($control['authorization_document_present'] ?? false);
        $this->legalReviewRequired = (bool) ($control['legal_review_required'] ?? false);
        $this->operatorNote = (string) ($control['operator_note'] ?? '');
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
