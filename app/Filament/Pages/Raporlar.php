<?php

namespace App\Filament\Pages;

use App\Models\ConversationControl;
use App\Services\CrmAnalyticsService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Raporlar extends Page
{
    protected string $view = 'filament.pages.raporlar';
    protected static ?string $title = 'Raporlar';
    protected static ?string $navigationLabel = 'Raporlar';
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static ?int $navigationSort = 34;

    public string $period = '30';
    public string $channelFilter = 'all';
    public string $botFilter = 'all';

    protected function analytics(): CrmAnalyticsService
    {
        return app(CrmAnalyticsService::class);
    }

    protected function startDate(): Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7' => now()->subDays(6)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            'all' => Carbon::create(2000, 1, 1)->startOfDay(),
            default => now()->subDays(29)->startOfDay(),
        };
    }

    protected function baseQuery(): Builder
    {
        return ConversationControl::query()
            ->with(['aiBot', 'assignedUser'])
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', $this->startDate())
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            );
    }

    public function getTotalLeadsProperty(): int
    {
        return $this->baseQuery()->count();
    }

    public function getOpenLeadsProperty(): int
    {
        return $this->baseQuery()->whereNotIn('lead_status', ['won', 'lost'])->count();
    }

    public function getHotLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_temperature', 'hot')->count();
    }

    public function getProposalLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'proposal')->count();
    }

    public function getWonLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'won')->count();
    }

    public function getLostLeadsProperty(): int
    {
        return $this->baseQuery()->where('lead_status', 'lost')->count();
    }

    public function getConversionRateProperty(): float
    {
        $total = $this->wonLeads + $this->lostLeads;

        if ($total === 0) {
            return 0;
        }

        return round(($this->wonLeads / $total) * 100, 1);
    }

    public function getTodayLeadsProperty(): int
    {
        return ConversationControl::query()
            ->where('user_id', auth()->id())
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            )
            ->count();
    }

    public function getAiMessageCountProperty(): int
    {
        return $this->analytics()->aiMessageCount(auth()->id(), $this->startDate());
    }

    public function getHumanMessageCountProperty(): int
    {
        return $this->analytics()->humanMessageCount(auth()->id(), $this->startDate());
    }

    public function getCustomerMessageCountProperty(): int
    {
        return $this->analytics()->customerMessageCount(auth()->id(), $this->startDate());
    }

    public function getAiResponseRateProperty(): float
    {
        return $this->analytics()->aiResponseRate(auth()->id(), $this->startDate());
    }

    public function getHumanResponseRateProperty(): float
    {
        return $this->analytics()->humanResponseRate(auth()->id(), $this->startDate());
    }

    public function getTakeoverCountProperty(): int
    {
        return $this->analytics()->takeoverCount(auth()->id(), $this->startDate());
    }

    public function getActiveHumanTakeoversProperty(): int
    {
        return $this->analytics()->activeHumanTakeovers(auth()->id());
    }

    public function getAverageResponseSecondsProperty(): float
    {
        return $this->analytics()->averageFirstResponseSeconds(auth()->id(), $this->startDate());
    }

    public function getAverageAiResponseSecondsProperty(): float
    {
        return $this->analytics()->averageAiResponseSeconds(auth()->id(), $this->startDate());
    }

    public function getAverageHumanResponseSecondsProperty(): float
    {
        return $this->analytics()->averageHumanResponseSeconds(auth()->id(), $this->startDate());
    }

    public function getAverageResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageResponseSeconds);
    }

    public function getAverageAiResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageAiResponseSeconds);
    }

    public function getAverageHumanResponseLabelProperty(): string
    {
        return $this->analytics()->formatSeconds($this->averageHumanResponseSeconds);
    }

    public function getStaffPerformanceProperty(): Collection
    {
        return $this->analytics()->staffPerformance(auth()->id(), $this->startDate());
    }

    public function getSevenDayTrendProperty(): array
    {
        $result = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            $count = ConversationControl::query()
                ->where('user_id', auth()->id())
                ->whereBetween('created_at', [
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ])
                ->when(
                    $this->channelFilter !== 'all',
                    fn (Builder $query) => $query->where('channel', $this->channelFilter)
                )
                ->when(
                    $this->botFilter !== 'all',
                    fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
                )
                ->count();

            $result[] = [
                'label' => $date->format('d.m'),
                'count' => $count,
            ];
        }

        return $result;
    }

    public function getChannelStatsProperty(): array
    {
        $channels = [
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'web' => 'Web',
        ];

        $total = max(1, $this->totalLeads);
        $rows = [];

        foreach ($channels as $key => $label) {
            $count = $this->baseQuery()->where('channel', $key)->count();

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        }

        return $rows;
    }

    public function getBotStatsProperty(): Collection
    {
        return ConversationControl::query()
            ->selectRaw(
                "
                ai_bot_id,
                COUNT(*) as total_leads,
                SUM(CASE WHEN lead_status = 'won' THEN 1 ELSE 0 END) as won_leads,
                SUM(CASE WHEN lead_temperature = 'hot' THEN 1 ELSE 0 END) as hot_leads
                "
            )
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', $this->startDate())
            ->when(
                $this->channelFilter !== 'all',
                fn (Builder $query) => $query->where('channel', $this->channelFilter)
            )
            ->when(
                $this->botFilter !== 'all',
                fn (Builder $query) => $query->where('ai_bot_id', (int) $this->botFilter)
            )
            ->groupBy('ai_bot_id')
            ->orderByDesc('total_leads')
            ->with('aiBot')
            ->get();
    }

    public function getBotsProperty(): Collection
    {
        return ConversationControl::query()
            ->with('aiBot')
            ->where('user_id', auth()->id())
            ->whereNotNull('ai_bot_id')
            ->get()
            ->pluck('aiBot')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function resetFilters(): void
    {
        $this->period = '30';
        $this->channelFilter = 'all';
        $this->botFilter = 'all';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }
}