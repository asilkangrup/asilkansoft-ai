<?php

namespace App\Filament\Pages;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Services\RealEstateCommercialDealService;
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

    public function getMediaGalleryProperty(): array
    {
        $profile = $this->profile;

        if (! $profile || $profile->profile_type !== 'seller') {
            return [];
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $findings = collect(is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [])
            ->filter(fn ($finding): bool => is_array($finding) && filled($finding['message_id'] ?? null))
            ->keyBy(fn (array $finding): string => trim((string) $finding['message_id']));

        $grouped = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $this->customer?->session_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('media_url')
            ->whereIn('message_type', ['image', 'document'])
            ->latest('id')
            ->get()
            ->map(function (ChatMessage $message) use ($findings): ?array {
                $finding = $findings->get(trim((string) $message->whatsapp_message_id), []);
                $category = (string) ($finding['media_category'] ?? '');

                if (! in_array($category, ['property_photo', 'title_deed', 'parcel_document', 'listing'], true)) {
                    return null;
                }

                $url = trim((string) $message->media_url);
                if (! preg_match('/^https?:\/\//i', $url)) {
                    return null;
                }

                return [
                    'id' => $message->id,
                    'url' => $url,
                    'category' => $category,
                    'group' => match ($category) {
                        'property_photo' => 'Arsa Fotoğrafları',
                        'listing' => 'İlan Görselleri',
                        default => 'Tapu / Parsel Belgeleri',
                    },
                    'is_image' => str_starts_with(strtolower((string) $message->media_mime_type), 'image/'),
                    'summary' => trim((string) ($finding['summary'] ?? '')),
                    'received_at' => $message->created_at?->format('d.m.Y H:i'),
                ];
            })
            ->filter()
            ->groupBy('group')
            ->all();

        return [
            'Arsa Fotoğrafları' => $grouped['Arsa Fotoğrafları'] ?? collect(),
            'Tapu / Parsel Belgeleri' => $grouped['Tapu / Parsel Belgeleri'] ?? collect(),
            'İlan Görselleri' => $grouped['İlan Görselleri'] ?? collect(),
        ];
    }

    public function getMessagesProperty(): Collection
    {
        if (! $this->customer) {
            return collect();
        }

        return ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $this->customer->session_id)
            ->whereIn('sender_type', ['customer', 'ai', 'human'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();
    }

    public function getDossierProperty(): array
    {
        $profile = $this->profile;
        $data = is_array($profile?->data) ? $profile->data : [];
        $valuation = is_array($profile?->valuation) ? $profile->valuation : [];
        $packet = is_array($data['seller_offer_packet_intelligence'] ?? null)
            ? $data['seller_offer_packet_intelligence'] : [];
        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
            ? $data['investor_offer_handoff_intelligence'] : [];
        $motivation = is_array($data['seller_motivation_intelligence'] ?? null)
            ? $data['seller_motivation_intelligence'] : [];
        $commercial = $profile
            ? app(RealEstateCommercialDealService::class)->summaryForProfile($profile)
            : [];

        return [
            'property' => [
                'İl' => $this->first($data, ['city']),
                'İlçe' => $this->first($data, ['district']),
                'Mahalle / Bölge' => $this->first($data, ['neighborhood', 'location', 'property.location']),
                'Taşınmaz türü' => $this->first($data, ['property_type', 'property.type']),
                'Metrekare' => $this->first($data, ['area_sqm', 'square_meters', 'property.area_sqm']),
                'Ada' => $this->first($data, ['block_no', 'ada_no']),
                'Parsel' => $this->first($data, ['parcel_no', 'parsel_no']),
                'Tapu niteliği' => $this->first($data, ['title_deed_type']),
                'İmar durumu' => $this->first($data, ['zoning_status']),
                'Hisse durumu' => $this->first($data, ['owner_share', 'share_status']),
                'Konum bağlantısı' => $this->first($data, ['location_url']),
                'İlan bağlantısı' => $this->first($data, ['listing_url']),
            ],
            'pricing' => [
                'Satıcı beklentisi' => $commercial['asking_price'] ?? null,
                'Gerçekçi satış alt' => $commercial['realistic_sale_min'] ?? null,
                'Gerçekçi satış üst' => $commercial['realistic_sale_max'] ?? null,
                'Yatırımcı hedef alt' => $commercial['negotiation_target_min'] ?? null,
                'Yatırımcı hedef üst' => $commercial['negotiation_target_max'] ?? null,
                'Pazarlık farkı' => $commercial['gap_to_investor_band_amount'] ?? null,
                'Tahmini komisyon' => $commercial['expected_commission_amount'] ?? null,
            ],
            'motivation' => [
                'Aciliyet' => $this->first($motivation, ['motivation_level']) ?: $this->first($data, ['urgency']),
                'Pazarlık esnekliği' => $this->first($motivation, ['confidential_price_flexibility.band']),
                'Dosya hazırlığı' => $this->first($packet, ['status']),
                'Yatırımcıya hazır' => (bool) ($handoff['ready_for_operator_handoff'] ?? false) ? 'Evet' : 'Hayır',
                'Uygun yatırımcı' => (int) ($handoff['candidate_count'] ?? 0),
                'Değerleme güveni' => (int) ($valuation['confidence_score'] ?? $profile?->confidence_score ?? 0).'% ',
            ],
            'missing' => array_values(array_unique(array_merge(
                is_array($packet['missing_critical_for_offer'] ?? null) ? $packet['missing_critical_for_offer'] : [],
                is_array($packet['missing_supporting_context'] ?? null) ? $packet['missing_supporting_context'] : [],
            ))),
            'call_script' => $this->callScript($commercial, $handoff),
        ];
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

    private function callScript(array $commercial, array $handoff): string
    {
        $action = trim((string) ($commercial['recommended_operator_action'] ?? ''));
        $missing = trim((string) ($handoff['recommended_operator_action'] ?? ''));

        if ($missing !== '' && ($commercial['state'] ?? null) === 'file_required') {
            return 'Merhaba, taşınmaz dosyanızı yatırımcılara doğru sunabilmemiz için '.$missing;
        }

        if ($action !== '') {
            return 'Merhaba, taşınmazınızla ilgili dosyayı inceledim. '.$action;
        }

        return 'Merhaba, taşınmaz dosyanızı birlikte netleştirip uygun yatırımcılardan gerçek teklif toplamak için arıyorum.';
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
