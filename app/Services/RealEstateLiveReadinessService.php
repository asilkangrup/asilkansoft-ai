<?php

namespace App\Services;

use App\Models\RealEstateOutboundDelivery;

class RealEstateLiveReadinessService extends RealEstateReadinessService
{
    public function snapshot(): array
    {
        $snapshot = parent::snapshot();
        $instanceValid = (bool) data_get(
            $snapshot,
            'checks.instance_valid',
            false
        );
        $state = $instanceValid
            ? app(RealEstateEvolutionConnectionStateService::class)->state()
            : 'invalid_instance';
        $connected = $state === 'open';
        $factConsistencyReady = class_exists(RealEstateFactConsistencyService::class)
            && class_exists(RealEstateFactConsistencyActionService::class);
        $mediaAdmissionGateReady = app(RealEstateMediaAnalysisService::class)
            instanceof RealEstateGuardedMediaAnalysisService;
        $outboundState = $this->outboundReconciliationState();
        $closingHealth = app(RealEstateClosingRiskService::class)->health();

        $snapshot['whatsapp_connection_state'] = $state;
        $snapshot['whatsapp_connection_source'] = 'evolution_live';
        $snapshot['checks']['whatsapp_connected'] = $connected;
        $snapshot['checks']['fact_consistency_guard_ready'] = $factConsistencyReady;
        $snapshot['checks']['media_admission_gate_ready'] = $mediaAdmissionGateReady;
        $snapshot['checks']['unresolved_outbound_deliveries'] = $outboundState['blocking'];
        $snapshot['outbound_reconciliation'] = $outboundState;
        $snapshot['closing_health'] = $closingHealth;
        $snapshot['checks']['closing_health_observable'] = (bool) ($closingHealth['ready'] ?? false);

        $blocking = array_values(array_filter(
            $snapshot['blocking_checks'] ?? [],
            fn (mixed $check): bool => is_string($check)
                && $check !== 'whatsapp_connected'
                && $check !== 'fact_consistency_guard_ready'
                && $check !== 'media_admission_gate_ready'
                && $check !== 'unresolved_outbound_deliveries'
                && $check !== 'closing_health_observable'
        ));

        if (! $connected) {
            $blocking[] = 'whatsapp_connected';
        }

        if (! $factConsistencyReady) {
            $blocking[] = 'fact_consistency_guard_ready';
        }

        if (! $mediaAdmissionGateReady) {
            $blocking[] = 'media_admission_gate_ready';
        }

        if ($outboundState['blocking'] > 0) {
            $blocking[] = 'unresolved_outbound_deliveries';
        }

        if (! (bool) ($closingHealth['ready'] ?? false)) {
            $blocking[] = 'closing_health_observable';
        }

        $snapshot['blocking_checks'] = array_values(array_unique($blocking));
        $snapshot['ready_for_live_traffic'] = $snapshot['blocking_checks'] === [];

        return $snapshot;
    }

    /**
     * A normal in-flight send must not make the whole production service look
     * unhealthy. Only uncertain rows and sends stuck at the network boundary
     * for at least two minutes are readiness blockers.
     *
     * @return array{fresh_sending:int,stale_sending:int,uncertain:int,blocking:int}
     */
    private function outboundReconciliationState(): array
    {
        $base = RealEstateOutboundDelivery::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE);
        $cutoff = now()->subSeconds(120);

        $freshSending = (clone $base)
            ->where('status', 'sending')
            ->whereNotNull('sending_started_at')
            ->where('sending_started_at', '>', $cutoff)
            ->count();
        $staleSending = (clone $base)
            ->where('status', 'sending')
            ->where(function ($query) use ($cutoff): void {
                $query
                    ->where('sending_started_at', '<=', $cutoff)
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested
                            ->whereNull('sending_started_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->count();
        $uncertain = (clone $base)
            ->where('status', 'uncertain')
            ->count();

        return [
            'fresh_sending' => $freshSending,
            'stale_sending' => $staleSending,
            'uncertain' => $uncertain,
            'blocking' => $staleSending + $uncertain,
        ];
    }
}
