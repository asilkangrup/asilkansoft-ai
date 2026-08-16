<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
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

    /*
    |--------------------------------------------------------------------------
    | HESAP SAHİBİ
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORGANİZASYON
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AI BOT
    |--------------------------------------------------------------------------
    */

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(
            AiBot::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJI GÖNDEREN PERSONEL
    |--------------------------------------------------------------------------
    */

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'sent_by_user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJ KAYNAĞI
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | MESAJ TİPİ
    |--------------------------------------------------------------------------
    */

    public function isText(): bool
    {
        return (
            $this->message_type
            ?: 'text'
        ) === 'text';
    }

    public function isMedia(): bool
    {
        return in_array(
            $this->message_type
            ?: 'text',
            [
                'image',
                'video',
                'audio',
                'document',
            ],
            true
        );
    }
}