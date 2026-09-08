<?php

namespace App\Services\Insurance;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Services\MemoryService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InsuranceWhatsAppInboundService
{
    public function supports(AiBot $aiBot): bool
    {
        return strtolower(trim((string) $aiBot->business_sector)) === 'insurance';
    }

    public function processPayload(array $payload): bool
    {
        $instanceName = trim((string) ($payload['instance'] ?? ''));
        if ($instanceName === '') {
            return false;
        }

        $aiBot = AiBot::query()
            ->where('whatsapp_instance', $instanceName)
            ->where('business_sector', 'insurance')
            ->first();

        if (! $aiBot || ! $this->supports($aiBot)) {
            return false;
        }

        $event = strtolower(str_replace(['_', '-'], '.', (string) ($payload['event'] ?? '')));
        if ($event !== 'messages.upsert') {
            return false;
        }

        if ((bool) data_get($payload, 'data.key.fromMe', false)) {
            $this->handleManualTakeover($aiBot, $instanceName, $payload);
            return true;
        }

        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        if ($remoteJid === '' || str_contains($remoteJid, '@g.us')) {
            return true;
        }

        $phoneNumber = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';
        if ($phoneNumber === '') {
            return true;
        }

        $messagePayload = data_get($payload, 'data.message', []);
        if (! is_array($messagePayload)) {
            return false;
        }

        [$messageType, $mimeType, $filename, $caption, $displayMessage] = $this->mediaDetails($messagePayload);
        if (! in_array($messageType, ['image', 'document'], true)) {
            return false;
        }

        $sessionId = 'whatsapp:'.$aiBot->id.':'.$phoneNumber;
        $organizationId = Organization::query()
            ->where('owner_user_id', $aiBot->user_id)
            ->value('id');

        $conversation = ConversationControl::firstOrCreate(
            ['ai_bot_id' => $aiBot->id, 'session_id' => $sessionId],
            [
                'user_id' => $aiBot->user_id,
                'organization_id' => $organizationId,
                'whatsapp_number' => $phoneNumber,
                'customer_name' => null,
                'unread_count' => 0,
                'human_takeover' => false,
            ]
        );

        if ($conversation->organization_id === null && $organizationId !== null) {
            $conversation->forceFill(['organization_id' => $organizationId])->save();
        }

        $pushName = trim((string) data_get($payload, 'data.pushName', ''));
        if ($pushName !== '' && blank($conversation->customer_name)) {
            $conversation->customer_name = $pushName;
        }
        $conversation->unread_count = (int) $conversation->unread_count + 1;
        $conversation->last_contact_at = now();
        $conversation->save();

        if ((bool) $conversation->human_takeover) {
            Log::info('INSURANCE AI PAUSED BY HUMAN TAKEOVER', [
                'ai_bot_id' => $aiBot->id,
                'phone_number' => $phoneNumber,
            ]);
            return true;
        }

        app(MemoryService::class)->mesajKaydet(
            userId: $aiBot->user_id,
            aiBotId: $aiBot->id,
            sessionId: $sessionId,
            role: 'user',
            message: $displayMessage,
        );

        ChatMessage::create([
            'user_id' => $aiBot->user_id,
            'organization_id' => $conversation->organization_id,
            'ai_bot_id' => $aiBot->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => $displayMessage,
            'message_type' => $messageType,
            'media_url' => data_get($messagePayload, $messageType.'Message.url'),
            'media_mime_type' => $mimeType,
            'media_filename' => $filename,
            'media_caption' => $caption,
            'status' => 'received',
            'whatsapp_message_id' => trim((string) data_get($payload, 'data.key.id', '')) ?: null,
        ]);

        $result = $this->process(
            aiBot: $aiBot,
            conversation: $conversation,
            instanceName: $instanceName,
            messageType: $messageType,
            messageEnvelope: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            mediaMimeType: $mimeType,
            mediaCaption: $caption,
        );

        if (! is_array($result) || ! ($result['handled'] ?? false)) {
            return false;
        }

        $answer = trim((string) ($result['answer'] ?? ''));
        if ($answer !== '') {
            $this->sendAnswer($aiBot, $conversation, $instanceName, $phoneNumber, $answer);
        }

        return true;
    }

