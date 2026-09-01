<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health/release', function () {
    return response()->json([
        'ok' => true,
        'release' => 'real-estate-whatsapp-v1',
        'deployed_at' => '2026-09-01',
    ]);
});

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);