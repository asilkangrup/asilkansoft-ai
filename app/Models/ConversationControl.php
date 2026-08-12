<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationControl extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'session_id',
        'whatsapp_number',
        'human_takeover',
        'taken_over_at',
        'released_at',
    ];

    protected $casts = [
        'human_takeover' => 'boolean',
        'taken_over_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | KULLANICI
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
    | KONUŞMAYI İNSAN DEVRALDI MI?
    |--------------------------------------------------------------------------
    */

    public function insanDevraldiMi(): bool
    {
        return (bool) $this->human_takeover;
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRALSIN
    |--------------------------------------------------------------------------
    */

    public function insanDevral(): void
    {
        $this->update([
            'human_takeover' => true,
            'taken_over_at' => now(),
            'released_at' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂYA GERİ VER
    |--------------------------------------------------------------------------
    */

    public function yapayZekayaGeriVer(): void
    {
        $this->update([
            'human_takeover' => false,
            'released_at' => now(),
        ]);
    }
}