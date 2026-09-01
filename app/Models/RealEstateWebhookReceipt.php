<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealEstateWebhookReceipt extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'instance',
        'event',
        'receipt_key',
        'whatsapp_message_id',
        'phone_number',
        'status',
        'attempts',
        'last_error',
        'processing_started_at',
        'processed_at',
        'replied_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'processing_started_at' => 'datetime',
        'processed_at' => 'datetime',
        'replied_at' => 'datetime',
    ];
}
