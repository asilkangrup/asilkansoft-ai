<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakMusteriDetay extends Page
{
    protected string $view = 'filament.pages.emlak-musteri-detay';
    protected static ?string $title = 'Müşteri Detayı';
    protected static ?string $navigationLabel = 'Müşteri Detayı';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
    protected static bool $shouldRegisterNavigation = false;

    public ?int $customerId = null;
    public string $note = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()
                ->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)
                ->exists();
    }

    public function mount(): void
    {
        $id = request()->integer('customer');
        $this->customerId = $id > 0 ? $id : null;
        abort_unless($this->customer !== null, 404);
    }

    public function getCustomerProperty(): ?ConversationControl
    {
        if (! $this->customerId) {
            return null;
        }

        return ConversationControl::query()
            ->whereKey($this->customerId)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->first();
    }

    public function getProfileProperty(): ?RealEstateProfile
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $this->customerId)
            ->first();
    }

    public function getActivitiesProperty(): Collection
    {
        return $this->customer?->activities()
            ->with('performedByUser')
            ->limit(50)
            ->get() ?? collect();
    }

    public function getDetailsProperty(): array
    {
        $profile = $this->profile;
        $data = is_array($profile?->data) ? $profile->data : [];
        $isInvestor = in_array($profile?->profile_type, ['investor', 'buyer'], true);

        return [
            'role' => $isInvestor ? 'Yatırımcı' : 'Satıcı',
            'location' => $this->first($data, ['location', 'property.location', 'preferred_locations.0', 'investment_locations.0']) ?: 'Belirtilmedi',
            'property_type' => $this->first($data, ['property_type', 'property.type', 'preferred_property_types.0', 'property_types.0']) ?: 'Belirtilmedi',
            'area' => $this->first($data, ['area_sqm', 'square_meters', 'property.area_sqm']) ?: 'Belirtilmedi',
            'price' => $this->money($data, $isInvestor
                ? ['max_budget', 'budget_max', 'investment_budget']
                : ['asking_price', 'requested_price', 'property.asking_price']),
            'price_label' => $isInvestor ? 'Azami bütçe' : 'İstenen fiyat',
            'completeness' => (int) ($profile?->completeness_score ?? 0),
            'confidence' => (int) ($profile?->confidence_score ?? 0),
            'next_action' => $isInvestor
                ? 'Yatırımcının kriterlerine uygun gerçek portföyleri kontrol et.'
                : (string) data_get($data, 'investor_offer_handoff_intelligence.recommended_operator_action', 'Satıcı dosyasındaki eksikleri tamamla.'),
        ];
    }

    public function saveNote(): void
    {
        $customer = $this->customer;
        abort_unless($customer && static::canAccess(), 403);

        $note = trim($this->note);

        if ($note === '') {
            Notification::make()->title('Not alanı boş')->warning()->send();
            return;
        }

        $entry = now()->format('d.m.Y H:i').' — '.$note;
        $customer->forceFill([
            'notes' => trim(($customer->notes ? $customer->notes."\n\n" : '').$entry),
            'next_follow_up_at' => null,
        ])->save();

        $customer->activities()->create([
            'user_id' => RealEstateIsolationService::USER_ID,
            'ai_bot_id' => RealEstateIsolationService::BOT_ID,
            'performed_by_user_id' => auth()->id(),
            'type' => 'note',
            'title' => 'Emlak CRM notu eklendi',
            'description' => $note,
            'meta' => [
                'scope' => 'isolated_real_estate',
                'follow_up_scheduling_allowed' => false,
                'automatic_outbound_allowed' => false,
            ],
        ]);

        $this->note = '';
        Notification::make()->title('Not CRM’e kaydedildi')->success()->send();
    }

    public function backUrl(): string
    {
        return url('/admin/emlak-crm');
    }

    private function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function money(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    public function getHeading(): string
    {
        return '';
    }
}
