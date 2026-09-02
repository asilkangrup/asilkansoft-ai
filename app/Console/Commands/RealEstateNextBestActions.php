<?php

namespace App\Console\Commands;

use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RealEstateNextBestActions extends Command
{
    protected $signature = 'real-estate:next-actions
        {--profile= : Real-estate profile id}
        {--limit=25 : Maximum rows, 1-100}';

    protected $description = 'Shows privacy-safe deterministic next-best-action history for the isolated Emlak AI account.';

    public function handle(): int
    {
        if (! Schema::hasTable('real_estate_next_best_action_events')) {
            $this->error('real_estate_next_best_action_events table is not available.');

            return self::FAILURE;
        }

        $limit = max(1, min(100, (int) $this->option('limit')));
        $profileId = trim((string) ($this->option('profile') ?? ''));

        $query = RealEstateNextBestActionEvent::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->latest('id');

        if ($profileId !== '') {
            if (! ctype_digit($profileId)) {
                $this->error('--profile must be a numeric profile id.');

                return self::INVALID;
            }

            $profile = RealEstateProfile::query()
                ->isolatedProduction()
                ->whereKey((int) $profileId)
                ->first();

            if (! $profile || ! $profile->belongsToIsolatedProductionScope()) {
                $this->error('Profile is outside the isolated production scope or does not exist.');

                return self::FAILURE;
            }

            $query->where('real_estate_profile_id', $profile->id);
        }

        $events = $query->limit($limit)->get();

        if ($events->isEmpty()) {
            $this->info('No isolated next-best-action events found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Profile', 'Action', 'Priority', 'Stage', 'Reasons', 'Occurred'],
            $events->map(function (RealEstateNextBestActionEvent $event): array {
                return [
                    $event->id,
                    $event->real_estate_profile_id,
                    $event->action_code,
                    $event->priority,
                    $event->stage ?: '-',
                    implode(',', array_values($event->reason_codes ?? [])),
                    $event->occurred_at?->toIso8601String() ?? '-',
                ];
            })->all()
        );

        $this->newLine();
        $this->comment('Ledger rows contain action codes/reasons only. Raw customer text, contact details and seller private-floor values are intentionally excluded. No follow-up is scheduled by this command.');

        return self::SUCCESS;
    }
}
