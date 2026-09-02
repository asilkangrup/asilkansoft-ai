<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateMediaProcessingEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'event_key',
        'message_id_hash',
        'source_type',
        'mime_type',
        'outcome',
        'reason',
        'declared_bytes',
        'actual_bytes',
        'content_hash',
        'analysis_version',
        'recorded_at',
    ];

    protected $casts = [
        'declared_bytes' => 'integer',
        'actual_bytes' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class,
            'conversation_control_id'
        );
    }
}
