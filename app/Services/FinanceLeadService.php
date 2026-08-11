<?php

namespace App\Services;

use App\Models\AiBot;

class FinanceLeadService
{
    /**
     * Bu servis SADECE Kredi Rehberim botu için çalışır.
     */
    private const AI_BOT_ID = 13;

    /**
     * Desteklenen başvuru türleri.
     */
    public const TYPE_VODAFONE = 'vodafone';

    public const TYPE_TURK_TELEKOM = 'turk_telekom';

    public const TYPE_TURKCELL = 'turkcell';

    public const TYPE_FINDEKS = 'findeks';

    public const TYPE_ELDEN_TAKSIT = 'elden_taksit';

    /**
     * Bu bot finans başvuru sistemini kullanıyor mu?
     */
    public function aktifMi(AiBot $aiBot): bool
    {
        return (int) $aiBot->id === self::AI_BOT_ID;
    }

    /**
     * Her başvuru türünde alınması gereken bilgiler.
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

    /**
     * Başvuru türünün kullanıcıya gösterilecek adı.
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

    /**
     * Alanların WhatsApp grubunda gösterilecek isimleri.
     */
    public function alanBasligi(string $field): string
    {
        return match ($field) {
            'name' =>
                'İsim Soyisim',

            'phone' =>
                'Numara',

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

    /**
     * Başvurunun bütün zorunlu alanları tamamlanmış mı?
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
            $value = $data[$field] ?? null;

            if (
                $value === null
                || trim((string) $value) === ''
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * WhatsApp grubuna gönderilecek başvuru özetini oluştur.
     */
    public function grupMesaji(
        string $type,
        array $data
    ): string {
        $lines = [
            '📋 Yeni '.$this->baslik($type).' Başvurusu',
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
                    (string) ($data[$field] ?? '')
                );
        }

        return implode("\n", $lines);
    }

    /**
     * QR bağlandıktan sonra bu bölüme gerçek group JID'leri
     * yerleştirilecek.
     *
     * Şimdilik null dönmesi özellikle güvenli:
     * yanlış gruba mesaj gönderilmesini engeller.
     */
    public function groupJid(string $type): ?string
    {
        return match ($type) {
            self::TYPE_VODAFONE =>
                null,

            self::TYPE_TURK_TELEKOM =>
                null,

            self::TYPE_TURKCELL =>
                null,

            self::TYPE_FINDEKS =>
                null,

            self::TYPE_ELDEN_TAKSIT =>
                null,

            default =>
                null,
        };
    }
}