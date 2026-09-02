<?php

namespace App\Filament\Pages;

use App\Models\CrmActivity;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class EmlakTeklifler extends Page
{
    protected string $view = 'filament.pages.emlak-teklifler';
    protected static ?string $title = 'Teklifler';
    protected static ?string $navigationLabel = 'Teklifler';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;
    protected static ?int $navigationSort = 33;

    public string $filter = 'active';
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

    public function setFilter(string $filter): void
    {
        if (in_array($filter, ['active', 'waiting_seller', 'waiting_investor', 'completed', 'all'], true)) {
            $this->filter = $filter;
        }
    }

    public function getDealsProperty(): Collection
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->get()
            ->keyBy('id');

        return $this->activities()
            ->groupBy(fn (CrmActivity $activity): string =>
                (int) $activity->meta['seller_profile_id'].':'.(int) $activity->meta['investor_profile_id']
            )
            ->map(function (Collection $events) use ($profiles): ?array {
                $events = $events->sortBy('id')->values();
                $latest = $events->last();
                $seller = $profiles->get((int) $latest->meta['seller_profile_id']);
                $investor = $profiles->get((int) $latest->meta['investor_profile_id']);

                if (! $seller || ! $investor) {
                    return null;
                }

                $stage = $this->stage((string) $latest->meta['task_kind'], (string) $latest->meta['outcome']);
                $property = $this->propertyLabel((array) $seller->data);
                $latestAmount = $events->reverse()
                    ->map(fn (CrmActivity $event): mixed => $event->meta['offer_amount'] ?? null)
                    ->first(fn (mixed $amount): bool => is_numeric($amount) && (int) $amount > 0);

                return [
                    'key' => $seller->id.':'.$investor->id,
                    'seller_name' => $seller->conversation?->customer_name ?: 'Satıcı',
                    'investor_name' => $investor->conversation?->customer_name ?: 'Yatırımcı',
                    'property' => $property,
                    'stage' => $stage,
                    'stage_label' => $this->stageLabel($stage),
                    'tone' => $this->tone($stage),
                    'amount' => is_numeric($latestAmount) ? (int) $latestAmount : null,
                    'next_action' => $this->nextAction($stage),
                    'updated_at' => $latest->created_at?->diffForHumans() ?? '—',
                    'seller_url' => $seller->conversation ? url('/admin/emlak-musteri-detay?customer='.$seller->conversation->id) : null,
                    'investor_url' => $investor->conversation ? url('/admin/emlak-musteri-detay?customer='.$investor->conversation->id) : null,
                    'timeline' => $events->take(-8)->reverse()->map(fn (CrmActivity $event): array => [
                        'title' => $this->eventTitle((string) $event->meta['task_kind']),
                        'outcome' => (string) $event->meta['outcome'],
                        'amount' => is_numeric($event->meta['offer_amount'] ?? null)
                            ? (int) $event->meta['offer_amount']
                            : null,
                        'date' => $event->created_at?->format('d.m.Y H:i') ?? '—',
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->filter(fn (array $deal): bool => $this->matchesFilter($deal['stage']))
            ->filter(function (array $deal): bool {
                $search = mb_strtolower(trim($this->search));

                return $search === '' || str_contains(
                    mb_strtolower(implode(' ', [$deal['seller_name'], $deal['investor_name'], $deal['property']])),
                    $search
                );
            })
            ->sortByDesc(fn (array $deal): int => match ($deal['stage']) {
                'waiting_seller_final' => 500,
                'waiting_investor' => 400,
                'waiting_seller' => 300,
                'interested' => 200,
                default => 100,
            })
            ->values();
    }

    public function getStatsProperty(): array
    {
        $all = $this->filter;
        $this->filter = 'all';
        $deals = $this->getDealsProperty();
        $this->filter = $all;

        return [
            'active' => $deals->whereNotIn('stage', ['completed', 'rejected'])->count(),
            'waiting_seller' => $deals->whereIn('stage', ['waiting_seller', 'waiting_seller_final'])->count(),
            'waiting_investor' => $deals->where('stage', 'waiting_investor')->count(),
            'completed' => $deals->where('stage', 'completed')->count(),
        ];
    }

    private function activities(): Collection
    {
        return CrmActivity::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('type', 'real_estate_operator_call')
            ->whereHas('conversationControl', fn ($query) => $query
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID))
            ->latest('id')
            ->limit(2000)
            ->get()
            ->filter(fn (CrmActivity $activity): bool =>
                is_array($activity->meta)
                && is_numeric($activity->meta['seller_profile_id'] ?? null)
                && is_numeric($activity->meta['investor_profile_id'] ?? null)
                && is_string($activity->meta['task_kind'] ?? null)
                && is_string($activity->meta['outcome'] ?? null)
            );
    }

    private function stage(string $kind, string $outcome): string
    {
        return match (true) {
            $kind === 'seller_final' && $outcome === 'Kabul etti' => 'completed',
            $kind === 'seller_final' && $outcome === 'Reddetti' => 'rejected',
            $kind === 'investor_counter' && in_array($outcome, ['Kabul etti', 'Yeni teklif'], true) => 'waiting_seller_final',
            $kind === 'investor_counter' && $outcome === 'Reddetti' => 'rejected',
            $kind === 'seller_offer' && $outcome === 'Karşı teklif' => 'waiting_investor',
            $kind === 'seller_offer' && $outcome === 'Kabul etti' => 'completed',
            $kind === 'seller_offer' && $outcome === 'Reddetti' => 'rejected',
            $kind === 'investor' && $outcome === 'Teklif verdi' => 'waiting_seller',
            $kind === 'investor' && $outcome === 'İlgileniyor' => 'interested',
            $kind === 'investor' && $outcome === 'Uygun değil' => 'rejected',
            default => 'active',
        };
    }

    private function matchesFilter(string $stage): bool
    {
        return match ($this->filter) {
            'waiting_seller' => in_array($stage, ['waiting_seller', 'waiting_seller_final'], true),
            'waiting_investor' => $stage === 'waiting_investor',
            'completed' => $stage === 'completed',
            'active' => ! in_array($stage, ['completed', 'rejected'], true),
            default => true,
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'waiting_seller' => 'Satıcı cevabı bekleniyor',
            'waiting_investor' => 'Yatırımcı cevabı bekleniyor',
            'waiting_seller_final' => 'Satıcı son teyidi',
            'completed' => 'Anlaşma sağlandı',
            'rejected' => 'Olumsuz sonuçlandı',
            'interested' => 'Yatırımcı ilgili',
            default => 'Görüşme sürüyor',
        };
    }

    private function nextAction(string $stage): string
    {
        return match ($stage) {
            'waiting_seller' => 'Emlak İş Merkezi’nden satıcıyı ara ve yatırımcı teklifini ilet.',
            'waiting_investor' => 'Yatırımcıyı ara ve satıcının karşı teklifini ilet.',
            'waiting_seller_final' => 'Satıcıyı ara ve işlemin son teyidini al.',
            'completed' => 'Tarafların işlem, tapu ve ödeme adımlarını insan kontrolünde planla.',
            'rejected' => 'Yeni yatırımcı eşleşmesini değerlendir; reddedilen kişiyi tekrar zorlama.',
            'interested' => 'Yatırımcıdan net nakit teklif tutarını öğren.',
            default => 'Emlak İş Merkezi’ndeki sıradaki görevi uygula.',
        };
    }

    private function tone(string $stage): string
    {
        return match ($stage) {
            'completed' => 'green',
            'waiting_seller', 'waiting_seller_final' => 'purple',
            'waiting_investor', 'interested' => 'blue',
            'rejected' => 'gray',
            default => 'orange',
        };
    }

    private function eventTitle(string $kind): string
    {
        return match ($kind) {
            'investor' => 'Yatırımcı görüşmesi',
            'seller_offer' => 'Satıcı teklif görüşmesi',
            'investor_counter' => 'Yatırımcı karşı teklif görüşmesi',
            'seller_final' => 'Satıcı son teyidi',
            default => 'Emlak görüşmesi',
        };
    }

    private function propertyLabel(array $data): string
    {
        $location = data_get($data, 'location') ?: data_get($data, 'property.location') ?: data_get($data, 'district');
        $type = data_get($data, 'property_type') ?: data_get($data, 'property.type') ?: 'taşınmaz';

        return trim(($location ? $location.' ' : '').$type);
    }

    public function getHeading(): string
    {
        return '';
    }
}
