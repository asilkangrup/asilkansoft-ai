<?php

namespace App\Services;

use App\Models\CrmAlarm;
use App\Models\CrmAlarmEvent;
use App\Models\User;

class CrmAlarmAuditService
{
    public function record(
        CrmAlarm $alarm,
        string $eventType,
        ?User $user = null,
        ?string $description = null,
        array $meta = []
    ): CrmAlarmEvent {
        return CrmAlarmEvent::query()
            ->create([
                'crm_alarm_id' =>
                    $alarm->id,

                'user_id' =>
                    $user?->id,

                'event_type' =>
                    $eventType,

                'description' =>
                    $description,

                'meta' =>
                    $meta !== []
                        ? $meta
                        : null,
            ]);
    }
}