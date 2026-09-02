<?php

namespace App\Filament\Pages;

use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOpportunityScoreService;
use BackedEnum;
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
