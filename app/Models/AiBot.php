<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiBot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'company_name',
        'website',
        'instagram',
        'whatsapp_number',
        'logo_path',
        'role',
        'openai_model',
        'system_prompt',
        'status',

        'company_description',
        'working_hours',
        'cargo_information',
        'payment_information',
        'return_policy',
        'company_rules',

        'whatsapp_status',
        'whatsapp_instance',
        'whatsapp_qr',
        'whatsapp_token',

        /*
        |--------------------------------------------------------------------------
        | OTOMATİK TAKİP AYARLARI
        |--------------------------------------------------------------------------
        */

        'follow_up_enabled',
        'first_follow_up_minutes',
        'first_follow_up_message',
        'second_follow_up_enabled',
        'second_follow_up_minutes',
        'second_follow_up_message',
    ];

    protected $hidden = [
        'whatsapp_token',
    ];

    protected $casts = [
        'follow_up_enabled' => 'boolean',
        'second_follow_up_enabled' => 'boolean',
        'first_follow_up_minutes' => 'integer',
        'second_follow_up_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(ConversationFollowUp::class);
    }
}