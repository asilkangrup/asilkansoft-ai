<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Carbon;
use Throwable;

class RealEstateCommissionIntegrityService
{
    public function assess(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
    ): array {
        if (! $this->supportsPair($seller, $investor)) {
            return [];
        }

        $closing = app(RealEstateClosingService::class)->caseForPair(
            $seller->id,
            $investor->id,
        );
        $record = app(RealEstateCommissionService::class)->recordForPair(
            $seller->id,
            $investor->id,
        );

        if (! is_array($closing)) {
            return $this->result(
                seller: $seller,
                investor: $investor,
                level: 'waiting',
                signals: ['closing_case_not_opened'],
                action: 'open_closing_case_first',
                label: 'Komisyon/tahsilat kaydı için önce insan kontrollü kapanış dosyasını aç.',
                record: $record,
                closingCompleted: false,
                stale: false,
            );
        }

        $closingCompleted = (string) ($closing['status'] ?? '') === 'completed'
            && (bool) ($closing['final_payment_verified'] ?? false)
            && (bool) ($closing['deed_transfer_completed'] ?? false);
        $agreedPrice = is_numeric($closing['agreed_price'] ?? null)
            ? max(0, (int) $closing['agreed_price'])
            : 0;
        $sellerDue = $this->commissionAmount($agreedPrice, RealEstateCommissionService::SELLER_RATE_PERCENT);
        $buyerDue = $this->commissionAmount($agreedPrice, RealEstateCommissionService::BUYER_RATE_PERCENT);
        $totalDue = $sellerDue + $buyerDue;
        $signals = [];
        $stale = false;

        if (! is_array($record)) {
            if (! $closingCompleted) {
                return $this->result(
                    seller: $seller,
                    investor: $investor,
                    level: 'waiting',
                    signals: ['closing_not_completed'],
                    action: 'wait_for_verified_closing',
                    label: 'Tapu devri ve nihai ödeme insan tarafından doğrulanmadan tahsilat kaydı açma.',
                    record: null,
                    closingCompleted: false,
                    stale: false,
                );
            }

            return $this->result(
                seller: $seller,
                investor: $investor,
                level: 'warning',
                signals: ['collection_record_missing'],
                action: 'record_collection_status',
                label: 'Tamamlanan işlem için satıcı ve alıcı hizmet bedeli tahsilat durumunu insan kontrolüyle kaydet.',
                record: null,
                closingCompleted: true,
                stale: $this->closingStale($closing),
            );
        }

        $sellerCollected = max(0, (int) ($record['seller_collected_amount'] ?? 0));
        $buyerCollected = max(0, (int) ($record['buyer_collected_amount'] ?? 0));
        $totalCollected = $sellerCollected + $buyerCollected;
        $sellerRemaining = max(0, $sellerDue - $sellerCollected);
        $buyerRemaining = max(0, $buyerDue - $buyerCollected);
        $totalRemaining = $sellerRemaining + $buyerRemaining;

        if (! $closingCompleted && $totalCollected > 0) {
            $signals[] = 'collection_before_completed_closing';
        }

        if ((int) ($record['agreed_price'] ?? -1) !== $agreedPrice) {
            $signals[] = 'agreed_price_mismatch';
        }

        if (
            (int) ($record['seller_due_amount'] ?? -1) !== $sellerDue
            || (int) ($record['buyer_due_amount'] ?? -1) !== $buyerDue
            || (int) ($record['total_due_amount'] ?? -1) !== $totalDue
        ) {
            $signals[] = 'commission_due_mismatch';
        }

        if ($sellerCollected > $sellerDue || $buyerCollected > $buyerDue || $totalCollected > $totalDue) {
            $signals[] = 'over_collected';
        }

        if (
            (int) ($record['total_collected_amount'] ?? -1) !== $totalCollected
            || (int) ($record['seller_remaining_amount'] ?? -1) !== $sellerRemaining
            || (int) ($record['buyer_remaining_amount'] ?? -1) !== $buyerRemaining
            || (int) ($record['total_remaining_amount'] ?? -1) !== $totalRemaining
        ) {
            $signals[] = 'collection_arithmetic_mismatch';
        }

        $expectedStatus = match (true) {
            $totalDue > 0 && $totalCollected === $totalDue => 'collected',
            $totalCollected > 0 => 'partial',
            default => 'awaiting',
        };
        if ((string) ($record['status'] ?? '') !== $expectedStatus) {
            $signals[] = 'collection_status_mismatch';
        }

        if ($sellerCollected > 0 && ! $this->date($record['seller_collected_at'] ?? null)) {
            $signals[] = 'seller_collection_date_missing';
        }
        if ($buyerCollected > 0 && ! $this->date($record['buyer_collected_at'] ?? null)) {
            $signals[] = 'buyer_collection_date_missing';
        }
        if ($this->dateInFuture($record['seller_collected_at'] ?? null)) {
            $signals[] = 'seller_collection_date_future';
        }
        if ($this->dateInFuture($record['buyer_collected_at'] ?? null)) {
            $signals[] = 'buyer_collection_date_future';
        }

        if ($closingCompleted && $totalRemaining > 0) {
            $updatedAt = $this->date($record['updated_at'] ?? null)
                ?: $this->date($closing['closed_at'] ?? null)
                ?: $this->date($closing['updated_at'] ?? null);
            $stale = ! $updatedAt || $updatedAt->lt(now()->subHours(72));
            if ($stale) {
                $signals[] = 'collection_stale';
            }
        }

        if ($signals === []) {
            if (! $closingCompleted) {
                $signals[] = 'closing_not_completed';
            } elseif ($expectedStatus === 'collected') {
                $signals[] = 'collection_integrity_current';
            } elseif ($expectedStatus === 'partial') {
                $signals[] = 'partial_collection_remaining';
            } else {
                $signals[] = 'collection_not_started';
            }
        }

        [$level, $action, $label] = $this->decision($signals, $closingCompleted, $expectedStatus);

        return $this->result(
            seller: $seller,
            investor: $investor,
            level: $level,
            signals: $signals,
            action: $action,
            label: $label,
            record: $record,
            closingCompleted: $closingCompleted,
            stale: $stale,
        );
    }

