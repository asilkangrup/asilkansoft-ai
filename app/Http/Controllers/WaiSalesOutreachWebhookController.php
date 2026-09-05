<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWaiSalesOutreachMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WaiSalesOutreachWebhookController extends Controller
{
    public const INSTANCE = 'wai-sales-48-clean';

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $event = strtolower(str_replace(['_', '-'], '.', trim((string) data_get($payload, 'event', ''))));

        if ($event !== 'messages.upsert') {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'from_me']);
        }

        $instance = trim((string) data_get($payload, 'instance', ''));
        if ($instance !== self::INSTANCE) {
            return response()->json(['success' => false, 'message' => 'Invalid instance.'], 403);
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if ($remoteJid === '' || str_contains($remoteJid, '@g.us')) {
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'unsupported_chat']);
        }

        $messagePayload = data_get($payload, 'data.message', []);
        $message = data_get($messagePayload, 'conversation')
            ?? data_get($messagePayload, 'extendedTextMessage.text');

        $message = trim((string) $message);
        if ($message === '') {
            return response()->json(['success' => true, 'ignored' => true, 'reason' => 'empty_message']);
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));
        $debounceKey = 'wai-sales:buffer:'.sha1($instance.'|'.$remoteJid);
        $tokenKey = 'wai-sales:token:'.sha1($instance.'|'.$remoteJid);
        $token = $messageId !== '' ? $messageId : (string) Str::uuid();

        $buffer = Cache::get($debounceKey, []);
        if (! is_array($buffer)) {
            $buffer = [];
        }

        $buffer[] = [
            'id' => $messageId,
            'text' => $message,
            'received_at' => now()->toIso8601String(),
        ];

        Cache::put($debounceKey, $buffer, now()->addMinutes(5));
        Cache::put($tokenKey, $token, now()->addMinutes(5));

        ProcessWaiSalesOutreachMessage::dispatch(
            instance: $instance,
            remoteJid: $remoteJid,
            debounceKey: $debounceKey,
            tokenKey: $tokenKey,
            token: $token,
        )->delay(now()->addSeconds(10));

        return response()->json([
            'success' => true,
            'queued' => true,
            'debounce_seconds' => 10,
        ]);
    }
}
