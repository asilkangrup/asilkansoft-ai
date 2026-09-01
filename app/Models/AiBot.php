<?php

namespace App\Models;

use App\Services\BusinessSectorService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

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
        'openai_api_key',
        'system_prompt',
        'status',

        /*
        |--------------------------------------------------------------------------
        | SEKTÖR / LEAD SCORING
        |--------------------------------------------------------------------------
        */

        'business_sector',
        'lead_scoring_profile',

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
        'openai_api_key',
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
    | İZOLE EMLAK AI OPENAI ANAHTARI
    |--------------------------------------------------------------------------
    |
    | Yalnızca fresh Emlak AI botunun (user_id=40, bot_id=35) özel anahtarını
    | APP_KEY ile veritabanında şifreli saklarız. Diğer WAI botlarının mevcut
    | anahtar davranışına dokunmayız. Eski/plaintext bir Emlak AI değeri varsa
    | getter geriye dönük uyumluluk için okuyabilir; bir sonraki kayıtta şifrelenir.
    |
    */

    public function setOpenaiApiKeyAttribute(mixed $value): void
    {
        $normalized = trim((string) ($value ?? ''));

        if (! $this->isIsolatedRealEstateBot()) {
            $this->attributes['openai_api_key'] =
                $normalized === '' ? null : $normalized;

            return;
        }

        $this->attributes['openai_api_key'] =
            $normalized === ''
                ? null
                : Crypt::encryptString($normalized);
    }

    public function getOpenaiApiKeyAttribute(mixed $value): ?string
    {
        $stored = trim((string) ($value ?? ''));

        if ($stored === '') {
            return null;
        }

        if (! $this->isIsolatedRealEstateBot()) {
            return $stored;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }

    private function isIsolatedRealEstateBot(): bool
    {
        return (int) ($this->attributes['user_id'] ?? 0) === 40
            && (int) ($this->attributes['id'] ?? 0) === 35;
    }

    /*
    |--------------------------------------------------------------------------
    | SEKTÖR DEĞİŞİNCE LEAD PROFİLİNİ OTOMATİK AYARLA
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::saving(
            function (AiBot $aiBot): void {
                if (
                    $aiBot->isDirty('business_sector')
                    || trim((string) $aiBot->lead_scoring_profile) === ''
                ) {
                    $aiBot->lead_scoring_profile =
                        BusinessSectorService::profileForSector(
                            $aiBot->business_sector
                        );
                }
            }
        );
    }

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
    | SEKTÖR ETİKETİ
    |--------------------------------------------------------------------------
    */

    public function businessSectorLabel(): string
    {
        return BusinessSectorService::label(
            $this->business_sector
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
