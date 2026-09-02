<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateNextBestActionEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'state_key',
        'action_code',
        'priority',
        'stage',
        'reason_codes',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'reason_codes' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class,
            'conversation_control_id'
        );
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            RealEstateProfile::class,
            'real_estate_profile_id'
        );
    }
}
