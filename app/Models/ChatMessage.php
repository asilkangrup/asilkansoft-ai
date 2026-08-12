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

        /*
        |--------------------------------------------------------------------------
        | OPENAI ROLÜ
        |--------------------------------------------------------------------------
        |
        | user
        | assistant
        |
        */

        'role',

        /*
        |--------------------------------------------------------------------------
        | MESAJI KİM GÖNDERDİ?
        |--------------------------------------------------------------------------
        |
        | customer = müşteri
        | ai       = yapay zekâ
        | human    = paneldeki personel
        |
        */

        'sender_type',

        /*
        |--------------------------------------------------------------------------
        | MESAJI GÖNDEREN PERSONEL
        |--------------------------------------------------------------------------
        |
        | sender_type = human olduğunda hangi panel kullanıcısının
        | mesajı gönderdiğini burada tutuyoruz.
        |
        */

        'sent_by_user_id',

        /*
        |--------------------------------------------------------------------------
        | MESAJ
        |--------------------------------------------------------------------------
        */

        'message',
    ];

    /*
    |--------------------------------------------------------------------------
    | KULLANICI / MÜŞTERİ HESABI
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂ BOTU
    |--------------------------------------------------------------------------
    */

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
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
    | MESAJ MÜŞTERİDEN Mİ?
    |--------------------------------------------------------------------------
    */

    public function musteridenMi(): bool
    {
        return $this->sender_type === 'customer';
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJ AI'DAN MI?
    |--------------------------------------------------------------------------
    */

    public function yapayZekadanMi(): bool
    {
        return $this->sender_type === 'ai';
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJ PERSONELDEN Mİ?
    |--------------------------------------------------------------------------
    */

    public function insandanMi(): bool
    {
        return $this->sender_type === 'human';
    }
}