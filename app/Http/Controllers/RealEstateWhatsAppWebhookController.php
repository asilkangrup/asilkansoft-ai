<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRealEstateMediaBatch;
use App\Jobs\ProcessRealEstateWhatsAppWebhook;
use App\Models\AiBot;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateWebhookAuthService;
use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RealEstateWhatsAppWebhookController extends Controller
{
    private const MAX_PAYLOAD_BYTES = 1_048_576;

    public function handle(
        Request $request,
        RealEstateWebhookAuthService $authService,
        RealEstateIsolationService $isolation,
        RealEstateWhatsAppInboundService $inboundService,
    ): JsonResponse {
        if (strlen($request->getContent()) > self::MAX_PAYLOAD_BYTES) {
            Log::warning('REAL ESTATE WEBHOOK PAYLOAD TOO LARGE', [
                'bytes' => strlen($request->getContent()),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook payload sınırı aşıldı.',
            ], 413);
        }

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

        $payload = $request->all();
        $instance = trim((string) data_get($payload, 'instance', ''));
        $event = $this->normalizeEvent(data_get($payload, 'event'));
        $payload['event'] = $event;

        if ($instance !== RealEstateIsolationService::INSTANCE) {
            Log::warning('REAL ESTATE WEBHOOK INSTANCE REJECTED', [
                'instance' => $instance,
                'expected_instance' => RealEstateIsolationService::INSTANCE,
                'event' => $event,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Geçersiz Emlak AI instance.',
            ], 403);
        }

        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('business_sector', 'real_estate')
            ->where('whatsapp_instance', RealEstateIsolationService::INSTANCE)
            ->first();

        if (! $isolation->supportsProductionBot($bot) || ! $isolation->organizationValid()) {
            Log::warning('REAL ESTATE WEBHOOK SCOPE REJECTED', [
                'instance' => $instance,
                'event' => $event,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Geçersiz Emlak AI kapsamı.',
            ], 403);
        }

        if (! in_array($event, ['messages.upsert', 'messages.update'], true)) {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'reason' => 'unsupported_event',
            ]);
        }

        try {
            if ($event === 'messages.update') {
                return response()->json(
                    $inboundService->handleStatusUpdate($payload)
                );
            }

            // This marker is created only after successful JWT + scope checks.
            // The job refuses isolated-instance traffic without it, preventing
            // the generic WAI endpoint from becoming an auth bypass.
            $payload['_real_estate_authorized'] = true;

            $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
            $cacheKey = 'real-estate-media-batch:'.hash('sha256', $remoteJid);
            $pendingMediaBatch = $remoteJid !== '' ? Cache::get($cacheKey) : null;

            // If a customer sends several photos/documents and then a short
            // text such as "bunları incele", keep that text in the same
            // burst. The final job therefore sees every media analysis before
            // producing exactly one customer-facing reply.
            if ($this->isBurstMediaPayload($payload) || is_array($pendingMediaBatch)) {
                $existing = $pendingMediaBatch;
                $payloads = is_array($existing) && is_array($existing['payloads'] ?? null)
                    ? $existing['payloads']
                    : [];
                $payloads[] = $payload;
                $generation = (string) Str::uuid();

                Cache::put($cacheKey, [
                    'generation' => $generation,
                    'payloads' => array_slice($payloads, -12),
                ], now()->addSeconds(45));

                ProcessRealEstateMediaBatch::dispatch($cacheKey, $generation)
                    ->delay(now()->addSeconds(3));

                return response()->json([
                    'success' => true,
                    'queued' => true,
                    'batched_media' => true,
                    'isolated' => true,
                ]);
            }

            ProcessRealEstateWhatsAppWebhook::dispatch($payload);

            return response()->json([
                'success' => true,
                'queued' => true,
                'isolated' => true,
            ]);
        } catch (Throwable $exception) {
            Log::error('REAL ESTATE WEBHOOK PROCESSING FAILED', [
                'instance' => $instance,
                'event' => $event,
                'message' => $exception->getMessage(),
            ]);

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Emlak AI webhook işlenemedi.',
            ], 500);
        }
    }

    private function isBurstMediaPayload(array $payload): bool
    {
        $message = data_get($payload, 'data.message', []);

        if (! is_array($message)) {
            return false;
        }

        $encoded = json_encode($message);

        return is_string($encoded) && (
            str_contains($encoded, '"imageMessage"')
            || str_contains($encoded, '"documentMessage"')
            || str_contains($encoded, '"videoMessage"')
        );
    }

    private function normalizeEvent(mixed $event): string
    {
        return str_replace(
            ['_', '-'],
            '.',
            strtolower(trim((string) $event))
        );
    }
}
