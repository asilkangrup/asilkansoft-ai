<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateMatchEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'seller_profile_id',
        'investor_profile_id',
        'seller_conversation_id',
        'investor_conversation_id',
        'pair_key',
        'event_key',
        'status',
        'match_score',
        'grade',
        'estimated_transaction_price',
        'reasons',
        'risks',
        'safety',
        'occurred_at',
    ];

    protected $casts = [
        'match_score' => 'integer',
        'estimated_transaction_price' => 'decimal:2',
        'reasons' => 'array',
        'risks' => 'array',
        'safety' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'seller_profile_id');
    }

    public function investorProfile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'investor_profile_id');
    }

    public function sellerConversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'seller_conversation_id');
    }

    public function investorConversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'investor_conversation_id');
    }
}
