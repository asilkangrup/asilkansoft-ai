<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateValuationResearchEvent extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'conversation_control_id',
        'real_estate_profile_id',
        'research_key',
        'profile_fingerprint',
        'model',
        'forced_refresh',
        'confidence_score',
        'source_count',
        'comparable_count',
        'usable_comparable_count',
        'distinct_source_host_count',
        'integrity_status',
        'integrity_quality',
        'market_min',
        'market_max',
        'quick_sale_min',
        'quick_sale_max',
        'investor_buy_min',
        'investor_buy_max',
        'source_host_hashes',
        'comparable_fingerprints',
        'researched_at',
    ];

    protected $casts = [
        'forced_refresh' => 'boolean',
        'confidence_score' => 'integer',
        'source_count' => 'integer',
        'comparable_count' => 'integer',
        'usable_comparable_count' => 'integer',
        'distinct_source_host_count' => 'integer',
        'market_min' => 'decimal:2',
        'market_max' => 'decimal:2',
        'quick_sale_min' => 'decimal:2',
        'quick_sale_max' => 'decimal:2',
        'investor_buy_min' => 'decimal:2',
        'investor_buy_max' => 'decimal:2',
        'source_host_hashes' => 'array',
        'comparable_fingerprints' => 'array',
        'researched_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'conversation_control_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'real_estate_profile_id');
    }
}
