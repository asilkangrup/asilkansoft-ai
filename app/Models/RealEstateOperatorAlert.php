<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateOperatorAlert extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'alert_key',
        'type',
        'severity',
        'status',
        'title',
        'message',
        'payload',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'conversation_control_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'real_estate_profile_id');
    }

    public function resolve(): void
    {
        if ($this->status === 'resolved') {
            return;
        }

        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
