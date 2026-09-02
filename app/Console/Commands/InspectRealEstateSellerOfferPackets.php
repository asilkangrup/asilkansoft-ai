<?php

namespace App\Console\Commands;

use App\Models\RealEstateProfile;
use App\Services\RealEstateSellerOfferPacketService;
use Illuminate\Console\Command;

class InspectRealEstateSellerOfferPackets extends Command
{
    protected $signature = 'real-estate:offer-packets {--json : JSON çıktısı üret}';

    protected $description = 'İzole Emlak AI satıcı dosyalarının PII içermeyen yatırımcı teklif hazırlık özetini gösterir.';

    public function handle(RealEstateSellerOfferPacketService $service): int
    {
        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('profile_type', 'seller')
            ->get();

        $statuses = [
            'ready' => 0,
            'nearly_ready' => 0,
            'building' => 0,
            'early' => 0,
        ];
        $readyForOffer = 0;
        $missingCritical = [];
        $missingSupporting = [];

        foreach ($profiles as $profile) {
            $summary = $service->summaryForProfile($profile);
            $status = (string) ($summary['status'] ?? 'early');

            if (! array_key_exists($status, $statuses)) {
                $status = 'early';
            }

            $statuses[$status]++;

            if ((bool) ($summary['ready_for_investor_offer'] ?? false)) {
                $readyForOffer++;
            }

            foreach (($summary['missing_critical_for_offer'] ?? []) as $field) {
                if (is_string($field) && $field !== '') {
                    $missingCritical[$field] = ($missingCritical[$field] ?? 0) + 1;
                }
            }

            foreach (($summary['missing_supporting_context'] ?? []) as $field) {
                if (is_string($field) && $field !== '') {
                    $missingSupporting[$field] = ($missingSupporting[$field] ?? 0) + 1;
                }
            }
        }

        arsort($missingCritical);
        arsort($missingSupporting);

        $result = [
            'scope' => [
                'user_id' => 40,
                'organization_id' => 37,
                'bot_id' => 35,
                'instance' => 'emlak-ai-35',
            ],
            'seller_profiles' => $profiles->count(),
            'ready_for_investor_offer' => $readyForOffer,
            'statuses' => $statuses,
            'missing_critical_counts' => $missingCritical,
            'missing_supporting_counts' => $missingSupporting,
            'contains_customer_pii' => false,
            'follow_up_scheduling_allowed' => false,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return self::SUCCESS;
        }

        $this->info('Emlak AI satıcı teklif dosyası özeti');
        $this->line('Satıcı profili: '.$result['seller_profiles']);
        $this->line('Yatırımcı ön teklifine hazır: '.$result['ready_for_investor_offer']);
        $this->line('Durumlar: '.json_encode($statuses, JSON_UNESCAPED_UNICODE));
        $this->line('Eksik kritik alanlar: '.json_encode($missingCritical, JSON_UNESCAPED_UNICODE));
        $this->line('Eksik destekleyici alanlar: '.json_encode($missingSupporting, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
