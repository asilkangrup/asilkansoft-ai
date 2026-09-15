<?php

namespace App\Providers;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Services\TextileV2\TextileV2WhatsAppInboundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TextileV2ServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Route::post('/api/textile-v2/whatsapp/webhook', function (Request $request, TextileV2WhatsAppInboundService $service) {
            $payload = $request->all();

            if (trim((string) ($payload['instance'] ?? '')) !== TextileV2WhatsAppInboundService::INSTANCE) {
                return response()->json(['ok' => false], 404);
            }

            $bot = AiBot::query()->find(TextileV2WhatsAppInboundService::BOT_ID);

            // Hard kill switch: an inactive/disabled bot must never process WhatsApp traffic.
            if (! $bot || $bot->status !== 'active' || ! (bool) $bot->ai_enabled) {
                return response()->json(['ok' => true, 'disabled' => true]);
            }

            $event = strtolower(str_replace(['_', '-'], '.', (string) ($payload['event'] ?? '')));

            if ($event === 'messages.upsert' && (bool) data_get($payload, 'data.key.fromMe', false)) {
                $text = trim((string) (
                    data_get($payload, 'data.message.conversation')
                    ?? data_get($payload, 'data.message.extendedTextMessage.text')
                    ?? data_get($payload, 'data.message.imageMessage.caption')
                    ?? data_get($payload, 'data.message.documentMessage.caption')
                    ?? ''
                ));

                // A real manual text sent by Fatih immediately takes over the customer chat.
                // Bot-originated text is marked by TextileV2WhatsAppInboundService::send().
                if ($text !== '') {
                    $phoneJid = trim((string) data_get($payload, 'data.key.remoteJidAlt', ''));
                    if ($phoneJid === '' || ! str_ends_with($phoneJid, '@s.whatsapp.net')) {
                        $phoneJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
                    }

                    if (! str_ends_with($phoneJid, '@g.us')) {
                        $phone = preg_replace('/\D+/', '', explode('@', $phoneJid)[0] ?? '') ?? '';

                        if ($phone !== '') {
                            $outboundKey = 'textile_v2_out:'.sha1(
                                TextileV2WhatsAppInboundService::INSTANCE.'|'.$phone.'|'.$text
                            );

                            // Use get(), never pull(): duplicate outbound webhooks must remain recognized as bot messages.
                            $isBotOutbound = (bool) Cache::store('database')->get($outboundKey, false);

                            if (! $isBotOutbound) {
                                $session = 'whatsapp:v2:'.TextileV2WhatsAppInboundService::BOT_ID.':'.$phone;
                                $conversation = ConversationControl::query()->firstOrCreate(
                                    [
                                        'ai_bot_id' => TextileV2WhatsAppInboundService::BOT_ID,
                                        'session_id' => $session,
                                    ],
                                    [
                                        'user_id' => TextileV2WhatsAppInboundService::USER_ID,
                                        'whatsapp_number' => $phone,
                                        'customer_name' => null,
                                        'unread_count' => 0,
                                        'human_takeover' => true,
                                    ]
                                );

                                $conversation->forceFill([
                                    'user_id' => TextileV2WhatsAppInboundService::USER_ID,
                                    'whatsapp_number' => $phone,
                                    'human_takeover' => true,
                                    'updated_at' => now(),
                                ])->save();

                                // Cancel any customer message currently waiting in the debounce window.
                                Cache::store('database')->forget(
                                    'textile_v2_pending_bundle:'.TextileV2WhatsAppInboundService::BOT_ID.':'.$phone
                                );
                                Cache::store('database')->forget(
                                    'textile_v2_pending_token:'.TextileV2WhatsAppInboundService::BOT_ID.':'.$phone
                                );

                                return response()->json(['ok' => true, 'human_takeover' => true]);
                            }
                        }
                    }
                }

                // Never send fromMe traffic into the AI inbound processor.
                return response()->json(['ok' => true, 'from_me' => true]);
            }

            $service->process($payload);
            return response()->json(['ok' => true]);
        });

        Route::get('/api/textile-v2/health', fn () => response()->json([
            'ok' => true,
            'bot_id' => TextileV2WhatsAppInboundService::BOT_ID,
            'instance' => TextileV2WhatsAppInboundService::INSTANCE,
        ]));
    }
}
