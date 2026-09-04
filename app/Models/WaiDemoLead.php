<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaiDemoLead extends Model
{
    protected $fillable = [
        'token',
        'company_name',
        'company_description',
        'source',
        'test_message_count',
        'first_opened_at',
        'last_tested_at',
        'whatsapp_connect_started_at',
        'whatsapp_connected_at',
        'temporary_bot_id',
        'whatsapp_instance',
    ];

    protected $casts = [
        'test_message_count' => 'integer',
        'first_opened_at' => 'datetime',
        'last_tested_at' => 'datetime',
        'whatsapp_connect_started_at' => 'datetime',
        'whatsapp_connected_at' => 'datetime',
    ];

    public function temporaryBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'temporary_bot_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaiDemoMessage::class, 'wai_demo_lead_id')->orderBy('id');
    }
}
