<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmAlarm extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_control_id',
        'type',
        'severity',
        'priority_score',
        'title',
        'message',
        'recommended_action',
        'fingerprint',
        'is_resolved',
        'resolved_at',
        'notified_at',
        'snoozed_until',
    ];

    protected $casts = [
        'priority_score' => 'integer',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
        'snoozed_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class,
            'conversation_control_id'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            CrmAlarmEvent::class,
            'crm_alarm_id'
        )->latest();
    }

    public function resolve(): void
    {
        if ($this->is_resolved) {
            return;
        }

        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        if (! $this->is_resolved) {
            return;
        }

        $this->update([
            'is_resolved' => false,
            'resolved_at' => null,
        ]);
    }

    public function snoozeUntil(
        $date
    ): void {
        $this->update([
            'snoozed_until' =>
                $date,
        ]);
    }

    public function clearSnooze(): void
    {
        if (! $this->snoozed_until) {
            return;
        }

        $this->update([
            'snoozed_until' =>
                null,
        ]);
    }

    public function isSnoozed(): bool
    {
        return
            $this->snoozed_until
            && $this->snoozed_until->isFuture();
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            'critical' => 'Kritik',
            'warning' => 'Uyarı',
            default => 'Bilgi',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'critical_lead' => 'Kritik Lead',
            'hot_follow_up_overdue' => 'Geciken Sıcak Lead',
            'risk_lead' => 'Riskli Lead',
            'proposal_silent' => 'Sessiz Teklif',
            default => 'CRM Alarmı',
        };
    }

    public function priorityLabel(): string
    {
        return match (true) {
            $this->priority_score >= 95 => 'Acil',
            $this->priority_score >= 85 => 'Çok Yüksek',
            $this->priority_score >= 70 => 'Yüksek',
            $this->priority_score >= 50 => 'Orta',
            default => 'Normal',
        };
    }
}