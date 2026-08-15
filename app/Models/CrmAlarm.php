<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAlarm extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_control_id',
        'type',
        'severity',
        'title',
        'message',
        'fingerprint',
        'is_resolved',
        'resolved_at',
        'notified_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
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
}