<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAlarmEvent extends Model
{
    protected $fillable = [
        'crm_alarm_id',
        'user_id',
        'event_type',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function alarm(): BelongsTo
    {
        return $this->belongsTo(
            CrmAlarm::class,
            'crm_alarm_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function eventLabel(): string
    {
        return match ($this->event_type) {
            'created' => 'Alarm Oluşturuldu',
            'notified' => 'Bildirim Gönderildi',
            'snoozed' => 'Alarm Ertelendi',
            'snooze_cleared' => 'Erteleme Kaldırıldı',
            'resolved' => 'Alarm Çözüldü',
            'reopened' => 'Alarm Yeniden Açıldı',
            default => 'Alarm Güncellendi',
        };
    }
}