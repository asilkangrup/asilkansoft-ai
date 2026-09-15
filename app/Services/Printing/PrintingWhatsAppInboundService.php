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
        private readonly PrintingBusinessCardDesignService $businessCardDesign,
        private readonly PrintingPdfArtworkRenderer $pdfArtworkRenderer,
        private readonly PrintingArtworkClassifierService $artworkClassifier,
        private readonly EvolutionMediaService $mediaService,
        private readonly WhatsAppService $whatsAppService,
        private readonly MemoryService $memoryService,
    ) {
    }

    public function processPayload(array $payload, bool $buffered = false): bool
    {
        $instance = trim((string) ($payload['instance'] ?? ''));
        if ($instance === '') return false;

        $bot = AiBot::query()
            ->where('whatsapp_instance', $instance)
            ->where('business_sector', 'printing')
            ->first();
        if (! $bot) return false;

        $event = strtolower(str_replace(['_', '-'], '.', (string) ($payload['event'] ?? '')));
        if ($event !== 'messages.upsert') return true;

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if ($remoteJid === '' || str_ends_with($remoteJid, '@g.us')) return true;
        if ((bool) data_get($payload, 'data.key.fromMe', false)) return true;
        if (! $bot->whatsappAiKullanilabilirMi()) return true;

        $remoteJidAlt = trim((string) data_get($payload, 'data.key.remoteJidAlt', ''));
        $identityJid = str_ends_with($remoteJid, '@lid') && $remoteJidAlt !== ''
            ? $remoteJidAlt
            : $remoteJid;

        $phone = preg_replace('/\D+/', '', explode('@', $identityJid)[0] ?? '') ?? '';
        if ($phone === '') return true;

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
                if ($messageId !== '' && ! $cache->add($key.':seen:'.$messageId, true, now()->addDay())) return;
                $batch = $cache->get($key);
                $new = ! is_array($batch);
                $batch = $new ? ['id' => (string) Str::uuid(), 'texts' => [], 'payload' => $payload] : $batch;
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
            if (! Cache::store('database')->add($dedupeKey, true, now()->addDay())) return true;
        }

        [$message, $attachments] = $this->extractMessageAndAttachments($messagePayload, $text);
        if ($message === '' && $attachments === []) return true;
        if ($message === '') $message = '[Tasarım dosyası gönderildi]';

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

        if ((bool) $conversation->human_takeover) return true;

        try {
            $result = $this->assistant->handle(
                sessionId: $sessionId,
                message: $message,
                attachments: $attachments,
                recentMessages: [],
            );

            if ($this->trySendGeneratedBusinessCardDesign($bot, $conversation, $instance, $phone, $result)) {
                return true;
            }

            $answer = trim((string) ($result['reply'] ?? ''));
            $preview = $this->trySendBusinessCardMockup(
                bot: $bot,
                conversation: $conversation,
                instance: $instance,
                phone: $phone,
                payload: $payload,
                result: $result,
            );

            if (($preview['handled'] ?? false) === true) {
                if (is_string($preview['message'] ?? null) && trim((string) $preview['message']) !== '') {
                    $this->sendText($bot, $conversation, $instance, $phone, (string) $preview['message']);
                }
            } elseif ($answer !== '') {
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
            $this->sendText($bot, $conversation, $instance, $phone,
                'Mesajınızı aldım. Baskı detaylarını işlerken kısa bir aksaklık oluştu; talebiniz kaybolmadı. Birkaç saniye sonra tekrar deneyebilirsiniz.'
            );
        }

        return true;
    }

    private function trySendGeneratedBusinessCardDesign(
        AiBot $bot,
        ConversationControl $conversation,
        string $instance,
        string $phone,
        array $result,
    ): bool {
        if (($result['status'] ?? null) !== 'design_brief_ready') return false;

        $state = is_array($result['state'] ?? null) ? $result['state'] : [];
        if (($state['product'] ?? null) !== 'business_card') return false;

        try {
            $brief = is_array($state['design_brief'] ?? null) ? $state['design_brief'] : [];
            $faces = $this->businessCardDesign->create($brief);
            $finish = (string) data_get($state, 'slots.lamination', 'mat');
            $mockup = $this->businessCardMockup->create($faces['front'], $faces['back'], $finish);
            $caption = 'İlk kartvizit taslak önizlemesini hazırladım. Firma bilgilerini okunabilir tutarak ön ve arka yüzü baskı sunumuna yerleştirdim. Renk, yazı, logo veya yerleşim için istediğiniz revizeyi yazabilirsiniz.';

            $this->whatsAppService->sendImage(
                $instance,
                $phone,
                $mockup,
                'kartvizit-tasarim-taslak.jpg',
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

            Log::info('PRINTING GENERATED BUSINESS CARD DESIGN SENT', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'phone_number' => $phone,
                'style' => $brief['style'] ?? null,
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::warning('PRINTING GENERATED BUSINESS CARD DESIGN FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /** @return array{handled: bool, message?: string} */
    private function trySendBusinessCardMockup(
        AiBot $bot,
        ConversationControl $conversation,
        string $instance,
        string $phone,
        array $payload,
        array $result,
    ): array {
        $state = is_array($result['state'] ?? null) ? $result['state'] : [];
        if (($state['product'] ?? null) !== 'business_card') return ['handled' => false];

        $image = data_get($payload, 'data.message.imageMessage');
        $document = data_get($payload, 'data.message.documentMessage');
        $isImage = is_array($image)
            && in_array(strtolower(trim((string) data_get($image, 'mimetype', 'image/jpeg'))), ['image/jpeg', 'image/jpg', 'image/png'], true);
        $isPdf = is_array($document)
            && strtolower(trim((string) data_get($document, 'mimetype', ''))) === 'application/pdf';

        if (! $isImage && ! $isPdf) return ['handled' => false];

        try {
            $envelope = [
                'key' => data_get($payload, 'data.key', []),
                'message' => data_get($payload, 'data.message', []),
                'messageTimestamp' => data_get($payload, 'data.messageTimestamp'),
            ];

            $mediaBase64 = $this->mediaService->downloadBase64($instance, $envelope);
            $front = null;
            $back = null;

            if ($isPdf) {
                $pages = $this->pdfArtworkRenderer->renderFirstTwoPages($mediaBase64);
                $classification = $this->artworkClassifier->classifyPdfPages($pages);
                if (($classification['role'] ?? 'unknown') !== 'print_artwork') {
                    return [
                        'handled' => true,
                        'message' => 'PDF dosyasını aldım ancak kartvizit baskı tasarımı olarak güvenle doğrulayamadım. Ön ve arka yüzü içeren kartvizit PDF’ini veya JPG/PNG tasarımını gönderebilirsiniz.',
                    ];
                }
                $front = $pages[0] ?? null;
                $back = $pages[1] ?? null;
            } else {
                $caption = trim((string) data_get($image, 'caption', ''));
                $classification = $this->artworkClassifier->classifyBusinessCardImage($mediaBase64, $caption);
                if (($classification['role'] ?? 'unknown') !== 'print_artwork') {
                    Log::info('PRINTING ARTWORK REJECTED', [
                        'ai_bot_id' => $bot->id,
                        'conversation_id' => $conversation->id,
                        'classification' => $classification,
                    ]);

                    return [
                        'handled' => true,
                        'message' => 'Gönderdiğiniz görsel kartvizit baskı tasarımı gibi görünmüyor. Önizleme hazırlayabilmem için kartvizitin ön/arka yüz tasarımını JPG, PNG veya PDF olarak gönderebilirsiniz. Yalnızca logo gönderdiyseniz tasarımı da birlikte hazırlayabiliriz.',
                    ];
                }
                $front = $mediaBase64;
            }

            if (! is_string($front) || $front === '') return ['handled' => false];

            $finish = (string) data_get($state, 'slots.lamination', 'mat');
            $mockupBase64 = $this->businessCardMockup->create($front, $back, $finish);
            $caption = $back
                ? 'Kartvizit ön ve arka yüz önizlemesini hazırladım. Tasarım içeriğini değiştirmeden baskı sunumuna yerleştirdim. İsterseniz farklı açı veya yüzey görünümü için revize yapabilirim.'
                : 'Kartvizit önizlemesini hazırladım. Tasarım içeriğini değiştirmeden baskı sunumuna yerleştirdim. İsterseniz farklı açı veya yüzey görünümü için revize yapabilirim.';

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
                'source' => $isPdf ? 'pdf' : 'image',
                'pages' => $back ? 2 : 1,
            ]);
            return ['handled' => true];
        } catch (Throwable $exception) {
            Log::warning('PRINTING BUSINESS CARD MOCKUP FAILED', [
                'ai_bot_id' => $bot->id,
                'conversation_id' => $conversation->id,
                'message' => $exception->getMessage(),
            ]);
            return ['handled' => false];
        }
    }

    private function extractMessageAndAttachments(array $messagePayload, string $text): array
    {
        $attachments = [];
        $message = $text;

        foreach (['imageMessage' => 'image/jpeg', 'documentMessage' => 'application/pdf'] as $key => $fallbackMime) {
            $media = data_get($messagePayload, $key);
            if (! is_array($media)) continue;
            $mime = trim((string) data_get($media, 'mimetype', $fallbackMime));
            if (! in_array(strtolower($mime), ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'], true)) continue;

            $attachments[] = array_filter([
                'name' => (string) (data_get($media, 'fileName') ?? ($key === 'imageMessage' ? 'tasarim.jpg' : 'tasarim.pdf')),
                'mime' => $mime,
                'url' => data_get($media, 'url'),
            ], static fn ($value) => $value !== null && $value !== '');

            $caption = trim((string) data_get($media, 'caption', ''));
            if ($message === '' && $caption !== '') $message = $caption;
        }

        return [$message, $attachments];
    }

    private function conversation(AiBot $bot, string $sessionId, string $phone, array $payload): ConversationControl
    {
        $organizationId = Organization::query()->where('owner_user_id', $bot->user_id)->value('id');
        $conversation = ConversationControl::firstOrCreate(
            ['ai_bot_id' => $bot->id, 'session_id' => $sessionId],
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
                if ($attempt < 3) usleep(250000 * $attempt);
            }
        }
        if (! is_array($send)) throw $lastException ?? new \RuntimeException('Matbaa WhatsApp mesajı gönderilemedi.');

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
