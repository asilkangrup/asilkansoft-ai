<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealEstateOutboundDelivery extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'instance',
        'delivery_key',
        'inbound_whatsapp_message_id',
        'session_id',
        'phone_number',
        'answer_hash',
        'answer',
        'status',
        'attempts',
        'whatsapp_message_id',
        'last_error',
        'sending_started_at',
        'sent_at',
        'trial_consumed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sending_started_at' => 'datetime',
        'sent_at' => 'datetime',
        'trial_consumed_at' => 'datetime',
    ];
}
