<?php

namespace App\Models;

use App\Observers\RealEstateProfileObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RealEstateProfileObserver::class])]
class RealEstateProfile extends Model
{
    private const ISOLATED_USER_ID = 40;

    private const ISOLATED_ORGANIZATION_ID = 37;

    private const ISOLATED_BOT_ID = 35;

    protected $fillable = [
        'conversation_control_id',
        'user_id',
        'ai_bot_id',
        'profile_type',
        'data',
        'valuation',
        'completeness_score',
        'confidence_score',
        'last_extracted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'valuation' => 'array',
        'completeness_score' => 'integer',
        'confidence_score' => 'integer',
        'last_extracted_at' => 'datetime',
    ];

    /**
     * Restrict a profile query to the one production real-estate tenant.
     *
     * The profile table intentionally does not duplicate organization_id, so
     * organization isolation is anchored to the owning conversation. Every
     * cross-profile operation (matching, valuation context, CRM decisions,
     * audit tools) can use this scope instead of relying on user+bot alone.
     */
    public function scopeIsolatedProduction(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('user_id'), self::ISOLATED_USER_ID)
            ->where($query->qualifyColumn('ai_bot_id'), self::ISOLATED_BOT_ID)
            ->whereHas('conversation', function (Builder $conversation): void {
                $conversation
                    ->where('user_id', self::ISOLATED_USER_ID)
                    ->where('organization_id', self::ISOLATED_ORGANIZATION_ID)
                    ->where('ai_bot_id', self::ISOLATED_BOT_ID);
            });
    }

    public function belongsToIsolatedProductionScope(): bool
    {
        if (
            (int) $this->user_id !== self::ISOLATED_USER_ID
            || (int) $this->ai_bot_id !== self::ISOLATED_BOT_ID
        ) {
            return false;
        }

        return $this->conversation()
            ->where('user_id', self::ISOLATED_USER_ID)
            ->where('organization_id', self::ISOLATED_ORGANIZATION_ID)
            ->where('ai_bot_id', self::ISOLATED_BOT_ID)
            ->exists();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ConversationControl::class,
            'conversation_control_id'
        );
    }

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }
}
