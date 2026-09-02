<?php

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\RealEstateOutboundSafetyEvent;
use App\Services\RealEstateEvidenceLedgerService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
use App\Services\RealEstateOutboundSafetyService;
use App\Services\RealEstateReadinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health/release', function () {
    return response()->json([
        'ok' => true,
        'release' => 'real-estate-whatsapp-v5-dedicated-inbound',
        'deployed_at' => '2026-09-01',
    ]);
});

Route::post('/whatsapp/webhook', function (Request $request) {
    $instance = trim((string) data_get($request->all(), 'instance', ''));

    if ($instance === RealEstateIsolationService::INSTANCE) {
        Log::warning('ISOLATED REAL ESTATE INSTANCE REJECTED ON GENERIC WEBHOOK', [
            'instance' => $instance,
            'event' => data_get($request->all(), 'event'),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Bu instance yalnızca izole Emlak AI webhook endpointini kullanabilir.',
        ], 403);
    }

    return app()->call([
        app(WhatsAppWebhookController::class),
        'handle',
    ], ['request' => $request]);
});

Route::prefix('real-estate')->group(function (): void {
    Route::get('/health', function () {
        $snapshot = app(RealEstateReadinessService::class)->snapshot();
        $matchLedgerReady = Schema::hasTable('real_estate_match_events')
            && class_exists(RealEstateMatchLedgerService::class);
        $evidenceLedgerReady = Schema::hasTable('real_estate_evidence_events')
            && class_exists(RealEstateEvidenceLedgerService::class);
        $outboundSafetyReady = Schema::hasTable('real_estate_outbound_safety_events')
            && class_exists(RealEstateOutboundSafetyService::class);

        $snapshot['checks']['match_ledger_ready'] = $matchLedgerReady;
        $snapshot['match_ledger_telemetry_24h'] = $matchLedgerReady
            ? app(RealEstateMatchLedgerService::class)->telemetry24h()
            : [
                'events' => 0,
                'activated' => 0,
                'removed' => 0,
                'strong_activated' => 0,
                'current_active_pairs' => 0,
            ];
        $snapshot['checks']['evidence_ledger_ready'] = $evidenceLedgerReady;
        $snapshot['evidence_ledger_telemetry_24h'] = $evidenceLedgerReady
            ? app(RealEstateEvidenceLedgerService::class)->telemetry24h()
            : [
                'events' => 0,
                'documentary' => 0,
                'supporting' => 0,
                'unknown' => 0,
                'qualified_documentary' => 0,
                'unique_profiles' => 0,
            ];
        $snapshot['checks']['outbound_safety_firewall_ready'] = $outboundSafetyReady;

        if ($outboundSafetyReady) {
            $safetyEvents = RealEstateOutboundSafetyEvent::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('detected_at', '>=', now()->subDay())
                ->get(['conversation_control_id', 'reasons']);

            $snapshot['outbound_safety_telemetry_24h'] = [
                'events' => $safetyEvents->count(),
                'replaced' => $safetyEvents->count(),
                'unsupported_transaction_certainty' => $safetyEvents
                    ->filter(fn ($event): bool => in_array(
                        'unsupported_transaction_certainty',
                        is_array($event->reasons) ? $event->reasons : [],
                        true
                    ))->count(),
                'unsupported_official_verification' => $safetyEvents
                    ->filter(fn ($event): bool => in_array(
                        'unsupported_official_verification',
                        is_array($event->reasons) ? $event->reasons : [],
                        true
                    ))->count(),
                'confidential_seller_floor' => $safetyEvents
                    ->filter(fn ($event): bool => in_array(
                        'confidential_seller_floor',
                        is_array($event->reasons) ? $event->reasons : [],
                        true
                    ))->count(),
                'unique_conversations' => $safetyEvents
                    ->pluck('conversation_control_id')
                    ->filter()
                    ->unique()
                    ->count(),
            ];
        } else {
            $snapshot['outbound_safety_telemetry_24h'] = [
                'events' => 0,
                'replaced' => 0,
                'unsupported_transaction_certainty' => 0,
                'unsupported_official_verification' => 0,
                'confidential_seller_floor' => 0,
                'unique_conversations' => 0,
            ];
        }

        foreach ([
            'match_ledger_ready' => $matchLedgerReady,
            'evidence_ledger_ready' => $evidenceLedgerReady,
            'outbound_safety_firewall_ready' => $outboundSafetyReady,
        ] as $check => $ready) {
            if ($ready) {
                continue;
            }

            $snapshot['blocking_checks'] = array_values(array_unique([
                ...($snapshot['blocking_checks'] ?? []),
                $check,
            ]));
            $snapshot['ready_for_live_traffic'] = false;
        }

        return response()->json($snapshot);
    });

    Route::post('/whatsapp/webhook', [
        RealEstateWhatsAppWebhookController::class,
        'handle',
    ]);
});
