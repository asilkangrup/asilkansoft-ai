<?php

namespace App\Console\Commands;

use App\Models\RealEstateMatchEvent;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RealEstateMatchLedger extends Command
{
    protected $signature = 'real-estate:match-ledger
        {--profile= : Seller or investor profile ID}
        {--status=active : active, removed or all}
        {--limit=30 : Maximum rows}';

    protected $description = 'Inspect the privacy-safe isolated real-estate match lifecycle ledger without sending messages.';

    public function handle(): int
    {
        if (! Schema::hasTable('real_estate_match_events')) {
            $this->error('real_estate_match_events table is not ready.');

            return self::FAILURE;
        }

        $status = strtolower(trim((string) $this->option('status')));

        if (! in_array($status, ['active', 'removed', 'all'], true)) {
            $this->error('--status must be active, removed or all.');

            return self::INVALID;
        }

        $profileId = $this->option('profile');
        $profileId = is_numeric($profileId) && (int) $profileId > 0
            ? (int) $profileId
            : null;
        $limit = max(1, min(200, (int) $this->option('limit')));

        $events = RealEstateMatchEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->when($profileId, function ($query) use ($profileId): void {
                $query->where(function ($query) use ($profileId): void {
                    $query
                        ->where('seller_profile_id', $profileId)
                        ->orWhere('investor_profile_id', $profileId);
                });
            })
            ->orderBy('id')
            ->get()
            ->groupBy('pair_key')
            ->map(fn (Collection $group): RealEstateMatchEvent => $group->last())
            ->when(
                $status !== 'all',
                fn (Collection $collection): Collection => $collection->where('status', $status)
            )
            ->sortByDesc('id')
            ->take($limit)
            ->values();

        $this->table(
            ['Pair', 'Seller', 'Investor', 'Status', 'Score', 'Grade', 'Changed'],
            $events->map(fn (RealEstateMatchEvent $event): array => [
                substr((string) $event->pair_key, 0, 12),
                $event->seller_profile_id,
                $event->investor_profile_id,
                $event->status,
                $event->match_score,
                $event->grade,
                $event->occurred_at?->toDateTimeString(),
            ])->all()
        );

        $telemetry = app(RealEstateMatchLedgerService::class)->telemetry24h();
        $this->line('24h events: '.$telemetry['events']);
        $this->line('Current active pairs: '.$telemetry['current_active_pairs']);
        $this->comment('No phone, email, WhatsApp number, document content or seller private floor is displayed by this command.');

        return self::SUCCESS;
    }
}
