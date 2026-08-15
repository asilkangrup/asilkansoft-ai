<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSalesGoal extends Model
{
    protected $fillable = [
        'owner_user_id',
        'staff_user_id',
        'month',
        'target_amount',
    ];

    protected $casts = [
        'month' => 'date',
        'target_amount' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_user_id'
        );
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'staff_user_id'
        );
    }
}