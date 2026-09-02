<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Services\RealEstateIsolationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegisterRealEstateMediaCrmFinding implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 30;

    public array $backoff = [2, 5, 10];

    public function __construct(public int $chatMessageId)
    {
        $this->onConnection('database');
        $this->onQueue('real-estate');
    }

    public function handle(): void
    {
        $message = ChatMessage::query()
            ->whereKey($this->chatMessageId)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('sender_type', 'customer')
            ->whereIn('message_type', ['image', 'document'])
            ->first();

        if (! $message || blank($message->whatsapp_message_id)) {
            return;
        }

        $conversation = ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $message->session_id)
            ->first();

        if (! $conversation) {
            return;
        }

        $profile = RealEstateProfile::query()
            ->isolatedProduction()
            ->where('conversation_control_id', $conversation->id)
            ->first();

        if (! $profile) {
            if ($this->attempts() < $this->tries) {
                $this->release(2);
            }

            return;
        }

        if ($profile->profile_type !== 'seller') {
            return;
        }

        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null)
            ? $data['media_findings']
            : [];
        $messageId = trim((string) $message->whatsapp_message_id);

        foreach ($findings as $finding) {
            if (
                is_array($finding)
                && trim((string) ($finding['message_id'] ?? '')) === $messageId
            ) {
                return;
            }
        }

        $isImage = $message->message_type === 'image';
        $findings[] = [
            'message_id' => $messageId,
            'media_category' => $isImage ? 'property_photo' : 'parcel_document',
            'summary' => $isImage
                ? 'WhatsApp fotoğrafı — taşınmaz bilgileri yazılı teyit bekliyor.'
                : 'WhatsApp belgesi — belge türü ve taşınmaz bilgileri yazılı teyit bekliyor.',
            'confidence_score' => 0,
            'operator_classified' => false,
            'legal_verification' => false,
            'vision_analyzed' => false,
            'source' => 'crm_only_media_registration',
            'registered_at' => now()->toIso8601String(),
        ];

        $data['media_findings'] = array_slice($findings, -100);
        $profile->updateQuietly(['data' => $data]);
    }
}
