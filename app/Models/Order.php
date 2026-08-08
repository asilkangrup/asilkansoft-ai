<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'ai_bot_id',
        'session_id',
        'whatsapp_number',
        'customer_name',
        'products',
        'quantity',
        'total_amount',
        'address',
        'city',
        'district',
        'payment_method',

        'shipping_company',
        'tracking_number',
        'tracking_url',

        'shipping_notification_sent_at',
        'confirmed_notification_sent_at',
        'preparing_notification_sent_at',
        'delivered_notification_sent_at',
        'cancelled_notification_sent_at',

        'status',
        'cancellation_reason',

        'customer_note',
        'internal_note',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',

        'shipping_notification_sent_at' => 'datetime',
        'confirmed_notification_sent_at' => 'datetime',
        'preparing_notification_sent_at' => 'datetime',
        'delivered_notification_sent_at' => 'datetime',
        'cancelled_notification_sent_at' => 'datetime',
    ];

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }
}