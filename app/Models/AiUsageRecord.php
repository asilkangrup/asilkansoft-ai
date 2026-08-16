<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'conversation_control_id',

        'provider',
        'model',
        'operation',

        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'reasoning_tokens',
        'total_tokens',

        'estimated_cost_usd',

        'request_id',
        'meta',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'reasoning_tokens' => 'integer',
        'total_tokens' => 'integer',

        'estimated_cost_usd' => 'decimal:6',

        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(
            AiBot::class
        );
    }

    public function conversationControl(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class
        );
    }
}