    public function health(): array
    {
        $counts = [
            'accepted_deals' => 0,
            'closing_completed' => 0,
            'collection_records' => 0,
            'fully_collected' => 0,
            'partial' => 0,
            'awaiting' => 0,
            'waiting_closing' => 0,
            'critical' => 0,
            'warning' => 0,
            'integrity_mismatch' => 0,
            'collection_before_closing' => 0,
            'record_missing' => 0,
            'stale' => 0,
        ];

        foreach (app(RealEstateClosingService::class)->acceptedDeals() as $deal) {
            $seller = $deal['seller'] ?? null;
            $investor = $deal['investor'] ?? null;
            if (! $seller instanceof RealEstateProfile || ! $investor instanceof RealEstateProfile) {
                continue;
            }

            $counts['accepted_deals']++;
            $assessment = $this->assess($seller, $investor);
            if ($assessment === []) {
                continue;
            }

            if ((bool) ($assessment['closing_completed'] ?? false)) {
                $counts['closing_completed']++;
            }
            if ((bool) ($assessment['record_exists'] ?? false)) {
                $counts['collection_records']++;
            }

            $collectionStatus = (string) ($assessment['collection_status'] ?? '');
            if ($collectionStatus === 'collected') {
                $counts['fully_collected']++;
            } elseif ($collectionStatus === 'partial') {
                $counts['partial']++;
            } elseif ($collectionStatus === 'awaiting') {
                $counts['awaiting']++;
            }

            $level = (string) ($assessment['risk_level'] ?? 'waiting');
            if (array_key_exists($level, $counts)) {
                $counts[$level]++;
            }
            if ($level === 'waiting') {
                $counts['waiting_closing']++;
            }

            $signals = (array) ($assessment['signal_codes'] ?? []);
            $integritySignals = [
                'agreed_price_mismatch',
                'commission_due_mismatch',
                'over_collected',
                'collection_arithmetic_mismatch',
                'collection_status_mismatch',
                'seller_collection_date_missing',
                'buyer_collection_date_missing',
                'seller_collection_date_future',
                'buyer_collection_date_future',
            ];
            if (array_intersect($integritySignals, $signals) !== []) {
                $counts['integrity_mismatch']++;
            }
            if (in_array('collection_before_completed_closing', $signals, true)) {
                $counts['collection_before_closing']++;
            }
            if (in_array('collection_record_missing', $signals, true)) {
                $counts['record_missing']++;
            }
            if ((bool) ($assessment['stale'] ?? false)) {
                $counts['stale']++;
            }
        }

        return [
            'ready' => true,
            'state' => match (true) {
                $counts['critical'] > 0 => 'human_attention_required',
                $counts['warning'] > 0 => 'collection_attention_required',
                default => 'healthy',
            },
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'counts' => $counts,
            'automatic_outbound_allowed' => false,
            'automatic_payment_request_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
            'live_traffic_blocking' => false,
        ];
    }

