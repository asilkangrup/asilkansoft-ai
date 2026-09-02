<?php

namespace App\Models;

use App\Services\RealEstateIsolationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RealEstatePrivateMedia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'organization_id' => 'integer',
            'ai_bot_id' => 'integer',
            'chat_message_id' => 'integer',
            'real_estate_profile_id' => 'integer',
            'size' => 'integer',
        ];
    }

    public function scopeIsolatedProduction(Builder $query): Builder
    {
        return $query
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID);
    }
}
