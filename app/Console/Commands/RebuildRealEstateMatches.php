<?php

namespace App\Console\Commands;

use App\Models\RealEstateProfile;
use App\Services\RealEstateMatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('wai:real-estate-rebuild-matches {--limit=500}')]
#[Description('Yalnızca izole Emlak AI hesabındaki satıcı/yatırımcı fırsat eşleşmelerini yeniden hesaplar.')]
class RebuildRealEstateMatches extends Command
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    public function handle(RealEstateMatchService $matchService): int
    {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $profiles = RealEstateProfile::query()
            ->with('conversation')
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->whereIn('profile_type', ['seller', 'investor', 'buyer'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $matched = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            if (! $profile->conversation) {
                continue;
            }

            try {
                $matches = $matchService->process($profile->conversation);
                $processed++;

                if ($matches !== []) {
                    $matched++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('REAL ESTATE MATCH REBUILD FAILED', [
                    'real_estate_profile_id' => $profile->id,
                    'conversation_control_id' => $profile->conversation_control_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(
            "Emlak eşleşme yeniden hesaplama tamamlandı: {$processed} profil işlendi, "
            ."{$matched} profilde eşleşme bulundu, {$failed} hata."
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
