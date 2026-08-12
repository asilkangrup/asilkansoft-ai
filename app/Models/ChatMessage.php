<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'session_id',
        'role',
        'sender_type',
        'sent_by_user_id',
        'message',
        'message_type',
        'media_url',
        'media_mime_type',
        'media_filename',
        'media_caption',
        'media_duration',
        'media_size',
        'whatsapp_message_id',
        'status',
    ];

    protected $casts = [
        'media_duration' => 'integer',
        'media_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function musteridenMi(): bool
    {
        return $this->sender_type === 'customer';
    }

    public function yapayZekadanMi(): bool
    {
        return $this->sender_type === 'ai';
    }

    public function insandanMi(): bool
    {
        return $this->sender_type === 'human';
    }

    public function isText(): bool
    {
        return ($this->message_type ?: 'text') === 'text';
    }

    public function isMedia(): bool
    {
        return in_array(
            $this->message_type ?: 'text',
            ['image', 'video', 'audio', 'document'],
            true
        );
    }
}