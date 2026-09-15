<?php

namespace App\Services\Printing;

use App\Jobs\ProcessPrintingTextBurst;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Services\EvolutionMediaService;
use App\Services\MemoryService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PrintingWhatsAppInboundService
{
    public function __construct(
        private readonly PrintingAssistantService $assistant,
        private readonly PrintingBusinessCardMockupService $businessCardMockup,
        private readonly EvolutionMediaService $mediaService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MemoryService $memoryService,
    ) {
    }

    public function processPayload(array $payload, bool $buffered = false): bool
    {
        $instance = trim((string) ($payload['instance'] ?? ''));
        if ($instance === '') {
            return false;
        }

        $bot = AiBot::query()
            ->where('whatsapp_instance', $instance)
            ->where('business_sector', 'printing')
            ->first();

        if (! $bot) {
            return false;
        }

        $event = strtolower(str_replace(['_', '-'], '.', (string) ($payload['event'] ?? '')));
        if ($event !== 'messages.upsert') {
            return true;
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us')) {
            return true;
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            return true;
        }

        if (! $bot->whatsappAiKullanilabilirMi()) {
            return true;
        }

        $phone = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';
        if ($phone === '') {
            return true;
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));
        $messagePayload = data_get($payload, 'data.message', []);
        $text = trim((string) (
            data_get($messagePayload, 'conversation')
            ?? data_get($messagePayload, 'extendedTextMessage.text')
            ?? ''
        ));

        if (! $buffered && $text !== '') {
            $key = 'printing_text_burst:'.$bot->id.':'.$phone;
            $cache = Cache::store('database');

            $cache->lock($key.':lock', 15)->block(5, function () use ($cache, $key, $payload, $text, $messageId): void {
                if ($messageId !== '' && ! $cache->add($key.':seen:'.$messageId, true, now()->addDay())) {
                    return;
                }

                $batch = $cache->get($key);
                $new = ! is_array($batch);
                $batch = $new
                    ? ['id' => (string) Str::uuid(), 'texts' => [], 'payload' => $payload]
                    : $batch;

                $batch['texts'][] = $text;
                $batch['updated_at'] = microtime(true);
                $cache->put($key, $batch, now()->addDay());

                if ($new) {
                    ProcessPrintingTextBurst::dispatch($key)
                        ->delay(now()->addSeconds(max(1, (int) config('matbaa.debounce_seconds', 10))));
                }
            });

            return true;
        }

        if ($messageId !== '') {
            $dedupeKey = 'printing_inbound:'.$bot->id.':'.$messageId;
            if (! Cache::store('database')->add($dedupeKey, true, now()->addDay())) {
                return true;
            }
        }

        [$message, $attachments] = $this->extractMessageAndAttachments($messagePayload, $text);
        if ($message === '' && $attachments === []) {
            return true;
        }

        if ($message === '') {
            $message = '[Tasarım dosyası gönderildi]';
        }

        $sessionId = 'whatsapp:'.$bot->id.':'.$phone;
        $conversation = $this->conversation($bot, $sessionId, $phone, $payload);

        $this->memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $sessionId,
            role: 'user',
            message: $message,
            senderType: 'customer',
        );

        $conversation->forceFill([
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_contact_at' => now(),
        ])->save();

        if ((bool) $conversation->human_takeover) {
            return true;
        }

        try {
            $result = $this->assistant->handle(
                sessionId: $sessionId,
                message: $message,
                attachments: $attachments,
                recentMessages: [],
            );

            $answer = trim((string) ($result['reply'] ?? ''));
            $mockupSent = $this->trySendBusinessCardMockup(
                bot: $bot,
                conversation: $conversation,
                instance: $instance,
                phone: $phone,
                payload: $payload,
                result: $result,
            );

            if (! $mockupSent && $answer !== '') {
                $this->sendText($bot, $conversation, $instance, $phone, $answer);
            }

            if (($result['status'] ?? null) === 'quote_ready') {
                Log::info('PRINTING QUOTE READY', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'phone_number' => $phone,
                    'state' => $result['state'] ?? [],
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('PRINTING WHATSAPP INBOUND FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);

            $this->sendText(
                $bot,
                $conversation,
                $instance,
                $phone,
                'Mesajınızı aldım. Baskı detaylarını işlerken kısa bir aksaklık oluştu; talebiniz kaybolmadı. Birkaç saniye sonra tekrar deneyebilirsiniz.'
            );
        }

        return true;
    }

    private function trySendBusinessCardMockup(
        AiBot $bot,
        ConversationControl $conversation,
        string $instance,
        string $phone,
        array $payload,
        array $result,
    ): bool {
        $state = is_array($result['state'] ?? null) ? $result['state'] : [];
        if (($state['product'] ?? null) !== 'business_card') {
            return false;
        }

        $image = data_get($payload, 'data.message.imageMessage');
        if (! is_array($image)) {
            // PDF rendering will be added separately; do not fake or redraw it.
            return false;
        }

        $mime = strtolower(trim((string) data_get($image, 'mimetype', 'image/jpeg')));
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return false;
        }

        try {
            $envelope = [
                'key' => data_get($payload, 'data.key', []),
                'message' => data_get($payload, 'data.message', []),
                'messageTimestamp' => data_get($payload, 'data.messageTimestamp'),
            ];

            $artworkBase64 = $this->mediaService->downloadBase64($instance, $envelope);
            $finish = (string) data_get($state, 'slots.lamination', 'mat');
            $mockupBase64 = $this->businessCardMockup->create($artworkBase64, null, $finish);
            $caption = 'Kartvizit önizlemesini hazırladım. Tasarım içeriğini değiştirmeden baskı sunumuna yerleştirdim. İsterseniz farklı açı veya yüzey görünümü için revize yapabilirim.';

            $this->whatsAppService->sendImage(
                $instance,
                $phone,
                $mockupBase64,
                'kartvizit-onizleme.jpg',
                $caption,
                'image/jpeg',
            );

            $this->memoryService->mesajKaydet(
                userId: $bot->user_id,
                aiBotId: $bot->id,
                sessionId: $conversation->session_id,
                role: 'assistant',
                message: $caption,
                senderType: 'ai',
            );

            Log::info('PRINTING BUSINESS CARD MOCKUP SENT', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'phone_number' => $phone,
                'finish' => $finish,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('PRINTING BUSINESS CARD MOCKUP FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function extractMessageAndAttachments(array $messagePayload, string $text): array
    {
        $attachments = [];
        $message = $text;

        foreach ([
            'imageMessage' => 'image/jpeg',
            'documentMessage' => 'application/pdf',
        ] as $key => $fallbackMime) {
            $media = data_get($messagePayload, $key);
            if (! is_array($media)) {
                continue;
            }

            $mime = trim((string) data_get($media, 'mimetype', $fallbackMime));
            if (! in_array(strtolower($mime), ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'], true)) {
                continue;
            }

            $attachments[] = array_filter([
                'name' => (string) (data_get($media, 'fileName') ?? ($key === 'imageMessage' ? 'tasarim.jpg' : 'tasarim.pdf')),
                'mime' => $mime,
                'url' => data_get($media, 'url'),
            ], static fn ($value) => $value !== null && $value !== '');

            $caption = trim((string) data_get($media, 'caption', ''));
            if ($message === '' && $caption !== '') {
                $message = $caption;
            }
        }

        return [$message, $attachments];
    }

    private function conversation(AiBot $bot, string $sessionId, string $phone, array $payload): ConversationControl
    {
        $organizationId = Organization::query()
            ->where('owner_user_id', $bot->user_id)
            ->value('id');

        $conversation = ConversationControl::firstOrCreate(
            [
                'ai_bot_id' => $bot->id,
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $bot->user_id,
                'organization_id' => $organizationId,
                'whatsapp_number' => $phone,
                'customer_name' => trim((string) data_get($payload, 'data.pushName', '')) ?: null,
                'unread_count' => 0,
                'human_takeover' => false,
            ]
        );

        if ($conversation->organization_id === null && $organizationId !== null) {
            $conversation->forceFill(['organization_id' => $organizationId])->save();
        }

        return $conversation;
    }

    private function sendText(AiBot $bot, ConversationControl $conversation, string $instance, string $phone, string $answer): void
    {
        $answer = trim(preg_replace("/\n{3,}/", "\n\n", str_replace(['\\r\\n', '\\n', '\\r'], "\n", $answer)) ?? $answer);

        $send = null;
        $lastException = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $send = $this->whatsAppService->sendText($instance, $phone, $answer);
                break;
            } catch (Throwable $exception) {
                $lastException = $exception;
                Log::warning('PRINTING WHATSAPP SEND RETRY', [
                    'ai_bot_id' => $bot->id,
                    'conversation_id' => $conversation->id,
                    'attempt' => $attempt,
                    'message' => $exception->getMessage(),
                ]);
                if ($attempt < 3) {
                    usleep(250000 * $attempt);
                }
            }
        }

        if (! is_array($send)) {
            throw $lastException ?? new \RuntimeException('Matbaa WhatsApp mesajı gönderilemedi.');
        }

        $this->memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $conversation->session_id,
            role: 'assistant',
            message: $answer,
            senderType: 'ai',
        );
    }
}
