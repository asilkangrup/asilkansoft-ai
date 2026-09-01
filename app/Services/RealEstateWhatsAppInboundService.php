<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RealEstateWhatsAppInboundService
{
    public function __construct(
        private readonly RealEstateIsolationService $isolation,
        private readonly MemoryService $memoryService,
        private readonly RealEstateOpenAIService $openAIService,
        private readonly WhatsAppService $whatsAppService,
    ) {
    }

    public function process(array $payload): array
    {
        $event = $this->normalizeEvent($payload['event'] ?? null);

        if ($event !== 'messages.upsert') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'unsupported_event',
            ];
        }

        $instance = trim((string) data_get($payload, 'instance', ''));
        $bot = $this->botForInstance($instance);

        if (! $bot) {
            throw new RuntimeException('İzole Emlak AI botu/instance eşleşmesi bulunamadı.');
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'from_me',
            ];
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));

        if ($remoteJid === '' || str_contains($remoteJid, '@g.us')) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'unsupported_chat',
            ];
        }

        $phoneNumber = trim((string) explode('@', $remoteJid)[0]);

        if ($phoneNumber === '') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'missing_phone',
            ];
        }

        $messageId = trim((string) data_get($payload, 'data.key.id', ''));
        $dedupeKey = $messageId !== ''
            ? 'real_estate_whatsapp_message:'.sha1($instance.'|'.$messageId)
            : null;

        if ($dedupeKey !== null && ! Cache::add($dedupeKey, true, now()->addHours(24))) {
            Log::info('REAL ESTATE DUPLICATE WHATSAPP MESSAGE IGNORED', [
                'message_id' => $messageId,
                'instance' => $instance,
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'duplicate_message',
            ];
        }

        try {
            return $this->processMessage(
                payload: $payload,
                bot: $bot,
                instance: $instance,
                phoneNumber: $phoneNumber,
                messageId: $messageId,
            );
        } catch (Throwable $exception) {
            if ($dedupeKey !== null) {
                Cache::forget($dedupeKey);
            }

            Log::error('REAL ESTATE INBOUND WHATSAPP FAILED', [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => $instance,
                'message_id' => $messageId !== '' ? $messageId : null,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function handleStatusUpdate(array $payload): array
    {
        $instance = trim((string) data_get($payload, 'instance', ''));
        $bot = $this->botForInstance($instance);

        if (! $bot) {
            throw new RuntimeException('İzole Emlak AI status update instance eşleşmesi bulunamadı.');
        }

        $updates = data_get($payload, 'data', []);

        if (! is_array($updates)) {
            return [
                'success' => true,
                'status_update' => true,
                'updated' => 0,
            ];
        }

        $items = array_is_list($updates) ? $updates : [$updates];
        $updated = 0;

        foreach ($items as $update) {
            if (! is_array($update)) {
                continue;
            }

            $messageId = data_get($update, 'key.id')
                ?? data_get($update, 'id')
                ?? data_get($update, 'messageId');

            if (! is_string($messageId) || trim($messageId) === '') {
                continue;
            }

            $status = $this->normalizeStatus(
                data_get($update, 'update.status')
                    ?? data_get($update, 'status')
                    ?? data_get($update, 'message.status')
            );

            $updated += ChatMessage::query()
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
                ->where('whatsapp_message_id', trim($messageId))
                ->update(['status' => $status]);
        }

        return [
            'success' => true,
            'status_update' => true,
            'updated' => $updated,
        ];
    }

    private function processMessage(
        array $payload,
        AiBot $bot,
        string $instance,
        string $phoneNumber,
        string $messageId,
    ): array {
        if (! $this->isolation->organizationValid()) {
            throw new RuntimeException('İzole Emlak AI organizasyon kimliği geçersiz.');
        }

        if (! $bot->whatsappAiKullanilabilirMi()) {
            Log::warning('REAL ESTATE AI SUBSCRIPTION/AI DISABLED', [
                'ai_bot_id' => $bot->id,
                'subscription_status' => $bot->subscription_status,
                'ai_enabled' => (bool) $bot->ai_enabled,
            ]);

            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'ai_unavailable',
            ];
        }

        [$message, $mediaContext] = $this->extractMessage($payload, $instance, $messageId);

        if ($message === '') {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'empty_message',
            ];
        }

        $sessionId = 'whatsapp:'.RealEstateIsolationService::BOT_ID.':'.$phoneNumber;

        $conversation = ConversationControl::query()->firstOrCreate(
            [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'session_id' => $sessionId,
            ],
            [
                'whatsapp_number' => $phoneNumber,
                'customer_name' => null,
                'unread_count' => 0,
                'human_takeover' => false,
            ]
        );

        if (! $this->isolation->supportsConversation($conversation)) {
            throw new RuntimeException('İzole Emlak AI konuşma kapsamı ihlali.');
        }

        $pushName = trim((string) data_get($payload, 'data.pushName', ''));

        $conversation->forceFill([
            'whatsapp_number' => $phoneNumber,
            'customer_name' => trim((string) $conversation->customer_name) !== ''
                ? $conversation->customer_name
                : ($pushName !== '' ? $pushName : null),
            'unread_count' => (int) $conversation->unread_count + 1,
            'last_contact_at' => now(),
        ])->save();

        $this->memoryService->mesajKaydet(
            userId: RealEstateIsolationService::USER_ID,
            aiBotId: RealEstateIsolationService::BOT_ID,
            sessionId: $sessionId,
            role: 'user',
            message: $message,
            senderType: 'customer',
            mediaContext: $mediaContext,
        );

        if ((bool) $conversation->human_takeover) {
            return [
                'success' => true,
                'ignored' => true,
                'reason' => 'human_takeover',
            ];
        }

        $history = $this->memoryService->openAIMesajlariHazirla(
            userId: RealEstateIsolationService::USER_ID,
            sessionId: $sessionId,
            limit: 20,
        );

        $answer = trim($this->openAIService->cevapVer(
            mesajlar: $history,
            aiBot: $bot,
        ));

        if ($answer === '') {
            throw new RuntimeException('Emlak AI boş cevap üretti.');
        }

        $sendResult = $this->whatsAppService->sendText(
            instanceName: $instance,
            number: $phoneNumber,
            text: $answer,
        );

        ChatMessage::query()->create([
            'user_id' => RealEstateIsolationService::USER_ID,
            'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
            'ai_bot_id' => RealEstateIsolationService::BOT_ID,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => $answer,
            'message_type' => 'text',
            'whatsapp_message_id' => data_get($sendResult, 'key.id')
                ?? data_get($sendResult, 'messageId')
                ?? data_get($sendResult, 'id'),
            'status' => 'sent',
        ]);

        $this->consumeTrialMessage($bot);

        Log::info('REAL ESTATE WHATSAPP MESSAGE PROCESSED', [
            'conversation_id' => $conversation->id,
            'message_id' => $messageId !== '' ? $messageId : null,
            'message_type' => $mediaContext['type'] ?? 'text',
            'instance' => $instance,
        ]);

        return [
            'success' => true,
            'message' => 'Emlak AI cevabı gönderildi.',
        ];
    }

    private function extractMessage(
        array $payload,
        string $instance,
        string $messageId,
    ): array {
        $messagePayload = data_get($payload, 'data.message', []);

        if (! is_array($messagePayload)) {
            return ['', []];
        }

        $message = data_get($messagePayload, 'conversation')
            ?? data_get($messagePayload, 'extendedTextMessage.text');

        $context = [
            'type' => 'text',
            'url' => null,
            'mime_type' => null,
            'filename' => null,
            'caption' => null,
            'message_id' => $messageId !== '' ? $messageId : null,
            'instance_name' => $instance,
            'message_envelope' => data_get($payload, 'data', []),
        ];

        if (is_string($message) && trim($message) !== '') {
            return [trim($message), $context];
        }

        $image = data_get($messagePayload, 'imageMessage');

        if (is_array($image)) {
            $context['type'] = 'image';
            $context['url'] = data_get($image, 'url');
            $context['mime_type'] = data_get($image, 'mimetype');
            $context['filename'] = data_get($image, 'fileName') ?? 'Fotoğraf';
            $context['caption'] = data_get($image, 'caption');

            return [
                trim((string) ($context['caption'] ?: '[Fotoğraf]')),
                $context,
            ];
        }

        $document = data_get($messagePayload, 'documentMessage');

        if (is_array($document)) {
            $context['type'] = 'document';
            $context['url'] = data_get($document, 'url');
            $context['mime_type'] = data_get($document, 'mimetype');
            $context['filename'] = data_get($document, 'fileName') ?? 'Belge';
            $context['caption'] = data_get($document, 'caption');

            return [
                trim((string) ($context['caption'] ?: '[Belge]')),
                $context,
            ];
        }

        $video = data_get($messagePayload, 'videoMessage');

        if (is_array($video)) {
            $context['type'] = 'video';
            $context['url'] = data_get($video, 'url');
            $context['mime_type'] = data_get($video, 'mimetype');
            $context['filename'] = data_get($video, 'fileName') ?? 'Video';
            $context['caption'] = data_get($video, 'caption');

            return [
                trim((string) ($context['caption'] ?: '[Video]')),
                $context,
            ];
        }

        $audio = data_get($messagePayload, 'audioMessage');

        if (is_array($audio)) {
            $context['type'] = 'audio';
            $context['url'] = data_get($audio, 'url');
            $context['mime_type'] = data_get($audio, 'mimetype');
            $context['filename'] = 'Sesli mesaj';

            return ['[Sesli mesaj]', $context];
        }

        return ['', $context];
    }

    private function botForInstance(string $instance): ?AiBot
    {
        if ($instance === '') {
            return null;
        }

        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('business_sector', 'real_estate')
            ->where('whatsapp_instance', $instance)
            ->first();

        return $this->isolation->supportsBotIdentity($bot) ? $bot : null;
    }

    private function consumeTrialMessage(AiBot $bot): void
    {
        if ($bot->subscription_status !== 'trial') {
            return;
        }

        $bot->increment('trial_messages_used');
        $bot->refresh();

        if ((int) $bot->trial_messages_used < (int) $bot->trial_message_limit) {
            return;
        }

        $bot->update([
            'trial_messages_used' => $bot->trial_message_limit,
            'trial_completed_at' => $bot->trial_completed_at ?: now(),
            'subscription_status' => 'expired',
        ]);
    }

    private function normalizeEvent(mixed $event): string
    {
        return str_replace(
            ['_', '-'],
            '.',
            strtolower(trim((string) $event))
        );
    }

    private function normalizeStatus(mixed $rawStatus): string
    {
        if (is_numeric($rawStatus)) {
            $rawStatus = match ((int) $rawStatus) {
                0 => 'error',
                1 => 'pending',
                2 => 'sent',
                3 => 'delivered',
                4 => 'read',
                5 => 'played',
                default => 'sent',
            };
        }

        $status = strtolower(trim((string) $rawStatus));

        return match ($status) {
            'pending', 'server_ack' => 'pending',
            'sent', 'device_ack' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'played' => 'played',
            'error', 'failed' => 'error',
            default => $status !== '' ? $status : 'sent',
        };
    }
}
