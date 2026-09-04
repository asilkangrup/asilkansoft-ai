<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaiDemoMessage extends Model
{
    protected $fillable = [
        'wai_demo_lead_id',
        'role',
        'message',
    ];

    public function demoLead(): BelongsTo
    {
        return $this->belongsTo(WaiDemoLead::class, 'wai_demo_lead_id');
    }
}
