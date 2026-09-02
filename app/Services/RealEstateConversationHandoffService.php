<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Support\Carbon;

class RealEstateConversationHandoffService
{
    public const IDLE_MINUTES = 30;

    public function status(?ConversationControl $conversation): array
    {
        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [
                'supported' => false,
                'operator_contact_eligible' => false,
                'active_whatsapp_chat' => false,
                'idle_threshold_minutes' => self::IDLE_MINUTES,
                'idle_minutes' => null,
                'last_message_at' => null,
                'last_sender_type' => null,
                'last_message_type' => null,
                'reason' => 'out_of_scope',
            ];
        }

        $latest = ChatMessage::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('session_id', $conversation->session_id)
            ->latest('created_at')
            ->latest('id')
            ->first(['id', 'sender_type', 'message_type', 'created_at']);

        if (! $latest || ! $latest->created_at) {
            return [
                'supported' => true,
                'operator_contact_eligible' => true,
                'active_whatsapp_chat' => false,
                'idle_threshold_minutes' => self::IDLE_MINUTES,
                'idle_minutes' => null,
                'last_message_at' => null,
                'last_sender_type' => null,
                'last_message_type' => null,
                'reason' => 'no_whatsapp_history',
            ];
        }

        /** @var Carbon $lastMessageAt */
        $lastMessageAt = $latest->created_at;
        $idleSeconds = max(0, $lastMessageAt->diffInSeconds(now()));
        $idleMinutes = (int) floor($idleSeconds / 60);
        $eligible = $lastMessageAt->lte(now()->subMinutes(self::IDLE_MINUTES));

        return [
            'supported' => true,
            'operator_contact_eligible' => $eligible,
            'active_whatsapp_chat' => ! $eligible,
            'idle_threshold_minutes' => self::IDLE_MINUTES,
            'idle_minutes' => $idleMinutes,
            'last_message_at' => $lastMessageAt->toIso8601String(),
            'last_sender_type' => $latest->sender_type,
            'last_message_type' => $latest->message_type ?: 'text',
            'reason' => $eligible ? 'whatsapp_idle' : 'whatsapp_active',
        ];
    }

    public function operatorContactEligible(?ConversationControl $conversation): bool
    {
        return (bool) ($this->status($conversation)['operator_contact_eligible'] ?? false);
    }

    public function telemetry(): array
    {
        $conversations = ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->whereHas('realEstateProfiles', fn ($query) => $query->isolatedProduction())
            ->get();

        $statuses = $conversations->map(fn (ConversationControl $conversation): array => $this->status($conversation));

        return [
            'idle_threshold_minutes' => self::IDLE_MINUTES,
            'scoped_conversations' => $conversations->count(),
            'active_whatsapp_chats' => $statuses->where('active_whatsapp_chat', true)->count(),
            'operator_contact_eligible' => $statuses->where('operator_contact_eligible', true)->count(),
            'without_whatsapp_history' => $statuses->where('reason', 'no_whatsapp_history')->count(),
            'follow_up_scheduling_allowed' => false,
            'automatic_outbound_allowed' => false,
        ];
    }
}