    public function process(
        AiBot $aiBot,
        ConversationControl $conversation,
        string $instanceName,
        string $messageType,
        array $messageEnvelope,
        ?string $mediaMimeType = null,
        ?string $mediaCaption = null,
    ): ?array {
        if (! $this->supports($aiBot) || ! in_array($messageType, ['image', 'document'], true)) {
            return null;
        }

        try {
            $analysis = app(InsuranceLicenseAnalysisService::class)->analyze(
                aiBot: $aiBot,
                conversation: $conversation,
                instanceName: $instanceName,
                messageEnvelope: $messageEnvelope,
                messageType: $messageType,
                mimeType: $mediaMimeType,
                caption: $mediaCaption,
            );

            if ($analysis['document_type'] !== 'vehicle_license') {
                return [
                    'handled' => true,
                    'answer' => 'Gönderdiğiniz belgeyi inceledim ancak araç ruhsatı bilgilerini güvenli şekilde ayırt edemedim. Ruhsatın tamamının net göründüğü bir fotoğraf gönderebilir misiniz?',
                ];
            }

            $case = app(InsuranceLicenseAnalysisService::class)->persistToCase(
                aiBot: $aiBot,
                conversation: $conversation,
                analysis: $analysis,
            );

            $lines = ['Ruhsat bilgilerini aldım ✓'];
            if ($case->plate) $lines[] = 'Plaka: '.$case->plate;
            if ($case->motor_number) $lines[] = 'Motor No: '.$case->motor_number;
            if ($case->chassis_number) $lines[] = 'Şasi No: '.$case->chassis_number;

            $lines[] = '';
            if ($case->status === 'ready_for_open') {
                $lines[] = app(OpenHizliTeklifClient::class)->configured()
                    ? 'Teklif kaydı hazırlandı; teklif akışına aktarılıyor.'
                    : 'Teklif kaydı hazırlandı. Open bağlantısı aktif edildiğinde aynı kayıt üzerinden teklif süreci devam edecek.';
            } else {
                $lines[] = 'Teklif işlemi için kritik araç bilgilerinden biri eksik görünüyor. Ruhsatın daha net bir fotoğrafını gönderebilirsiniz.';
            }

            return [
                'handled' => true,
                'answer' => implode("\n", $lines),
                'insurance_case_id' => $case->id,
            ];
        } catch (Throwable $e) {
            report($e);
            return [
                'handled' => true,
                'answer' => 'Ruhsatı işlerken geçici bir sorun oluştu. Lütfen fotoğrafın tamamı net görünecek şekilde tekrar gönderin.',
            ];
        }
    }

    private function sendAnswer(
        AiBot $aiBot,
        ConversationControl $conversation,
        string $instanceName,
        string $phoneNumber,
        string $answer,
    ): void {
        $cacheKey = 'wai_api_outbound:'.sha1($instanceName.'|'.$phoneNumber.'|'.$answer);
        Cache::put($cacheKey, true, now()->addMinutes(5));

        $send = app(WhatsAppService::class)->sendText($instanceName, $phoneNumber, $answer);

        ChatMessage::create([
            'user_id' => $aiBot->user_id,
            'organization_id' => $conversation->organization_id,
            'ai_bot_id' => $aiBot->id,
            'session_id' => $conversation->session_id,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => $answer,
            'message_type' => 'text',
            'whatsapp_message_id' => data_get($send, 'key.id') ?? data_get($send, 'messageId') ?? data_get($send, 'id'),
            'status' => 'sent',
        ]);

        app(MemoryService::class)->mesajKaydet(
            userId: $aiBot->user_id,
            aiBotId: $aiBot->id,
            sessionId: $conversation->session_id,
            role: 'assistant',
            message: $answer,
        );
    }

    private function handleManualTakeover(AiBot $aiBot, string $instanceName, array $payload): void
    {
        $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
        $number = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';
        $messagePayload = data_get($payload, 'data.message', []);
        $text = trim((string) (
            data_get($messagePayload, 'conversation')
            ?? data_get($messagePayload, 'extendedTextMessage.text')
            ?? ''
        ));

        if ($number === '' || $text === '') {
            return;
        }

        $key = 'wai_api_outbound:'.sha1($instanceName.'|'.$number.'|'.$text);
        if ((bool) Cache::pull($key, false)) {
            return;
        }

        ConversationControl::query()
            ->where('ai_bot_id', $aiBot->id)
            ->where('whatsapp_number', $number)
            ->update(['human_takeover' => true, 'updated_at' => now()]);

        Log::info('INSURANCE CONVERSATION PAUSED AFTER MANUAL REPLY', [
            'ai_bot_id' => $aiBot->id,
            'phone_number' => $number,
        ]);
    }

    private function mediaDetails(array $messagePayload): array
    {
        $image = data_get($messagePayload, 'imageMessage');
        if (is_array($image)) {
            $caption = data_get($image, 'caption');
            return ['image', data_get($image, 'mimetype'), data_get($image, 'fileName') ?? 'Ruhsat fotoğrafı', $caption, $caption ?: '[Ruhsat fotoğrafı]'];
        }

        $document = data_get($messagePayload, 'documentMessage');
        if (is_array($document)) {
            $caption = data_get($document, 'caption');
            return ['document', data_get($document, 'mimetype'), data_get($document, 'fileName') ?? 'Ruhsat belgesi', $caption, $caption ?: '[Ruhsat belgesi]'];
        }

        return ['text', null, null, null, ''];
    }
}
