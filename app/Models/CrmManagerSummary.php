<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmManagerSummary extends Model
{
    protected $fillable = [
        'user_id',
        'summary_date',
        'summary',
        'metrics',
        'generated_at',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'metrics' => 'array',
        'generated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }
}