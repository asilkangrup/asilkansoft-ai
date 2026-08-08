<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'ai_bot_id',
        'name',
        'category',
        'price',
        'description',
        'stock_status',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Bu ürünün bağlı olduğu yapay zekâ botu.
     */
    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }
}