    private function decision(array $signals, bool $closingCompleted, string $status): array
    {
        $critical = [
            'collection_before_completed_closing' => [
                'stop_collection_and_verify_closing',
                'Kapanış tamamlanmadan tahsilat kaydı var. Ödeme/devir durumunu ve kaydı insan kontrolüyle doğrula; otomatik işlem yapma.',
            ],
            'over_collected' => [
                'resolve_over_collection',
                'Tahsil edilen tutar hesaplanan hizmet bedelini aşıyor. Kaydı ve muhasebe belgesini insan kontrolüyle düzelt.',
            ],
            'agreed_price_mismatch' => [
                'reconcile_agreed_price_and_commission',
                'Kapanış satış bedeli ile tahsilat kaydındaki bedel farklı. Hakedişi insan kontrolüyle yeniden uzlaştır.',
            ],
            'commission_due_mismatch' => [
                'recalculate_commission_entitlement',
                'Kaydedilmiş hizmet bedeli ile güncel %2 + %2 hakediş hesabı uyuşmuyor. İnsan kontrolüyle yeniden hesapla.',
            ],
            'collection_arithmetic_mismatch' => [
                'repair_collection_arithmetic',
                'Tahsil edilen/kalan tutarların aritmetiği tutarsız. Kaydı insan kontrolüyle düzelt.',
            ],
            'collection_status_mismatch' => [
                'repair_collection_status',
                'Tahsilat durumu ile tutarlar uyuşmuyor. Durumu insan kontrolüyle düzelt.',
            ],
        ];

        foreach ($critical as $signal => [$action, $label]) {
            if (in_array($signal, $signals, true)) {
                return ['critical', $action, $label];
            }
        }

        foreach ([
            'seller_collection_date_future',
            'buyer_collection_date_future',
            'seller_collection_date_missing',
            'buyer_collection_date_missing',
        ] as $signal) {
            if (in_array($signal, $signals, true)) {
                return [
                    'warning',
                    'verify_collection_evidence_dates',
                    'Tahsilat tarihi veya belge kaydını insan kontrolüyle doğrula; müşteri tarafına otomatik mesaj gönderme.',
                ];
            }
        }

        if (in_array('collection_record_missing', $signals, true)) {
            return [
                'warning',
                'record_collection_status',
                'Tamamlanan işlem için satıcı ve alıcı hizmet bedeli tahsilat durumunu insan kontrolüyle kaydet.',
            ];
        }

        if (in_array('collection_stale', $signals, true)) {
            return [
                'warning',
                'review_stale_collection',
                'Tamamlanmış işlemde kalan hizmet bedeli 72 saattir güncellenmemiş. Son gerçek durumu insan kontrolüyle doğrula.',
            ];
        }

        if ($closingCompleted && in_array('partial_collection_remaining', $signals, true)) {
            return [
                'warning',
                'review_remaining_collection',
                'Kısmi tahsilat var. Kalan hizmet bedelinin gerçek durumunu insan kontrolüyle güncelle.',
            ];
        }

        if ($closingCompleted && in_array('collection_not_started', $signals, true)) {
            return [
                'warning',
                'record_collection_status',
                'Kapanış tamamlandı. Tahsilat durumunu insan kontrolüyle kaydet.',
            ];
        }

        if (! $closingCompleted || in_array('closing_not_completed', $signals, true)) {
            return [
                'waiting',
                'wait_for_verified_closing',
                'Tapu devri ve nihai ödeme insan tarafından doğrulanmadan tahsilat işlemini ilerletme.',
            ];
        }

        if ($status === 'collected') {
            return [
                'collected',
                'archive_collection_record',
                'Tahsilat tutarları güncel ve tam. Kaydı denetim amacıyla sakla.',
            ];
        }

        return [
            'warning',
            'review_collection_status',
            'Tahsilat durumunu insan kontrolüyle doğrula.',
        ];
    }

    private function result(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        string $level,
        array $signals,
        string $action,
        string $label,
        ?array $record,
        bool $closingCompleted,
        bool $stale,
    ): array {
        return [
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'risk_level' => $level,
            'risk_label' => $this->riskLabel($level),
            'signal_codes' => array_values(array_unique($signals)),
            'next_best_action' => $action,
            'next_best_action_label' => $label,
            'closing_completed' => $closingCompleted,
            'record_exists' => is_array($record),
            'collection_status' => is_array($record) ? (string) ($record['status'] ?? '') : null,
            'stale' => $stale,
            'human_confirmation_required' => true,
            'automatic_outbound_allowed' => false,
            'automatic_payment_request_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_private_seller_floor' => false,
            'contains_customer_pii' => false,
        ];
    }

    private function supportsPair(RealEstateProfile $seller, RealEstateProfile $investor): bool
    {
        return $seller->belongsToIsolatedProductionScope()
            && $investor->belongsToIsolatedProductionScope()
            && $seller->profile_type === 'seller'
            && in_array($investor->profile_type, ['investor', 'buyer'], true);
    }

    private function commissionAmount(int $agreedPrice, int $rate): int
    {
        return (int) round($agreedPrice * ($rate / 100));
    }

    private function closingStale(array $closing): bool
    {
        $updatedAt = $this->date($closing['closed_at'] ?? null)
            ?: $this->date($closing['updated_at'] ?? null);

        return ! $updatedAt || $updatedAt->lt(now()->subHours(72));
    }

    private function dateInFuture(mixed $value): bool
    {
        $date = $this->date($value);

        return $date?->gt(now()->addMinutes(5)) ?? false;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function riskLabel(string $level): string
    {
        return match ($level) {
            'critical' => 'Kritik tahsilat kontrolü gerekiyor',
            'warning' => 'Tahsilat operatör aksiyonu gerekiyor',
            'collected' => 'Tahsilat bütünlüğü doğrulandı',
            default => 'Kapanış tamamlanması bekleniyor',
        };
    }
}
