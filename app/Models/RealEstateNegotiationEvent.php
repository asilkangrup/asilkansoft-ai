<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateNegotiationEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'source_chat_message_id',
        'event_type',
        'actor_role',
        'numeric_value',
        'text_value',
        'previous_numeric_value',
        'previous_text_value',
        'direction',
        'position_key',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'numeric_value' => 'decimal:2',
        'previous_numeric_value' => 'decimal:2',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'conversation_control_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'real_estate_profile_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'source_chat_message_id');
    }
}
