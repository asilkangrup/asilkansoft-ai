<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateEvidenceEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'event_key',
        'message_id_hash',
        'source_type',
        'mime_type',
        'document_type',
        'provenance_class',
        'confidence_score',
        'field_keys',
        'identity_signals',
        'warning_count',
        'content_fingerprint',
        'analysis_version',
        'recorded_at',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'field_keys' => 'array',
        'identity_signals' => 'array',
        'warning_count' => 'integer',
        'recorded_at' => 'datetime',
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
