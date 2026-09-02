<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRealEstateMediaBatch;
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

            // Debounce every inbound burst, not only media. Wait for a short\n            // silence window so customers who type word by word receive one\n            // coherent reply instead of a reply to every fragment. Ads frequently
            // produce a photo followed by several short text messages. Keeping
            // them in one ordered batch ensures the full-resolution image is
            // analyzed before the single final reply and prevents duplicate
            // discovery questions from concurrent jobs.
            $contactKey = $this->canonicalContactKey($payload);
            $cacheKey = 'real-estate-inbound-burst:'.hash('sha256', $contactKey);

            // Record arrival immediately, before queued processing. An answer
            // already being generated for an older turn can use this marker
            // to fail closed even though the new payload is not persisted yet.
            $phoneDigits = preg_replace(
                '/\D+/',
                '',
                Str::before($contactKey, '@')
            ) ?? '';
            $messageId = trim((string) data_get($payload, 'data.key.id', ''));

            if ($phoneDigits !== '' && $messageId !== '') {
                Cache::put(
                    'real-estate-latest-inbound:'.hash('sha256', $phoneDigits),
                    $messageId,
                    now()->addMinutes(5)
                );
            }

            $existing = Cache::get($cacheKey);
            $payloads = is_array($existing) && is_array($existing['payloads'] ?? null)
                ? $existing['payloads']
                : [];
            $payloads[] = $payload;
            $generation = (string) Str::uuid();

            Cache::put($cacheKey, [
                'generation' => $generation,
                'payloads' => array_slice($payloads, -12),
                'quiet_until' => now()->addSeconds(10)->timestamp,
            ], now()->addSeconds(60));

            // One delayed job per contact is enough. Further fragments only
            // extend quiet_until; the queued job releases itself until the
            // customer has been silent for ten seconds. This prevents ad
            // bursts from flooding the queue with obsolete no-op jobs.
            $scheduledKey = $cacheKey.':scheduled';

            if (Cache::add($scheduledKey, true, now()->addSeconds(90))) {
                ProcessRealEstateMediaBatch::dispatch($cacheKey, $generation)
                    ->delay(now()->addSeconds(10));
            }

            return response()->json([
                'success' => true,
                'queued' => true,
                'batched_inbound' => true,
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

    private function canonicalContactKey(array $payload): string
    {
        $remoteJid = strtolower(trim((string) data_get(
            $payload,
            'data.key.remoteJid',
            ''
        )));
        $remoteJidAlt = strtolower(trim((string) data_get(
            $payload,
            'data.key.remoteJidAlt',
            ''
        )));

        // Evolution may alternate between a privacy-preserving @lid address
        // and the real phone JID for consecutive messages from one contact.
        // Prefer the phone alternative so both payloads share one debounce.
        if (
            str_ends_with($remoteJid, '@lid')
            && $this->isPhoneJid($remoteJidAlt)
        ) {
            return $remoteJidAlt;
        }

        if ($this->isPhoneJid($remoteJid)) {
            return $remoteJid;
        }

        return $remoteJid !== '' ? $remoteJid : (string) Str::uuid();
    }

    private function isPhoneJid(string $jid): bool
    {
        return str_ends_with($jid, '@s.whatsapp.net')
            || str_ends_with($jid, '@c.us');
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
