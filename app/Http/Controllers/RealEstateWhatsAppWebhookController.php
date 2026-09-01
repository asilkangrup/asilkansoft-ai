<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealEstateWhatsAppWebhookController extends Controller
{
    private const REAL_ESTATE_USER_ID = 40;
    private const REAL_ESTATE_BOT_ID = 35;

    public function handle(Request $request): JsonResponse
    {
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
