<?php

namespace App\Console\Commands;

use App\Models\RealEstateProfile;
use App\Services\RealEstateMatchService;
use App\Services\RealEstateMatchVerificationFilterService;
use App\Services\RealEstateVerificationDecisionGuardService;
use App\Services\RealEstateVerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('wai:real-estate-rebuild-matches {--limit=500}')]
#[Description('İzole Emlak AI hesabında doğrulama/risk kontrolünü ve güvenli satıcı-yatırımcı eşleşmelerini yeniden hesaplar.')]
class RebuildRealEstateMatches extends Command
{
    private const REAL_ESTATE_USER_ID = 40;

    private const REAL_ESTATE_BOT_ID = 35;

    public function handle(
        RealEstateVerificationService $verificationService,
        RealEstateVerificationDecisionGuardService $decisionGuardService,
        RealEstateMatchService $matchService,
        RealEstateMatchVerificationFilterService $matchFilterService,
    ): int {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $profiles = RealEstateProfile::query()
            ->with('conversation')
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('ai_bot_id', self::REAL_ESTATE_BOT_ID)
            ->whereIn('profile_type', ['seller', 'investor', 'buyer'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $verified = 0;
        $processed = 0;
        $matched = 0;
        $failed = 0;

        // First verify every seller so investor-side matching never sees stale
        // or unreviewed seller eligibility during the second pass.
        foreach ($profiles->where('profile_type', 'seller') as $profile) {
            if (! $profile->conversation) {
                continue;
            }

            try {
                $verificationService->process($profile->conversation);
                $decisionGuardService->process($profile->conversation);
                $verified++;
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('REAL ESTATE VERIFICATION REBUILD FAILED', [
                    'real_estate_profile_id' => $profile->id,
                    'conversation_control_id' => $profile->conversation_control_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($profiles as $profile) {
            if (! $profile->conversation) {
                continue;
            }

            try {
                $matchService->process($profile->conversation);
                $matches = $matchFilterService->process($profile->conversation);
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
            "Emlak doğrulama/eşleşme yeniden hesaplama tamamlandı: {$verified} satıcı doğrulandı, "
            ."{$processed} profil işlendi, {$matched} profilde güvenli eşleşme bulundu, {$failed} hata."
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
