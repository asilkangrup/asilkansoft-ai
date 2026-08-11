<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceLead extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'session_id',
        'whatsapp_number',

        'type',

        'name',
        'phone',
        'city',

        'line_owner',
        'mother_maiden_surname',

        'limit_score',

        'birth_date',
        'tc_identity_number',

        'limit',

        'group_jid',
        'group_message_id',

        'sent_to_group_at',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'sent_to_group_at' => 'datetime',
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
    | YAPAY ZEKA
    |--------------------------------------------------------------------------
    */

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }

    /*
    |--------------------------------------------------------------------------
    | GRUBA GÖNDERİLDİ Mİ?
    |--------------------------------------------------------------------------
    */

    public function grubaGonderildiMi(): bool
    {
        return $this->sent_to_group_at !== null;
    }
}