<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateOutboundSafetyEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'event_key',
        'inbound_message_id_hash',
        'response_hash',
        'action',
        'recipient_role',
        'reasons',
        'safe_response_version',
        'detected_at',
    ];

    protected $casts = [
        'reasons' => 'array',
        'detected_at' => 'datetime',
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
