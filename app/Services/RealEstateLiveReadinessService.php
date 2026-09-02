<?php

namespace App\Services;

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

        $snapshot['whatsapp_connection_state'] = $state;
        $snapshot['whatsapp_connection_source'] = 'evolution_live';
        $snapshot['checks']['whatsapp_connected'] = $connected;
        $snapshot['checks']['fact_consistency_guard_ready'] = $factConsistencyReady;
        $snapshot['checks']['media_admission_gate_ready'] = $mediaAdmissionGateReady;

        $blocking = array_values(array_filter(
            $snapshot['blocking_checks'] ?? [],
            fn (mixed $check): bool => is_string($check)
                && $check !== 'whatsapp_connected'
                && $check !== 'fact_consistency_guard_ready'
                && $check !== 'media_admission_gate_ready'
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

        $snapshot['blocking_checks'] = array_values(array_unique($blocking));
        $snapshot['ready_for_live_traffic'] = $snapshot['blocking_checks'] === [];

        return $snapshot;
    }
}
