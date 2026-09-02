<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakCrm extends Page
{
    protected string $view = 'filament.pages.emlak-crm';
    protected static ?string $title = 'Emlak CRM';
    protected static ?string $navigationLabel = 'Emlak CRM';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?int $navigationSort = 32;

    public string $tab = 'portfolios';
    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()
                ->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)
                ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['portfolios', 'sellers', 'investors'], true)) {
            $this->tab = $tab;
        }
    }

    public function getStatsProperty(): array
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->get(['id', 'profile_type', 'data']);

        $sellers = $profiles->where('profile_type', 'seller');
        $investors = $profiles->filter(
            fn (RealEstateProfile $profile): bool => in_array($profile->profile_type, ['investor', 'buyer'], true)
        );

        return [
            'portfolios' => $sellers->count(),
            'sellers' => $sellers->count(),
            'investors' => $investors->count(),
            'ready' => $sellers->filter(function (RealEstateProfile $profile): bool {
                $data = is_array($profile->data) ? $profile->data : [];
                return (bool) data_get($data, 'investor_offer_handoff_intelligence.ready_for_operator_handoff', false);
            })->count(),
        ];
    }

    public function getRecordsProperty(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->latest('last_extracted_at')
            ->limit(250)
            ->get();

        $profiles = match ($this->tab) {
            'investors' => $profiles->filter(
                fn (RealEstateProfile $profile): bool => in_array($profile->profile_type, ['investor', 'buyer'], true)
            ),
            default => $profiles->where('profile_type', 'seller'),
        };

        return $profiles
            ->map(fn (RealEstateProfile $profile): array => $this->record($profile))
            ->filter(function (array $record): bool {
                $search = mb_strtolower(trim($this->search));

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower(implode(' ', [
                    $record['name'],
                    $record['phone'],
                    $record['title'],
                    $record['location'],
                    $record['notes'],
                ])), $search);
            })
            ->values();
    }

    private function record(RealEstateProfile $profile): array
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $conversation = $profile->conversation;
        $isInvestor = in_array($profile->profile_type, ['investor', 'buyer'], true);
        $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
            ? $data['investor_offer_handoff_intelligence']
            : [];
        $packet = is_array($data['seller_offer_packet_intelligence'] ?? null)
            ? $data['seller_offer_packet_intelligence']
            : [];

        $location = $this->firstValue($data, [
            'location', 'property.location', 'property_location', 'district', 'city',
            'preferred_location', 'investment_locations.0', 'preferred_locations.0',
        ]) ?: 'Konum belirtilmedi';
        $type = $this->firstValue($data, [
            'property_type', 'property.type', 'asset_type', 'preferred_property_type',
            'property_types.0', 'preferred_property_types.0',
        ]) ?: ($isInvestor ? 'Taşınmaz tercihi belirtilmedi' : 'Taşınmaz');
        $area = $this->firstValue($data, ['area_sqm', 'square_meters', 'property.area_sqm', 'size_sqm']);
        $asking = $this->numericValue($data, ['asking_price', 'requested_price', 'property.asking_price']);
        $budget = $this->numericValue($data, ['budget_max', 'max_budget', 'investment_budget', 'budget.maximum']);
        $status = $isInvestor
            ? $this->investorStatus($data)
            : (string) ($handoff['status'] ?? $packet['status'] ?? 'packet_incomplete');

        return [
            'id' => $profile->id,
            'conversation_id' => $conversation?->id,
            'name' => $conversation?->customer_name ?: ($isInvestor ? 'İsimsiz yatırımcı' : 'İsimsiz satıcı'),
            'phone' => $conversation?->whatsapp_number ?: '',
            'title' => $isInvestor ? $type.' yatırımcısı' : trim($location.' '.$type),
            'location' => $location,
            'type' => $type,
            'area' => $area,
            'price' => $isInvestor ? $budget : $asking,
            'price_label' => $isInvestor ? 'Azami bütçe' : 'İstenen fiyat',
            'status' => $status,
            'status_label' => $this->statusLabel($status, $isInvestor),
            'status_tone' => $this->statusTone($status),
            'score' => (int) ($profile->completeness_score ?? 0),
            'confidence' => (int) ($profile->confidence_score ?? 0),
            'match_count' => (int) ($handoff['candidate_count'] ?? count((array) ($data['opportunity_matches'] ?? []))),
            'next_action' => $isInvestor
                ? $this->investorNextAction($data)
                : (string) ($handoff['recommended_operator_action'] ?? 'Satıcı dosyasındaki eksikleri tamamla.'),
            'notes' => trim((string) ($conversation?->notes ?? '')),
            'updated_at' => ($profile->last_extracted_at ?? $profile->updated_at)?->diffForHumans() ?? '—',
            'customer_url' => $conversation ? url('/admin/emlak-musteri-detay?customer='.$conversation->id) : null,
            'is_investor' => $isInvestor,
        ];
    }

    private function investorStatus(array $data): string
    {
        $hasLocation = $this->firstValue($data, [
            'preferred_location', 'investment_locations.0', 'preferred_locations.0', 'location',
        ]) !== null;
        $hasBudget = $this->numericValue($data, [
            'budget_max', 'max_budget', 'investment_budget', 'budget.maximum',
        ]) !== null;

        return $hasLocation && $hasBudget ? 'active' : 'criteria_incomplete';
    }

    private function investorNextAction(array $data): string
    {
        if ($this->investorStatus($data) === 'active') {
            return 'Yeni ve uygun portföy eşleşmelerini kontrol et; yalnız gerçek kriter uyumunda ara.';
        }

        return 'Yatırımcının hedef bölgesini, taşınmaz türünü ve azami bütçesini netleştir.';
    }

    private function statusLabel(string $status, bool $investor): string
    {
        if ($investor) {
            return $status === 'active' ? 'Aktif yatırımcı' : 'Kriterleri eksik';
        }

        return match ($status) {
            'ready' => 'Yatırımcıya hazır',
            'packet_incomplete' => 'Dosya eksik',
            'confirmation_required' => 'Bilgi doğrulanmalı',
            'verification_required' => 'Belge doğrulanmalı',
            'evidence_required' => 'Kanıt eksik',
            'valuation_required' => 'Değerleme gerekli',
            'comparable_review' => 'Emsal kontrolü',
            'investor_sourcing' => 'Yatırımcı aranıyor',
            default => 'İnceleniyor',
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'ready', 'active' => 'green',
            'investor_sourcing', 'valuation_required', 'comparable_review' => 'blue',
            'confirmation_required', 'verification_required' => 'red',
            default => 'orange',
        };
    }

    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function numericValue(array $data, array $keys): ?int
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
