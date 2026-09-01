<?php

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchLedgerService;
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

        if (! $matchLedgerReady) {
            $snapshot['blocking_checks'] = array_values(array_unique([
                ...($snapshot['blocking_checks'] ?? []),
                'match_ledger_ready',
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
