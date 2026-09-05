<?php

namespace App\Models;

use App\Services\WaiOutreachLeadPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachLead extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'sector',
        'phone_e164',
        'source',
        'source_url',
        'source_published_at',
        'source_checked_at',
        'status',
        'whatsapp_status',
        'priority_score',
        'first_message_text',
        'whatsapp_verified_at',
        'contact_opened_at',
        'replied_at',
        'ai_activated_at',
        'notes',
    ];

    protected $casts = [
        'source_published_at' => 'datetime',
        'source_checked_at' => 'datetime',
        'priority_score' => 'integer',
        'whatsapp_verified_at' => 'datetime',
        'contact_opened_at' => 'datetime',
        'replied_at' => 'datetime',
        'ai_activated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutreachLead $lead): void {
            app(WaiOutreachLeadPolicy::class)->prepareForCreate($lead);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFreshFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw('CASE WHEN source_published_at >= ? THEN 0 ELSE 1 END', [now()->subYear()])
            ->orderByDesc('source_published_at')
            ->orderByDesc('priority_score')
            ->orderByDesc('created_at');
    }

    public function isFreshSource(): bool
    {
        return (bool) $this->source_published_at?->gte(now()->subYear());
    }

    public function whatsappUrl(): string
    {
        $phone = preg_replace('/\D+/', '', $this->phone_e164) ?: '';
        $message = $this->first_message_text ?: "Merhaba kolay gelsin, {$this->company_name} doğru mudur?";

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
