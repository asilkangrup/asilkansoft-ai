<?php

namespace App\Console\Commands;

use App\Models\RealEstateNegotiationEvent;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RealEstateNegotiationTimeline extends Command
{
    protected $signature = 'real-estate:negotiation-timeline
        {--profile= : Real-estate profile id}
        {--limit=25 : Maximum rows, 1-100}';

    protected $description = 'Shows a privacy-safe negotiation timeline for the isolated Emlak AI account.';

    public function handle(): int
    {
        if (! Schema::hasTable('real_estate_negotiation_events')) {
            $this->error('real_estate_negotiation_events table is not available.');

            return self::FAILURE;
        }

        $limit = max(1, min(100, (int) $this->option('limit')));
        $profileId = trim((string) ($this->option('profile') ?? ''));

        $query = RealEstateNegotiationEvent::query()
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
                ->whereKey((int) $profileId)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->first();

            if (! $profile) {
                $this->error('Profile is outside the isolated production scope or does not exist.');

                return self::FAILURE;
            }

            $query->where('real_estate_profile_id', $profile->id);
        }

        $events = $query->limit($limit)->get();

        if ($events->isEmpty()) {
            $this->info('No isolated negotiation events found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Profile', 'Event', 'Direction', 'Value', 'Occurred'],
            $events->map(function (RealEstateNegotiationEvent $event): array {
                $value = $event->event_type === 'seller_minimum_price'
                    ? '[CONFIDENTIAL FLOOR UPDATED]'
                    : ($event->numeric_value !== null
                        ? number_format((float) $event->numeric_value, 0, ',', '.').' TL'
                        : (string) ($event->text_value ?? '-'));

                return [
                    $event->id,
                    $event->real_estate_profile_id,
                    $event->event_type,
                    $event->direction,
                    $value,
                    $event->occurred_at?->toIso8601String() ?? '-',
                ];
            })->all()
        );

        $this->newLine();
        $this->comment('Customer-sourced positions are CRM memory, not binding offers or acceptances. Seller floor amounts are intentionally hidden.');

        return self::SUCCESS;
    }
}
