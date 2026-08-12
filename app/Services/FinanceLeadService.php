<?php

namespace App\Services;

use App\Models\AiBot;

class FinanceLeadService
{
    /*
    |--------------------------------------------------------------------------
    | DESTEKLENEN BAŞVURU TÜRLERİ
    |--------------------------------------------------------------------------
    */

    public const TYPE_VODAFONE = 'vodafone';

    public const TYPE_TURK_TELEKOM = 'turk_telekom';

    public const TYPE_TURKCELL = 'turkcell';

    public const TYPE_FINDEKS = 'findeks';

    public const TYPE_ELDEN_TAKSIT = 'elden_taksit';

    /*
    |--------------------------------------------------------------------------
    | FİNANS GRUP YÖNLENDİRME AKTİF Mİ?
    |--------------------------------------------------------------------------
    |
    | Artık bot ID kontrolü yapılmaz.
    |
    | Sadece group_routing_enabled = true olan botlarda finans başvuru
    | sistemi çalışır.
    |
    */

    public function aktifMi(AiBot $aiBot): bool
    {
        return (bool) $aiBot->group_routing_enabled;
    }

    /*
    |--------------------------------------------------------------------------
    | GEREKLİ ALANLAR
    |--------------------------------------------------------------------------
    */

    public function gerekliAlanlar(string $type): array
    {
        return match ($type) {
            self::TYPE_VODAFONE => [
                'name',
                'phone',
                'city',
                'line_owner',
            ],

            self::TYPE_TURK_TELEKOM => [
                'name',
                'phone',
                'city',
                'line_owner',
                'mother_maiden_surname',
            ],

            self::TYPE_TURKCELL => [
                'name',
                'phone',
                'city',
                'line_owner',
                'limit_score',
            ],

            self::TYPE_FINDEKS => [
                'name',
                'phone',
                'city',
                'birth_date',
                'tc_identity_number',
            ],

            self::TYPE_ELDEN_TAKSIT => [
                'name',
                'phone',
                'city',
                'limit',
            ],

            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | BAŞVURU BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function baslik(string $type): string
    {
        return match ($type) {
            self::TYPE_VODAFONE =>
                'Vodafone',

            self::TYPE_TURK_TELEKOM =>
                'Türk Telekom',

            self::TYPE_TURKCELL =>
                'Turkcell',

            self::TYPE_FINDEKS =>
                'Findeks',

            self::TYPE_ELDEN_TAKSIT =>
                'Elden Taksit',

            default =>
                'Finans Başvurusu',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | ALAN BAŞLIKLARI
    |--------------------------------------------------------------------------
    */

    public function alanBasligi(string $field): string
    {
        return match ($field) {
            'name' =>
                'İsim Soyisim',

            'phone' =>
                'Telefon Numarası',

            'city' =>
                'Yaşadığı İl',

            'line_owner' =>
                'Hat Sahibi',

            'mother_maiden_surname' =>
                'Anne Kızlık Soyadı',

            'limit_score' =>
                'Limit / Puan',

            'birth_date' =>
                'Doğum Tarihi',

            'tc_identity_number' =>
                'T.C. Kimlik No',

            'limit' =>
                'Limit',

            default =>
                $field,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | BAŞVURU TAMAMLANDI MI?
    |--------------------------------------------------------------------------
    */

    public function tamamlandiMi(
        string $type,
        array $data
    ): bool {
        $requiredFields =
            $this->gerekliAlanlar($type);

        if ($requiredFields === []) {
            return false;
        }

        foreach ($requiredFields as $field) {
            $value =
                $data[$field]
                ?? null;

            if (
                $value === null
                || trim((string) $value) === ''
            ) {
                return false;
            }
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP GRUP MESAJI
    |--------------------------------------------------------------------------
    */

    public function grupMesaji(
        string $type,
        array $data
    ): string {
        $lines = [
            '📥 YENİ '
                .mb_strtoupper(
                    $this->baslik($type),
                    'UTF-8'
                )
                .' BAŞVURUSU',
            '',
        ];

        foreach (
            $this->gerekliAlanlar($type)
            as $field
        ) {
            $lines[] =
                $this->alanBasligi($field)
                .': '
                .trim(
                    (string) (
                        $data[$field]
                        ?? ''
                    )
                );
        }

        $lines[] = '';
        $lines[] =
            '🤖 WAI üzerinden otomatik iletildi.';

        return implode(
            "\n",
            $lines
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BAŞVURU TÜRÜNE GÖRE WHATSAPP GRUP JID
    |--------------------------------------------------------------------------
    |
    | Her bot kendi grup ayarlarını taşır.
    |
    | Böylece hiçbir müşteri başka müşterinin grubuna mesaj gönderemez.
    |
    */

    public function groupJid(
        AiBot $aiBot,
        string $type
    ): ?string {
        if (! $this->aktifMi($aiBot)) {
            return null;
        }

        $jid = match ($type) {
            self::TYPE_VODAFONE =>
                $aiBot->vodafone_group_jid,

            self::TYPE_TURK_TELEKOM =>
                $aiBot->turktelekom_group_jid,

            self::TYPE_TURKCELL =>
                $aiBot->turkcell_group_jid,

            self::TYPE_FINDEKS =>
                $aiBot->findeks_group_jid,

            self::TYPE_ELDEN_TAKSIT =>
                $aiBot->elden_taksit_group_jid,

            default =>
                null,
        };

        if (! is_string($jid)) {
            return null;
        }

        $jid = trim($jid);

        if (
            $jid === ''
            || ! str_ends_with(
                $jid,
                '@g.us'
            )
        ) {
            return null;
        }

        return $jid;
    }
}