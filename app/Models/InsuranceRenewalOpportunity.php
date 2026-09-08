<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceRenewalOpportunity extends Model
{
    protected $fillable = [
        'user_id','organization_id','assigned_user_id','customer_name','phone','plate',
        'motor_number','policy_number','insurer','expiry_date','external_policy_detected_at',
        'status','source','source_reference','notes','data',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'external_policy_detected_at' => 'datetime',
        'data' => 'array',
    ];

    public function statusLabel(): string
    {
        return match ($this->status) {
            'monitoring' => 'Takipte',
            'external_renewal_detected' => 'Başka yerde yenilendi',
            'assigned' => 'Satış ekibinde',
            'recovered' => 'Geri kazanıldı',
            'lost' => 'Kaybedildi',
            default => $this->status,
        };
    }
}
