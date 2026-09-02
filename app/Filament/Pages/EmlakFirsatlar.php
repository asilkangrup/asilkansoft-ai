<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateCommercialDealService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOpportunityScoreService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakFirsatlar extends Page
{
    protected string $view = 'filament.pages.emlak-firsatlar';
    protected static ?string $title = 'Fırsatlar';
    protected static ?string $navigationLabel = 'Fırsatlar';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;
    protected static ?int $navigationSort = 30;

    public string $filter = 'priority';
    public string $search = '';
    public array $commissionRates = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && (int) $user->id === RealEstateIsolationService::USER_ID
            && $user->activeOrganizations()->where('organizations.id', RealEstateIsolationService::ORGANIZATION_ID)->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['priority', 'actionable', 'preparation', 'blocked', 'all'], true)) {
            $this->filter = $filter;
        }
    }

    public function getOpportunitiesProperty(): Collection
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->with('conversation')
            ->get()
            ->map(function (RealEstateProfile $profile): array {
                $data = is_array($profile->data) ? $profile->data : [];
                $score = app(RealEstateOpportunityScoreService::class)->summaryForProfile($profile);
                $deal = app(RealEstateCommercialDealService::class)->summaryForProfile($profile);
                $location = $this->first($data, ['location', 'property.location', 'district', 'city']) ?: 'Konum belirtilmedi';
                $type = $this->first($data, ['property_type', 'property.type']) ?: 'taşınmaz';

                return [
                    'id' => $profile->id,
                    'name' => $profile->conversation?->customer_name ?: 'İsimsiz satıcı',
                    'phone' => $profile->conversation?->whatsapp_number,
                    'property' => trim($location.' '.$type),
                    'score' => (int) ($score['score'] ?? 0),
                    'grade' => (string) ($score['grade'] ?? 'weak'),
                    'state' => (string) ($score['state'] ?? 'research_required'),
                    'action' => (string) ($score['recommended_operator_action'] ?? 'Dosyayı incele.'),
                    'discount' => data_get($score, 'metrics.asking_to_realistic_discount_percent'),
                    'candidate_count' => (int) data_get($score, 'metrics.candidate_count', 0),
                    'match_score' => data_get($score, 'metrics.strongest_match_score'),
                    'completeness' => (int) data_get($score, 'metrics.file_completeness', 0),
                    'valuation_confidence' => (int) data_get($score, 'metrics.valuation_confidence', 0),
                    'asking_price' => $deal['asking_price'] ?? null,
                    'realistic_sale_min' => $deal['realistic_sale_min'] ?? null,
                    'realistic_sale_max' => $deal['realistic_sale_max'] ?? null,
                    'negotiation_target_min' => $deal['negotiation_target_min'] ?? null,
                    'negotiation_target_max' => $deal['negotiation_target_max'] ?? null,
                    'gap_amount' => $deal['gap_to_investor_band_amount'] ?? null,
                    'gap_percent' => $deal['gap_to_investor_band_percent'] ?? null,
                    'commission_rate' => $deal['commission_rate_percent'] ?? null,
                    'expected_commission' => $deal['expected_commission_amount'] ?? null,
                    'commercial_action' => $deal['recommended_operator_action'] ?? null,
                    'customer_url' => $profile->conversation
                        ? url('/admin/emlak-musteri-detay?customer='.$profile->conversation->id)
                        : null,
                ];
            })
            ->filter(fn (array $item): bool => $this->matchesFilter($item))
            ->filter(function (array $item): bool {
                $search = mb_strtolower(trim($this->search));
                return $search === '' || str_contains(
                    mb_strtolower($item['name'].' '.$item['property'].' '.($item['phone'] ?? '')),
                    $search
                );
            })
            ->sortByDesc('score')
            ->values();
    }

    public function getStatsProperty(): array
    {
        $previous = $this->filter;
        $this->filter = 'all';
        $all = $this->getOpportunitiesProperty();
        $this->filter = $previous;

        return [
            'exceptional' => $all->where('grade', 'exceptional')->count(),
            'strong' => $all->where('grade', 'strong')->count(),
            'preparation' => $all->whereIn('state', ['research_required', 'preparation_required'])->count(),
            'blocked' => $all->where('state', 'blocked')->count(),
        ];
    }

    public function saveCommission(int $profileId): void
    {
        abort_unless(static::canAccess() && auth()->user()?->canManageOrganization(RealEstateIsolationService::ORGANIZATION_ID), 403);

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->whereKey($profileId)
            ->firstOrFail();
        $rate = $this->commissionRates[$profileId] ?? null;

        if (! is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 20) {
            Notification::make()->title('Komisyon oranı 0–20 arasında olmalı')->warning()->send();
            return;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $terms = is_array($data['commercial_terms'] ?? null) ? $data['commercial_terms'] : [];
        $terms['commission_rate_percent'] = round((float) $rate, 2);
        $terms['commission_estimate_only'] = true;
        $terms['updated_at'] = now()->toIso8601String();
        $data['commercial_terms'] = $terms;

        $profile->forceFill(['data' => $data])->save();
        Notification::make()->title('Komisyon oranı CRM’e kaydedildi')->success()->send();
    }

    private function matchesFilter(array $item): bool
    {
        return match ($this->filter) {
            'priority' => $item['state'] !== 'blocked',
            'actionable' => $item['state'] === 'actionable',
            'preparation' => in_array($item['state'], ['research_required', 'preparation_required'], true),
            'blocked' => $item['state'] === 'blocked',
            default => true,
        };
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

    public function getHeading(): string
    {
        return '';
    }
}
