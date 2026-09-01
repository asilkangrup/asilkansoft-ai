<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationFollowUp extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'session_id',
        'whatsapp_number',

        'last_customer_message_at',
        'last_bot_message_at',

        'follow_up_sent_at',

        'first_follow_up_sent_at',
        'second_follow_up_sent_at',

        'is_active',
    ];

    protected $casts = [
        'last_customer_message_at' => 'datetime',
        'last_bot_message_at' => 'datetime',

        'follow_up_sent_at' => 'datetime',

        'first_follow_up_sent_at' => 'datetime',
        'second_follow_up_sent_at' => 'datetime',

        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (ConversationFollowUp $followUp): void {
            // Emlak AI için otomatik takip mesajı ürün gereği kesinlikle yasaktır.
            // Yanlış panel ayarı veya başka bir ortak WAI akışı aktif kayıt üretmeye
            // çalışsa dahi kayıt gönderilebilir duruma gelemez.
            if (
                (int) $followUp->user_id === 40
                && (int) $followUp->ai_bot_id === 35
            ) {
                $followUp->is_active = false;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }
}
