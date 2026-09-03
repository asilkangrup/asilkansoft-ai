<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealEstateClosingCase extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'ai_bot_id',
        'seller_profile_id',
        'investor_profile_id',
        'status',
        'agreed_price',
        'title_deed_verified',
        'identity_authority_verified',
        'encumbrance_checked',
        'tax_fee_checked',
        'payment_method_confirmed',
        'appointment_at',
        'appointment_location',
        'deposit_amount',
        'deposit_received',
        'final_payment_verified',
        'deed_transfer_completed',
        'operator_note',
        'closed_at',
        'updated_by_user_id',
    ];

    protected $casts = [
        'agreed_price' => 'integer',
        'title_deed_verified' => 'boolean',
        'identity_authority_verified' => 'boolean',
        'encumbrance_checked' => 'boolean',
        'tax_fee_checked' => 'boolean',
        'payment_method_confirmed' => 'boolean',
        'appointment_at' => 'datetime',
        'deposit_amount' => 'integer',
        'deposit_received' => 'boolean',
        'final_payment_verified' => 'boolean',
        'deed_transfer_completed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'seller_profile_id');
    }

    public function investorProfile(): BelongsTo
    {
        return $this->belongsTo(RealEstateProfile::class, 'investor_profile_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
