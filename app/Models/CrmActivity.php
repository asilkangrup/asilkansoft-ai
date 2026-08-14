<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'conversation_control_id',
        'performed_by_user_id',

        'type',
        'title',
        'description',

        'old_value',
        'new_value',

        'meta',
    ];

    protected $casts = [
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

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by_user_id'
        );
    }

    public function actorName(): string
    {
        if ($this->performedByUser) {
            return $this->performedByUser->name;
        }

        return match ($this->type) {
            'ai_score',
            'ai_status',
            'ai_action' =>
                'WAI Yapay Zekâ',

            default =>
                'Sistem',
        };
    }
}