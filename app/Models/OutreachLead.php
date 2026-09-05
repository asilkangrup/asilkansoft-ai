<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachLead extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'phone_e164',
        'source',
        'status',
        'first_message_text',
        'whatsapp_verified_at',
        'contact_opened_at',
        'replied_at',
        'ai_activated_at',
        'notes',
    ];

    protected $casts = [
        'whatsapp_verified_at' => 'datetime',
        'contact_opened_at' => 'datetime',
        'replied_at' => 'datetime',
        'ai_activated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappUrl(): string
    {
        $phone = preg_replace('/\D+/', '', $this->phone_e164) ?: '';
        $message = $this->first_message_text ?: "Merhaba kolay gelsin, {$this->company_name} doğru mudur?";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
