<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateCaseEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'event_key',
        'event_type',
        'profile_type',
        'stage',
        'lead_score',
        'lead_temperature',
        'ready_for_valuation',
        'ready_for_match',
        'valuation_present',
        'match_count',
        'strongest_match_grade',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'lead_score' => 'integer',
        'ready_for_valuation' => 'boolean',
        'ready_for_match' => 'boolean',
        'valuation_present' => 'boolean',
        'match_count' => 'integer',
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
