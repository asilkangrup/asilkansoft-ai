<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Services\RealEstateWebhookAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RealEstateWhatsAppWebhookController extends Controller
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_BOT_ID = 35;

    public function handle(
        Request $request,
        RealEstateWebhookAuthService $authService
    ): JsonResponse {
        if (! $authService->configured()) {
            Log::error('REAL ESTATE WEBHOOK AUTH NOT CONFIGURED');

            return response()->json([
                'success' => false,
                'message' => 'Emlak AI webhook güvenliği yapılandırılmamış.',
            ], 503);
        }

        if (! $authService->verifyRequest($request)) {
            Log::warning('REAL ESTATE WEBHOOK AUTH REJECTED', [
                'instance' => data_get($request->all(), 'instance'),
                'event' => data_get($request->all(), 'event'),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Yetkisiz webhook isteği.',
            ], 401);
        }

        $instance = trim((string) data_get($request->all(), 'instance', ''));

        $allowed = AiBot::query()
            ->whereKey(self::REAL_ESTATE_BOT_ID)
            ->where('user_id', self::REAL_ESTATE_USER_ID)
            ->where('business_sector', 'real_estate')
            ->where('whatsapp_instance', $instance)
            ->exists();

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Geçersiz Emlak AI instance.',
            ], 403);
        }

        return app()->call(
            [app(WhatsAppWebhookController::class), 'handle'],
            ['request' => $request]
        );
    }
}
