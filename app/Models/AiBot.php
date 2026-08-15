<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiBot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'company_name',
        'website',
        'instagram',
        'whatsapp_number',
        'logo_path',
        'role',
        'lead_scoring_profile',
        'openai_model',
        'system_prompt',
        'status',

        /*
        |--------------------------------------------------------------------------
        | YAPAY ZEKÂ DURUMU
        |--------------------------------------------------------------------------
        */

        'ai_enabled',

        /*
        |--------------------------------------------------------------------------
        | ABONELİK / ÜCRETSİZ DENEME
        |--------------------------------------------------------------------------
        */

        'subscription_status',
        'trial_message_limit',
        'trial_messages_used',
        'trial_completed_at',
        'subscription_started_at',
        'subscription_ends_at',

        /*
        |--------------------------------------------------------------------------
        | FİRMA BİLGİLERİ
        |--------------------------------------------------------------------------
        */

        'company_description',
        'working_hours',
        'cargo_information',
        'payment_information',
        'return_policy',
        'company_rules',

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP
        |--------------------------------------------------------------------------
        */

        'whatsapp_status',
        'whatsapp_instance',
        'whatsapp_qr',
        'whatsapp_token',

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP GRUP YÖNLENDİRME
        |--------------------------------------------------------------------------
        */

        'group_routing_enabled',
        'vodafone_group_jid',
        'turktelekom_group_jid',
        'turkcell_group_jid',
        'findeks_group_jid',
        'elden_taksit_group_jid',

        /*
        |--------------------------------------------------------------------------
        | OTOMATİK TAKİP AYARLARI
        |--------------------------------------------------------------------------
        */

        'follow_up_enabled',
        'first_follow_up_minutes',
        'first_follow_up_message',
        'second_follow_up_enabled',
        'second_follow_up_minutes',
        'second_follow_up_message',
    ];

    protected $hidden = [
        'whatsapp_token',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'group_routing_enabled' => 'boolean',
        'follow_up_enabled' => 'boolean',
        'second_follow_up_enabled' => 'boolean',
        'first_follow_up_minutes' => 'integer',
        'second_follow_up_minutes' => 'integer',
        'trial_message_limit' => 'integer',
        'trial_messages_used' => 'integer',
        'trial_completed_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | LEAD SCORING PROFİLLERİ
    |--------------------------------------------------------------------------
    */

    public static function leadScoringProfiles(): array
    {
        return [
            'general' => 'Genel Satış',
            'ecommerce' => 'E-Ticaret / Ürün Satışı',
            'finance' => 'Finans / Kredi',
            'appointment' => 'Randevu / Klinik / Güzellik',
            'service' => 'Ajans / Profesyonel Hizmet',
            'real_estate' => 'Emlak',
            'automotive' => 'Otomotiv',
            'emergency' => 'Acil Hizmet / Çekici / Usta',
        ];
    }

    public function leadScoringProfileLabel(): string
    {
        return self::leadScoringProfiles()[
            $this->lead_scoring_profile ?: 'general'
        ] ?? 'Genel Satış';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(
            ConversationFollowUp::class
        );
    }

    public function kalanDenemeMesaji(): int
    {
        $limit = (int) $this->trial_message_limit;
        $used = (int) $this->trial_messages_used;

        return max(
            0,
            $limit - $used
        );
    }

    public function denemeBittiMi(): bool
    {
        if (
            $this->subscription_status !== 'trial'
        ) {
            return false;
        }

        return
            $this->trial_messages_used
            >=
            $this->trial_message_limit;
    }

    public function whatsappAiKullanilabilirMi(): bool
    {
        if (! $this->ai_enabled) {
            return false;
        }

        if (
            $this->subscription_status === 'active'
        ) {
            if (! $this->subscription_ends_at) {
                return true;
            }

            return
                $this->subscription_ends_at
                ->isFuture();
        }

        if (
            $this->subscription_status === 'trial'
        ) {
            return ! $this->denemeBittiMi();
        }

        return false;
    }
}