<?php

namespace App\Console\Commands;

use App\Models\RealEstateProfile;
use App\Services\RealEstateDecisionService;
use App\Services\RealEstateMatchService;
use App\Services\RealEstateMatchValuationFreshnessFilterService;
use App\Services\RealEstateMatchVerificationFilterService;
use App\Services\RealEstateValuationDecisionGuardService;
use App\Services\RealEstateValuationFreshnessService;
use App\Services\RealEstateVerificationDecisionGuardService;
use App\Services\RealEstateVerificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('wai:real-estate-rebuild-matches {--limit=500}')]
#[Description('İzole Emlak AI hesabında değerleme tazeliği, doğrulama/risk kontrolü ve güvenli satıcı-yatırımcı eşleşmelerini yeniden hesaplar.')]
class RebuildRealEstateMatches extends Command
{
    public function handle(
        RealEstateValuationFreshnessService $valuationFreshnessService,
        RealEstateDecisionService $decisionService,
        RealEstateValuationDecisionGuardService $valuationDecisionGuardService,
        RealEstateVerificationService $verificationService,
        RealEstateVerificationDecisionGuardService $verificationDecisionGuardService,
        RealEstateMatchService $matchService,
        RealEstateMatchValuationFreshnessFilterService $matchValuationFilterService,
        RealEstateMatchVerificationFilterService $matchVerificationFilterService,
    ): int {
        $limit = max(1, min(5000, (int) $this->option('limit')));

        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->whereIn('profile_type', ['seller', 'investor', 'buyer'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $valuationCounts = [
            'fresh' => 0,
            'stale' => 0,
            'missing' => 0,
            'out_of_scope' => 0,
        ];
        $verified = 0;
        $processed = 0;
        $matched = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            try {
                $freshness = $valuationFreshnessService->refreshMetadata($profile);
                $status = (string) ($freshness['status'] ?? 'missing');
                $valuationCounts[$status] = ($valuationCounts[$status] ?? 0) + 1;
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('REAL ESTATE VALUATION FRESHNESS REBUILD FAILED', [
                    'real_estate_profile_id' => $profile->id,
                    'conversation_control_id' => $profile->conversation_control_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // First verify every seller and rebuild decision guards so investor-side
        // matching never sees stale valuation or unreviewed seller eligibility.
        foreach ($profiles->where('profile_type', 'seller') as $profile) {
            if (! $profile->conversation) {
                continue;
            }

            try {
                $decisionService->process($profile->conversation);
                $valuationDecisionGuardService->process($profile->conversation);
                $verificationService->process($profile->conversation);
                $verificationDecisionGuardService->process($profile->conversation);
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
                $matchValuationFilterService->process($profile->conversation);
                $matches = $matchVerificationFilterService->process($profile->conversation);
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
            'Emlak yeniden hesaplama tamamlandı: '
            ."değerleme fresh={$valuationCounts['fresh']}, stale={$valuationCounts['stale']}, missing={$valuationCounts['missing']}; "
            ."{$verified} satıcı karar/doğrulama kontrolünden geçti, {$processed} profil işlendi, "
            ."{$matched} profilde güvenli eşleşme bulundu, {$failed} hata."
        );

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
