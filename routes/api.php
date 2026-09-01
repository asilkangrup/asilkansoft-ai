<?php

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health/release', function () {
    return response()->json([
        'ok' => true,
        'release' => 'real-estate-whatsapp-v2-isolated',
        'deployed_at' => '2026-09-01',
    ]);
});

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

Route::prefix('real-estate')->group(function (): void {
    Route::get('/health', function () {
        return response()->json([
            'ok' => true,
            'service' => 'real-estate-ai',
            'user_id' => 40,
            'bot_id' => 35,
            'isolated' => true,
        ]);
    });

    Route::post('/whatsapp/webhook', [
        RealEstateWhatsAppWebhookController::class,
        'handle',
    ]);
});
