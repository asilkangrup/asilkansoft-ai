<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class ConversationControl extends Model
{
    protected $fillable = [
        'user_id',
        'ai_bot_id',
        'session_id',
        'whatsapp_number',

        /*
        |--------------------------------------------------------------------------
        | KANAL
        |--------------------------------------------------------------------------
        */

        'channel',

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİ BİLGİLERİ
        |--------------------------------------------------------------------------
        */

        'customer_name',
        'customer_email',
        'company_name',
        'unread_count',

        /*
        |--------------------------------------------------------------------------
        | CRM ETİKETLERİ
        |--------------------------------------------------------------------------
        */

        'tags',

        /*
        |--------------------------------------------------------------------------
        | CRM / LEAD
        |--------------------------------------------------------------------------
        */

        'lead_status',
        'lead_score',
        'lead_temperature',

        /*
        |--------------------------------------------------------------------------
        | SORUMLU PERSONEL
        |--------------------------------------------------------------------------
        */

        'assigned_user_id',

        /*
        |--------------------------------------------------------------------------
        | CRM NOTLARI
        |--------------------------------------------------------------------------
        */

        'notes',

        /*
        |--------------------------------------------------------------------------
        | TAKİP / İLETİŞİM
        |--------------------------------------------------------------------------
        */

        'last_contact_at',
        'next_follow_up_at',

        /*
        |--------------------------------------------------------------------------
        | SATIŞ SONUCU
        |--------------------------------------------------------------------------
        */

        'won_at',
        'lost_at',
        'lost_reason',

        /*
        |--------------------------------------------------------------------------
        | AI / İNSAN KONTROLÜ
        |--------------------------------------------------------------------------
        */

        'human_takeover',
        'taken_over_at',
        'released_at',
    ];

    protected $casts = [
        'unread_count' =>
            'integer',

        'tags' =>
            'array',

        'lead_score' =>
            'integer',

        'human_takeover' =>
            'boolean',

        'taken_over_at' =>
            'datetime',

        'released_at' =>
            'datetime',

        'last_contact_at' =>
            'datetime',

        'next_follow_up_at' =>
            'datetime',

        'won_at' =>
            'datetime',

        'lost_at' =>
            'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | KULLANICI
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SORUMLU PERSONEL
    |--------------------------------------------------------------------------
    */

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂ BOTU
    |--------------------------------------------------------------------------
    */

    public function aiBot(): BelongsTo
    {
        return $this->belongsTo(
            AiBot::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CRM AKTİVİTELERİ
    |--------------------------------------------------------------------------
    */

    public function activities(): HasMany
    {
        return $this->hasMany(
            CrmActivity::class
        )
            ->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMAYI İNSAN DEVRALDI MI?
    |--------------------------------------------------------------------------
    */

    public function insanDevraldiMi(): bool
    {
        return (bool) $this->human_takeover;
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRALSIN
    |--------------------------------------------------------------------------
    */

    public function insanDevral(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ZATEN İNSANDA İSE TEKRAR KAYIT OLUŞTURMA
        |--------------------------------------------------------------------------
        */

        if ($this->human_takeover) {
            return;
        }

        $this->update([
            'human_takeover' =>
                true,

            'taken_over_at' =>
                now(),

            'released_at' =>
                null,
        ]);

        $this->refresh();

        /*
        |--------------------------------------------------------------------------
        | CRM TIMELINE
        |--------------------------------------------------------------------------
        |
        | Aktivite kaydı başarısız olsa bile ana insan devralma işlemi bozulmaz.
        |
        */

        try {
            app(
                \App\Services\CrmActivityService::class
            )->humanTakeover(
                conversation: $this,
                performedBy: auth()->user(),
            );
        } catch (\Throwable $exception) {
            Log::warning(
                'CRM HUMAN TAKEOVER ACTIVITY FAILED',
                [
                    'conversation_control_id' =>
                        $this->id,

                    'user_id' =>
                        $this->user_id,

                    'ai_bot_id' =>
                        $this->ai_bot_id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂYA GERİ VER
    |--------------------------------------------------------------------------
    */

    public function yapayZekayaGeriVer(): void
    {
        /*
        |--------------------------------------------------------------------------
        | ZATEN AI'DA İSE TEKRAR KAYIT OLUŞTURMA
        |--------------------------------------------------------------------------
        */

        if (! $this->human_takeover) {
            return;
        }

        $this->update([
            'human_takeover' =>
                false,

            'released_at' =>
                now(),
        ]);

        $this->refresh();

        /*
        |--------------------------------------------------------------------------
        | CRM TIMELINE
        |--------------------------------------------------------------------------
        */

        try {
            app(
                \App\Services\CrmActivityService::class
            )->aiReleased(
                conversation: $this,
                performedBy: auth()->user(),
            );
        } catch (\Throwable $exception) {
            Log::warning(
                'CRM AI RELEASE ACTIVITY FAILED',
                [
                    'conversation_control_id' =>
                        $this->id,

                    'user_id' =>
                        $this->user_id,

                    'ai_bot_id' =>
                        $this->ai_bot_id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | OKUNMAMIŞ MESAJI ARTIR
    |--------------------------------------------------------------------------
    */

    public function okunmamisMesajiArtir(): void
    {
        $this->increment(
            'unread_count'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OKUNMAMIŞ MESAJLARI SIFIRLA
    |--------------------------------------------------------------------------
    */

    public function okunmamisMesajlariSifirla(): void
    {
        if (
            (int) $this->unread_count === 0
        ) {
            return;
        }

        $this->update([
            'unread_count' =>
                0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKETLERİ GETİR
    |--------------------------------------------------------------------------
    */

    public function etiketler(): array
    {
        return is_array(
            $this->tags
        )
            ? $this->tags
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKET EKLE
    |--------------------------------------------------------------------------
    */

    public function etiketEkle(
        string $etiket
    ): void {
        $etiket =
            trim(
                $etiket
            );

        if ($etiket === '') {
            return;
        }

        $etiketler =
            $this->etiketler();

        if (
            ! in_array(
                $etiket,
                $etiketler,
                true
            )
        ) {
            $etiketler[] =
                $etiket;
        }

        $this->update([
            'tags' =>
                array_values(
                    $etiketler
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKET SİL
    |--------------------------------------------------------------------------
    */

    public function etiketSil(
        string $etiket
    ): void {
        $etiketler =
            $this->etiketler();

        $etiketler =
            array_values(
                array_filter(
                    $etiketler,
                    fn ($item) =>
                        $item !== $etiket
                )
            );

        $this->update([
            'tags' =>
                $etiketler,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKET VAR MI?
    |--------------------------------------------------------------------------
    */

    public function etiketiVarMi(
        string $etiket
    ): bool {
        return in_array(
            $etiket,
            $this->etiketler(),
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD DURUM ETİKETİ
    |--------------------------------------------------------------------------
    */

    public function leadStatusLabel(): string
    {
        return match (
            $this->lead_status
        ) {
            'contacted' =>
                'Görüşülüyor',

            'qualified' =>
                'Nitelikli Lead',

            'proposal' =>
                'Teklif Verildi',

            'won' =>
                'Kazanıldı',

            'lost' =>
                'Kaybedildi',

            default =>
                'Yeni Lead',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD SICAKLIK ETİKETİ
    |--------------------------------------------------------------------------
    */

    public function leadTemperatureLabel(): string
    {
        return match (
            $this->lead_temperature
        ) {
            'hot' =>
                'Sıcak',

            'warm' =>
                'Ilık',

            default =>
                'Soğuk',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD PUANINI GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function leadSkoruGuncelle(
        int $score
    ): void {
        $score =
            max(
                0,
                min(
                    100,
                    $score
                )
            );

        $temperature =
            match (true) {
                $score >= 70 =>
                    'hot',

                $score >= 40 =>
                    'warm',

                default =>
                    'cold',
            };

        $this->update([
            'lead_score' =>
                $score,

            'lead_temperature' =>
                $temperature,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LEAD DURUMUNU GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function leadDurumuGuncelle(
        string $status
    ): void {
        $allowed = [
            'new',
            'contacted',
            'qualified',
            'proposal',
            'won',
            'lost',
        ];

        if (
            ! in_array(
                $status,
                $allowed,
                true
            )
        ) {
            return;
        }

        $data = [
            'lead_status' =>
                $status,
        ];

        if ($status === 'won') {
            $data['won_at'] =
                now();

            $data['lost_at'] =
                null;

            $data['lost_reason'] =
                null;
        }

        if ($status === 'lost') {
            $data['lost_at'] =
                now();

            $data['won_at'] =
                null;
        }

        if (
            ! in_array(
                $status,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            $data['won_at'] =
                null;

            $data['lost_at'] =
                null;
        }

        $this->update(
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KAZANILDI OLARAK İŞARETLE
    |--------------------------------------------------------------------------
    */

    public function kazanildi(): void
    {
        $this->leadDurumuGuncelle(
            'won'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KAYBEDİLDİ OLARAK İŞARETLE
    |--------------------------------------------------------------------------
    */

    public function kaybedildi(
        ?string $reason = null
    ): void {
        $this->update([
            'lead_status' =>
                'lost',

            'lost_at' =>
                now(),

            'won_at' =>
                null,

            'lost_reason' =>
                $reason
                    ? trim($reason)
                    : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SON İLETİŞİMİ GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function sonIletisimiGuncelle(): void
    {
        $this->update([
            'last_contact_at' =>
                now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAKİP TARİHİ AYARLA
    |--------------------------------------------------------------------------
    */

    public function takipTarihiAyarla(
        $date
    ): void {
        $this->update([
            'next_follow_up_at' =>
                $date,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TAKİBİ TEMİZLE
    |--------------------------------------------------------------------------
    */

    public function takibiTemizle(): void
    {
        $this->update([
            'next_follow_up_at' =>
                null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KANAL ETİKETİ
    |--------------------------------------------------------------------------
    */

    public function channelLabel(): string
    {
        return match (
            $this->channel
        ) {
            'instagram' =>
                'Instagram',

            'facebook' =>
                'Facebook',

            'web' =>
                'Web',

            default =>
                'WhatsApp',
        };
    }
}