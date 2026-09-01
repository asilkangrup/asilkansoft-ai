<?php

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Services\RealEstateReadinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

Route::prefix('real-estate')->group(function (): void {
    Route::get('/health', function () {
        return response()->json(
            app(RealEstateReadinessService::class)->snapshot()
        );
    });

    Route::post('/whatsapp/webhook', [
        RealEstateWhatsAppWebhookController::class,
        'handle',
    ]);
});
