<?php

namespace App\Console\Commands;

use App\Models\RealEstateProfile;
use App\Services\RealEstateSellerInvestorHandoffService;
use Illuminate\Console\Command;

class InspectRealEstateInvestorHandoffs extends Command
{
    protected $signature = 'real-estate:investor-handoffs {--json : JSON çıktısı üret}';

    protected $description = 'İzole Emlak AI satıcı dosyalarının PII içermeyen yatırımcı handoff durumunu gösterir.';

    public function handle(RealEstateSellerInvestorHandoffService $service): int
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->get();

        $statuses = [
            'ready' => 0,
            'investor_sourcing' => 0,
            'comparable_review' => 0,
            'valuation_required' => 0,
            'evidence_required' => 0,
            'verification_required' => 0,
            'confirmation_required' => 0,
            'packet_incomplete' => 0,
        ];
        $candidateCount = 0;

        foreach ($profiles as $profile) {
            $summary = $service->summaryForProfile($profile);
            $status = (string) ($summary['status'] ?? 'packet_incomplete');

            if (! array_key_exists($status, $statuses)) {
                $status = 'packet_incomplete';
            }

            $statuses[$status]++;
            $candidateCount += max(0, (int) ($summary['candidate_count'] ?? 0));
        }

        $result = [
            'scope' => [
                'user_id' => 40,
                'organization_id' => 37,
                'bot_id' => 35,
                'instance' => 'emlak-ai-35',
            ],
            'seller_profiles' => $profiles->count(),
            'ready_for_operator_handoff' => $statuses['ready'],
            'eligible_candidate_refs' => $candidateCount,
            'statuses' => $statuses,
            'contains_customer_pii' => false,
            'automatic_investor_outreach_allowed' => false,
            'automatic_customer_follow_up_allowed' => false,
            'human_review_required_before_investor_contact' => true,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $this->info('Emlak AI yatırımcı handoff özeti');
        $this->line('Satıcı profili: '.$result['seller_profiles']);
        $this->line('Operatör handoff hazır: '.$result['ready_for_operator_handoff']);
        $this->line('Uygun aday referansı: '.$result['eligible_candidate_refs']);
        $this->line('Durumlar: '.json_encode($statuses, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
