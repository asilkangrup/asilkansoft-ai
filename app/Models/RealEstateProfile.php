<?php

namespace App\Models;

use App\Observers\RealEstateProfileObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RealEstateProfileObserver::class])]
class RealEstateProfile extends Model
{
    protected $fillable = [
        'conversation_control_id',
        'user_id',
        'ai_bot_id',
        'profile_type',
        'data',
        'valuation',
        'completeness_score',
        'confidence_score',
        'last_extracted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'valuation' => 'array',
        'completeness_score' => 'integer',
        'confidence_score' => 'integer',
        'last_extracted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class,
            'conversation_control_id'
        );
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }
}
