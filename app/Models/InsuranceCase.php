<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCase extends Model
{
    protected $fillable = [
        'user_id', 'organization_id', 'conversation_control_id', 'assigned_user_id',
        'source_channel', 'source_reference', 'customer_name', 'phone',
        'status', 'priority', 'policy_type', 'plate', 'license_number',
        'motor_number', 'chassis_number', 'vehicle_brand', 'vehicle_model', 'vehicle_year',
        'open_teklif_id', 'integration_status', 'integration_error', 'last_synced_at', 'data',
    ];

    protected $casts = [
        'data' => 'array',
        'last_synced_at' => 'datetime',
        'vehicle_year' => 'integer',
        'priority' => 'integer',
        'open_teklif_id' => 'integer',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(InsuranceQuoteResult::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InsuranceEvent::class)->latest();
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ConversationControl::class, 'conversation_control_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['issued', 'cancelled']);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'new' => 'Yeni',
            'waiting_vehicle' => 'Araç bilgisi bekliyor',
            'ready_for_open' => 'Open hazır',
            'open_pending' => 'Teklif sorgulanıyor',
            'quoted' => 'Teklif hazır',
            'payment_ready' => 'Ödeme aşaması',
            'issued' => 'Poliçelendi',
            'needs_attention' => 'Kontrol gerekli',
            'failed' => 'Hata',
            'cancelled' => 'İptal',
            default => $this->status,
        };
    }
}
