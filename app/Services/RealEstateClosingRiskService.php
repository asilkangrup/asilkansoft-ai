<?php

namespace App\Services;

use App\Models\RealEstateProfile;
use Illuminate\Support\Carbon;
use Throwable;

class RealEstateClosingRiskService
{
    public function assess(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        ?array $case = null,
    ): array {
        if (! $this->supportsPair($seller, $investor)) {
            return [];
        }

        $authorization = app(RealEstateAuthorizationService::class)->readiness($seller);
        $authorizationStatus = (string) ($authorization['status'] ?? 'incomplete');
        $authorizationExpired = $authorizationStatus === 'expired'
            || $this->storedAuthorizationExpired($seller);
        $case ??= app(RealEstateClosingService::class)->caseForPair($seller->id, $investor->id);
        $signals = [];

        if ($authorizationExpired) {
            $signals[] = 'authorization_expired';
        } elseif (! (bool) ($authorization['ready'] ?? false)) {
            $signals[] = 'authorization_not_ready';
        }

        if (! is_array($case)) {
            $signals[] = 'closing_case_not_opened';
            [$level, $action, $label] = $this->decision($signals);

            return $this->result($seller, $investor, null, $level, $signals, $action, $label, false);
        }

        if (! $this->supportsCase($case)) {
            return [];
        }

        $completed = (string) ($case['status'] ?? '') === 'completed'
            && (bool) ($case['final_payment_verified'] ?? false)
            && (bool) ($case['deed_transfer_completed'] ?? false);

        if ($completed) {
            return $this->result(
                $seller,
                $investor,
                $case,
                'completed',
                ['closing_completed'],
                'archive_completed_case',
                'İşlem tamamlandı. Dosyayı yalnız kayıt ve denetim amacıyla sakla.',
                false,
            );
        }

        $agreedPrice = is_numeric($case['agreed_price'] ?? null) ? (int) $case['agreed_price'] : 0;
        $depositAmount = is_numeric($case['deposit_amount'] ?? null) ? (int) $case['deposit_amount'] : 0;
        $depositReceived = (bool) ($case['deposit_received'] ?? false);
        $finalPayment = (bool) ($case['final_payment_verified'] ?? false);
        $deedTransfer = (bool) ($case['deed_transfer_completed'] ?? false);

        if ($depositAmount > 0 && $agreedPrice > 0 && $depositAmount > $agreedPrice) {
            $signals[] = 'deposit_exceeds_agreed_price';
        }
        if ($depositReceived && $depositAmount <= 0) {
            $signals[] = 'deposit_received_without_amount';
        }
        if ($deedTransfer && ! $finalPayment) {
            $signals[] = 'deed_transfer_without_final_payment';
        } elseif ($finalPayment && ! $deedTransfer) {
            $signals[] = 'final_payment_pending_deed_transfer';
        }

        $criticalChecks = [
            'title_deed_verified',
            'identity_authority_verified',
            'encumbrance_checked',
            'tax_fee_checked',
            'payment_method_confirmed',
        ];
        if (collect($criticalChecks)->contains(fn (string $field): bool => ! (bool) ($case[$field] ?? false))) {
            $signals[] = 'critical_checks_incomplete';
        }

        $appointment = $this->date($case['appointment_at'] ?? null);
        if (! $appointment) {
            $signals[] = 'appointment_missing';
        } elseif ($appointment->isPast()) {
            $signals[] = 'appointment_missed_or_unconfirmed';
        }

        $updatedAt = $this->date($case['updated_at'] ?? null);
        $stale = $updatedAt ? $updatedAt->lt(now()->subHours(48)) : true;
        if ($stale) {
            $signals[] = 'closing_case_stale';
        }
        if ($signals === []) {
            $signals[] = 'closing_controls_current';
        }

        [$level, $action, $label] = $this->decision($signals);

        return $this->result($seller, $investor, $case, $level, $signals, $action, $label, $stale);
    }

