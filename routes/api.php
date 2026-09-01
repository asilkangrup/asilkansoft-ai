<?php

use App\Http\Controllers\RealEstateWhatsAppWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\AiBot;
use App\Services\RealEstateWebhookAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health/release', function () {
    return response()->json([
        'ok' => true,
        'release' => 'real-estate-whatsapp-v3-secure',
        'deployed_at' => '2026-09-01',
    ]);
});

Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

Route::prefix('real-estate')->group(function (): void {
    Route::get('/health', function () {
        $bot = AiBot::query()->find(35);

        return response()->json([
            'ok' => (bool) $bot,
            'service' => 'real-estate-ai',
            'user_id' => 40,
            'bot_id' => 35,
            'isolated' => true,
            'webhook_auth_configured' => app(RealEstateWebhookAuthService::class)->configured(),
            'openai_api_key_configured' => filled($bot?->openai_api_key),
            'whatsapp_status' => $bot?->whatsapp_status,
            'whatsapp_instance_configured' => filled($bot?->whatsapp_instance),
            'follow_up_enabled' => (bool) ($bot?->follow_up_enabled ?? false),
            'second_follow_up_enabled' => (bool) ($bot?->second_follow_up_enabled ?? false),
        ]);
    });

    Route::post('/whatsapp/webhook', [
        RealEstateWhatsAppWebhookController::class,
        'handle',
    ]);
});
