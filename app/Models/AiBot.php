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
        'openai_model',
        'system_prompt',
        'status',

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
        /*
        |--------------------------------------------------------------------------
        | OTOMATİK TAKİP
        |--------------------------------------------------------------------------
        */

        'follow_up_enabled' => 'boolean',
        'second_follow_up_enabled' => 'boolean',
        'first_follow_up_minutes' => 'integer',
        'second_follow_up_minutes' => 'integer',

        /*
        |--------------------------------------------------------------------------
        | ÜCRETSİZ DENEME
        |--------------------------------------------------------------------------
        */

        'trial_message_limit' => 'integer',
        'trial_messages_used' => 'integer',

        'trial_completed_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | KULLANICI
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ÜRÜNLER
    |--------------------------------------------------------------------------
    */

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | OTOMATİK TAKİPLER
    |--------------------------------------------------------------------------
    */

    public function followUps(): HasMany
    {
        return $this->hasMany(
            ConversationFollowUp::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ÜCRETSİZ DENEME KALAN MESAJ
    |--------------------------------------------------------------------------
    */

    public function kalanDenemeMesaji(): int
    {
        $limit = (int) $this->trial_message_limit;

        $used = (int) $this->trial_messages_used;

        return max(
            0,
            $limit - $used
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DENEME BİTTİ Mİ?
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂ WHATSAPP'TA CEVAP VEREBİLİR Mİ?
    |--------------------------------------------------------------------------
    */

    public function whatsappAiKullanilabilirMi(): bool
    {
        /*
        |--------------------------------------------------------------------------
        | ÜCRETLİ PAKET AKTİF
        |--------------------------------------------------------------------------
        */

        if (
            $this->subscription_status === 'active'
        ) {
            /*
            |--------------------------------------------------------------------------
            | BİTİŞ TARİHİ YOKSA AKTİF
            |--------------------------------------------------------------------------
            */

            if (! $this->subscription_ends_at) {
                return true;
            }

            /*
            |--------------------------------------------------------------------------
            | PAKET SÜRESİ DEVAM EDİYORSA AKTİF
            |--------------------------------------------------------------------------
            */

            return
                $this->subscription_ends_at
                ->isFuture();
        }

        /*
        |--------------------------------------------------------------------------
        | ÜCRETSİZ DENEME
        |--------------------------------------------------------------------------
        */

        if (
            $this->subscription_status === 'trial'
        ) {
            return ! $this->denemeBittiMi();
        }

        /*
        |--------------------------------------------------------------------------
        | EXPIRED / DİĞER DURUMLAR
        |--------------------------------------------------------------------------
        */

        return false;
    }
}