    public function health(): array
    {
        $counts = [
            'accepted_deals' => 0,
            'cases_opened' => 0,
            'completed' => 0,
            'critical' => 0,
            'warning' => 0,
            'ready' => 0,
            'case_not_opened' => 0,
            'authorization_not_ready' => 0,
            'appointment_missed_or_unconfirmed' => 0,
            'stale' => 0,
        ];

        foreach (app(RealEstateClosingService::class)->acceptedDeals() as $deal) {
            $seller = $deal['seller'] ?? null;
            $investor = $deal['investor'] ?? null;
            if (! $seller instanceof RealEstateProfile || ! $investor instanceof RealEstateProfile) {
                continue;
            }

            $counts['accepted_deals']++;
            $case = app(RealEstateClosingService::class)->caseForPair($seller->id, $investor->id);
            if (is_array($case)) {
                $counts['cases_opened']++;
            }

            $assessment = $this->assess($seller, $investor, $case);
            if ($assessment === []) {
                continue;
            }

            $level = (string) ($assessment['risk_level'] ?? 'warning');
            if (array_key_exists($level, $counts)) {
                $counts[$level]++;
            }

            $signals = (array) ($assessment['signal_codes'] ?? []);
            $counts['case_not_opened'] += in_array('closing_case_not_opened', $signals, true) ? 1 : 0;
            $counts['authorization_not_ready'] += (
                in_array('authorization_not_ready', $signals, true)
                || in_array('authorization_expired', $signals, true)
            ) ? 1 : 0;
            $counts['appointment_missed_or_unconfirmed'] += in_array(
                'appointment_missed_or_unconfirmed',
                $signals,
                true
            ) ? 1 : 0;
            $counts['stale'] += (bool) ($assessment['stale'] ?? false) ? 1 : 0;
        }

        return [
            'ready' => true,
            'state' => $counts['critical'] > 0 ? 'human_attention_required' : 'healthy',
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'counts' => $counts,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_payload' => false,
            'contains_customer_pii' => false,
            'live_traffic_blocking' => false,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function decision(array $signals): array
    {
        $priority = [
            'authorization_expired' => [
                'critical', 'renew_seller_authorization',
                'Geçerli satıcı yetkilendirmesini yenile; kapanış ilerlemesini otomatik olarak sürdürme.',
            ],
            'authorization_not_ready' => [
                'critical', 'complete_seller_authorization',
                'Satıcı yetkilendirme kontrolünü tamamla; kapanış ilerlemesini otomatik olarak sürdürme.',
            ],
            'deed_transfer_without_final_payment' => [
                'critical', 'resolve_payment_transfer_inconsistency',
                'Tapu devri ile nihai ödeme kaydı arasındaki kritik tutarsızlığı insan kontrolüyle çöz.',
            ],
            'deposit_exceeds_agreed_price' => [
                'critical', 'resolve_payment_amount_inconsistency',
                'Kapora ve anlaşılan satış bedeli tutarlarını insan kontrolüyle düzelt; ödeme/devir adımını otomatik ilerletme.',
            ],
            'deposit_received_without_amount' => [
                'critical', 'verify_deposit_record',
                'Kapora tahsilat kaydını ve tutarını insan kontrolüyle doğrula.',
            ],
            'appointment_missed_or_unconfirmed' => [
                'critical', 'resolve_missed_appointment',
                'Geçmiş tapu randevusunun sonucunu insan kontrolüyle teyit et; gerekirse yeni randevu planla.',
            ],
            'critical_checks_incomplete' => [
                'warning', 'verify_closing_documents_and_payment_plan',
                'Tapu, taraf yetkisi, takyidat, harç ve güvenli ödeme kontrollerindeki eksikleri insan doğrulamasıyla tamamla.',
            ],
            'final_payment_pending_deed_transfer' => [
                'warning', 'confirm_deed_transfer',
                'Nihai ödeme doğrulandı. Resmî tapu devrini ayrıca insan kontrolüyle teyit et.',
            ],
            'appointment_missing' => [
                'warning', 'schedule_deed_appointment',
                'Tapu randevusunu insan tarafından teyit ederek planla.',
            ],
            'closing_case_stale' => [
                'warning', 'review_stale_closing_case',
                'Kapanış dosyası 48 saattir güncellenmemiş. Son gerçek durumu insan kontrolüyle doğrula.',
            ],
            'closing_case_not_opened' => [
                'warning', 'open_closing_case',
                'Kabul edilmiş gerçek teklif için insan kontrollü kapanış dosyasını aç.',
            ],
        ];

        foreach ($priority as $signal => $decision) {
            if (in_array($signal, $signals, true)) {
                return $decision;
            }
        }

        return [
            'ready',
            'continue_closing_controls',
            'Kapanış kontrollerini insan doğrulamasıyla sürdür.',
        ];
    }

    private function result(
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        ?array $case,
        string $riskLevel,
        array $signals,
        string $nextAction,
        string $nextActionLabel,
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
            'closing_case_id' => is_array($case) ? ($case['id'] ?? null) : null,
            'risk_level' => $riskLevel,
            'risk_label' => $this->riskLabel($riskLevel),
            'signal_codes' => array_values(array_unique($signals)),
            'next_best_action' => $nextAction,
            'next_best_action_label' => $nextActionLabel,
            'stale' => $stale,
            'human_confirmation_required' => true,
            'automatic_outbound_allowed' => false,
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

    private function supportsCase(array $case): bool
    {
        return (int) ($case['user_id'] ?? 0) === RealEstateIsolationService::USER_ID
            && (int) ($case['organization_id'] ?? 0) === RealEstateIsolationService::ORGANIZATION_ID
            && (int) ($case['ai_bot_id'] ?? 0) === RealEstateIsolationService::BOT_ID;
    }

    private function storedAuthorizationExpired(RealEstateProfile $seller): bool
    {
        $expiresAt = data_get($seller->data, 'authorization_control.expires_at');
        $expires = $this->date($expiresAt);

        return $expires?->endOfDay()->isPast() ?? false;
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
            'critical' => 'Kritik insan kontrolü gerekiyor',
            'warning' => 'Operatör aksiyonu gerekiyor',
            'completed' => 'İşlem tamamlandı',
            default => 'Kapanış kontrolleri güncel',
        };
    }
}
