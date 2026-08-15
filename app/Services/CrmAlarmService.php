<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmAlarm;

class CrmAlarmService
{
    public function createOrGet(
        ConversationControl $conversation,
        string $type,
        string $severity,
        string $title,
        string $message,
        string $fingerprint
    ): CrmAlarm {
        return CrmAlarm::query()
            ->firstOrCreate(
                [
                    'fingerprint' => $fingerprint,
                ],
                [
                    'user_id' => $conversation->user_id,
                    'conversation_control_id' => $conversation->id,
                    'type' => $type,
                    'severity' => $severity,
                    'title' => $title,
                    'message' => $message,
                    'is_resolved' => false,
                ]
            );
    }

    public function markNotified(
        CrmAlarm $alarm
    ): void {
        if ($alarm->notified_at) {
            return;
        }

        $alarm->update([
            'notified_at' => now(),
        ]);
    }

    public function resolveClosedConversationAlarms(
        ConversationControl $conversation
    ): void {
        if (
            ! in_array(
                $conversation->lead_status,
                ['won', 'lost'],
                true
            )
        ) {
            return;
        }

        CrmAlarm::query()
            ->where(
                'conversation_control_id',
                $conversation->id
            )
            ->where(
                'is_resolved',
                false
            )
            ->update([
                'is_resolved' => true,
                'resolved_at' => now(),
            ]);
    }
}