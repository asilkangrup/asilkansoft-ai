<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CrmCustomerExtractorService
{
    /*
    |--------------------------------------------------------------------------
    | CRM MÜŞTERİ BİLGİLERİNİ MESAJDAN ÇIKAR VE UYGULA
    |--------------------------------------------------------------------------
    |
    | Bu servis ekstra OpenAI çağrısı yapmaz.
    |
    | Amaç:
    | - müşteri adı
    | - e-posta adresi
    | - firma adı
    |
    | gibi temel CRM bilgilerini müşterinin kendi mesajından güvenli şekilde
    | yakalamaktır.
    |
    | Mevcut doğru CRM bilgileri gereksiz yere ezilmez.
    |
    */

    public function process(
        ConversationControl $conversation,
        string $message,
        ?string $pushName = null
    ): array {
        $message = trim($message);

        if ($message === '') {
            return $this->emptyResult();
        }

        $extracted = $this->extract($message);

        $changes = [];

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİ ADI
        |--------------------------------------------------------------------------
        |
        | customer_name boşsa yazılır.
        |
        | Ayrıca mevcut isim yalnızca WhatsApp pushName ile aynıysa ve müşteri
        | konuşmada açıkça kendi adını verdiyse gerçek isimle değiştirilmesine
        | izin verilir.
        |
        */

        $newCustomerName =
            $extracted['customer_name']
            ?? null;

        if ($newCustomerName !== null) {
            $currentName = trim(
                (string) $conversation->customer_name
            );

            $normalizedPushName = trim(
                (string) $pushName
            );

            $canUpdateName =
                $currentName === ''
                || (
                    $normalizedPushName !== ''
                    && $this->sameText(
                        $currentName,
                        $normalizedPushName
                    )
                );

            if (
                $canUpdateName
                && ! $this->sameText(
                    $currentName,
                    $newCustomerName
                )
            ) {
                $changes['customer_name'] = [
                    'old' =>
                        $currentName !== ''
                            ? $currentName
                            : null,

                    'new' =>
                        $newCustomerName,
                ];

                $conversation->customer_name =
                    $newCustomerName;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | E-POSTA
        |--------------------------------------------------------------------------
        |
        | İlk güvenilir e-posta kaydedilir.
        | Mevcut dolu e-posta otomatik olarak ezilmez.
        |
        */

        $newEmail =
            $extracted['customer_email']
            ?? null;

        if (
            $newEmail !== null
            && trim(
                (string) $conversation->customer_email
            ) === ''
        ) {
            $changes['customer_email'] = [
                'old' => null,
                'new' => $newEmail,
            ];

            $conversation->customer_email =
                $newEmail;
        }

        /*
        |--------------------------------------------------------------------------
        | FİRMA ADI
        |--------------------------------------------------------------------------
        |
        | İlk açık ve güvenilir firma adı kaydedilir.
        | Mevcut firma adı otomatik olarak ezilmez.
        |
        */

        $newCompanyName =
            $extracted['company_name']
            ?? null;

        if (
            $newCompanyName !== null
            && trim(
                (string) $conversation->company_name
            ) === ''
        ) {
            $changes['company_name'] = [
                'old' => null,
                'new' => $newCompanyName,
            ];

            $conversation->company_name =
                $newCompanyName;
        }

        /*
        |--------------------------------------------------------------------------
        | DEĞİŞİKLİK YOK
        |--------------------------------------------------------------------------
        */

        if ($changes === []) {
            return [
                ...$extracted,
                'changed' => false,
                'changes' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CRM KAYDET
        |--------------------------------------------------------------------------
        */

        try {
            $conversation->save();

            $conversation->refresh();
        } catch (Throwable $exception) {
            Log::warning(
                'WAI CRM CUSTOMER AUTO UPDATE FAILED',
                [
                    'conversation_control_id' =>
                        $conversation->id,

                    'user_id' =>
                        $conversation->user_id,

                    'ai_bot_id' =>
                        $conversation->ai_bot_id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return [
                ...$extracted,
                'changed' => false,
                'changes' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | CRM TIMELINE
        |--------------------------------------------------------------------------
        |
        | Timeline kaydı hata verirse ana WhatsApp sistemi etkilenmez.
        |
        */

        try {
            foreach ($changes as $field => $change) {
                app(
                    CrmActivityService::class
                )->log(
                    conversation: $conversation,
                    type: 'ai_action',
                    title: $this->activityTitle($field),
                    description: $this->activityDescription(
                        field: $field,
                        newValue: $change['new']
                    ),
                    oldValue: $change['old'],
                    newValue: $change['new'],
                    performedBy: null,
                    meta: [
                        'source' =>
                            'customer_message',

                        'field' =>
                            $field,

                        'automatic' =>
                            true,
                    ],
                );
            }
        } catch (Throwable $exception) {
            Log::warning(
                'WAI CRM CUSTOMER ACTIVITY FAILED',
                [
                    'conversation_control_id' =>
                        $conversation->id,

                    'user_id' =>
                        $conversation->user_id,

                    'ai_bot_id' =>
                        $conversation->ai_bot_id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }

        return [
            ...$extracted,
            'changed' => true,
            'changes' => $changes,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SADECE VERİ ÇIKAR
    |--------------------------------------------------------------------------
    */

    public function extract(
        string $message
    ): array {
        $message = trim($message);

        if ($message === '') {
            return $this->emptyResult();
        }

        return [
            'customer_name' =>
                $this->extractCustomerName(
                    $message
                ),

            'customer_email' =>
                $this->extractEmail(
                    $message
                ),

            'company_name' =>
                $this->extractCompanyName(
                    $message
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | E-POSTA ÇIKAR
    |--------------------------------------------------------------------------
    */

    private function extractEmail(
        string $message
    ): ?string {
        if (
            ! preg_match(
                '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/iu',
                $message,
                $matches
            )
        ) {
            return null;
        }

        $email = mb_strtolower(
            trim(
                (string) (
                    $matches[0]
                    ?? ''
                )
            ),
            'UTF-8'
        );

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        return $email;
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ ADI ÇIKAR
    |--------------------------------------------------------------------------
    |
    | Yalnızca müşterinin kendi adını açık şekilde söylediği güçlü kalıplar
    | kabul edilir.
    |
    | Örnek:
    |
    | Ben Ahmet Yılmaz.
    | Benim adım Ahmet Yılmaz.
    | Adım Ahmet Yılmaz.
    | İsmim Ahmet Yılmaz.
    |
    */

    private function extractCustomerName(
        string $message
    ): ?string {
        $patterns = [
            '/\bbenim\s+ad[ıi]m\s+[:\-]?\s*([\p{L}][\p{L}\s\'\-]{2,60})/iu',
            '/\bad[ıi]m\s+[:\-]?\s*([\p{L}][\p{L}\s\'\-]{2,60})/iu',
            '/\bismim\s+[:\-]?\s*([\p{L}][\p{L}\s\'\-]{2,60})/iu',
            '/\bben\s+([\p{L}][\p{L}\s\'\-]{2,60}?)(?=\s+(?:isimli|adlı|olarak|ve|firmasından|şirketinden|şirketi|firması|danışmanıyım|yetkilisiyim)\b|[,.;!?]|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (
                ! preg_match(
                    $pattern,
                    $message,
                    $matches
                )
            ) {
                continue;
            }

            $name = $this->cleanName(
                (string) (
                    $matches[1]
                    ?? ''
                )
            );

            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | FİRMA ADI ÇIKAR
    |--------------------------------------------------------------------------
    |
    | Örnek:
    |
    | ABC İnşaat'tan yazıyorum.
    | ABC Ltd. firmasından yazıyorum.
    | Firmamız ABC İnşaat.
    | Şirketimiz ABC Yazılım.
    | Firma adımız ABC Teknoloji.
    |
    */

    private function extractCompanyName(
        string $message
    ): ?string {
        $patterns = [
            '/\bfirma(?:mız|miz|muz|müz)?\s+(?:ad[ıi](?:mız|miz|muz|müz)?\s*)?[:\-]?\s*([\p{L}\p{N}&.\-\' ]{2,80}?)(?=[,.;!?]|$)/iu',

            '/\bşirket(?:imiz|im|imizin)?\s+(?:ad[ıi](?:mız|miz|muz|müz)?\s*)?[:\-]?\s*([\p{L}\p{N}&.\-\' ]{2,80}?)(?=[,.;!?]|$)/iu',

            '/\b([\p{L}\p{N}&.\-\' ]{2,80}?)\s+(?:firmasından|firmasindan|şirketinden|sirketinden)\s+(?:yazıyorum|yaziyorum|ulaşıyorum|ulasiyorum|arıyorum|ariyorum)/iu',

            '/\b([\p{L}\p{N}&.\-\' ]{2,80}?)\'?(?:dan|den|tan|ten)\s+(?:yazıyorum|yaziyorum|ulaşıyorum|ulasiyorum)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (
                ! preg_match(
                    $pattern,
                    $message,
                    $matches
                )
            ) {
                continue;
            }

            $company = $this->cleanCompanyName(
                (string) (
                    $matches[1]
                    ?? ''
                )
            );

            if ($company !== null) {
                return $company;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | İSİM TEMİZLE
    |--------------------------------------------------------------------------
    */

    private function cleanName(
        string $value
    ): ?string {
        $value = trim($value);

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        /*
        |--------------------------------------------------------------------------
        | CÜMLE DEVAMINI KES
        |--------------------------------------------------------------------------
        */

        $value = preg_split(
            '/\b(?:ve|ama|fakat|mailim|mail|telefonum|numaram|firmam|şirketim|sirketim)\b/iu',
            $value,
            2
        )[0] ?? $value;

        $value = trim(
            $value,
            " \t\n\r\0\x0B,.;:!?-"
        );

        if ($value === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | İSİMDE RAKAM / @ / URL OLMASIN
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/[\d@]/u',
                $value
            )
        ) {
            return null;
        }

        if (
            str_contains(
                mb_strtolower(
                    $value,
                    'UTF-8'
                ),
                'http'
            )
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | KELİME SAYISI
        |--------------------------------------------------------------------------
        |
        | Gerçek kişi adı için 1-5 kelime kabul ediyoruz.
        |
        */

        $words = preg_split(
            '/\s+/u',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (
            ! is_array($words)
            || count($words) < 1
            || count($words) > 5
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | ÇOK KISA / ÇOK UZUN
        |--------------------------------------------------------------------------
        */

        if (
            mb_strlen($value) < 2
            || mb_strlen($value) > 70
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | SAÇMA GENEL İFADELERİ REDDET
        |--------------------------------------------------------------------------
        */

        $normalized =
            $this->normalizeForCompare(
                $value
            );

        $invalidNames = [
            'musteri',
            'musteriyim',
            'firma sahibi',
            'sirket sahibi',
            'yetkili',
            'yetkiliyim',
            'patron',
            'mudur',
            'calisan',
            'personel',
            'insan',
            'ben',
        ];

        if (
            in_array(
                $normalized,
                $invalidNames,
                true
            )
        ) {
            return null;
        }

        return Str::title(
            mb_strtolower(
                $value,
                'UTF-8'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FİRMA ADI TEMİZLE
    |--------------------------------------------------------------------------
    */

    private function cleanCompanyName(
        string $value
    ): ?string {
        $value = trim($value);

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_split(
            '/\b(?:ve|ama|fakat|mailim|telefonum|numaram)\b/iu',
            $value,
            2
        )[0] ?? $value;

        $value = trim(
            $value,
            " \t\n\r\0\x0B,;:!?"
        );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value) < 2
            || mb_strlen($value) > 100
        ) {
            return null;
        }

        if (
            str_contains(
                $value,
                '@'
            )
        ) {
            return null;
        }

        $normalized =
            $this->normalizeForCompare(
                $value
            );

        $invalidCompanies = [
            'firma',
            'firmamiz',
            'firmam',
            'sirket',
            'sirketimiz',
            'sirketim',
            'isletme',
            'isletmemiz',
            'isletmem',
        ];

        if (
            in_array(
                $normalized,
                $invalidCompanies,
                true
            )
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | "BEN ABC..." GİBİ BAŞLANGIÇLARI TEMİZLE
        |--------------------------------------------------------------------------
        */

        $value = preg_replace(
            '/^(?:ben|biz)\s+/iu',
            '',
            $value
        ) ?? $value;

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /*
    |--------------------------------------------------------------------------
    | TIMELINE BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    private function activityTitle(
        string $field
    ): string {
        return match ($field) {
            'customer_name' =>
                'Müşteri adı WAI tarafından öğrenildi',

            'customer_email' =>
                'Müşteri e-postası WAI tarafından öğrenildi',

            'company_name' =>
                'Müşteri firması WAI tarafından öğrenildi',

            default =>
                'CRM bilgisi WAI tarafından güncellendi',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | TIMELINE AÇIKLAMASI
    |--------------------------------------------------------------------------
    */

    private function activityDescription(
        string $field,
        mixed $newValue
    ): string {
        $value = trim(
            (string) $newValue
        );

        return match ($field) {
            'customer_name' =>
                'Müşteri konuşma sırasında adını paylaştı: '
                .$value,

            'customer_email' =>
                'Müşteri konuşma sırasında e-posta adresini paylaştı: '
                .$value,

            'company_name' =>
                'Müşteri konuşma sırasında firma bilgisini paylaştı: '
                .$value,

            default =>
                'Müşteri konuşmasından CRM bilgisi otomatik çıkarıldı.',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | METİN KARŞILAŞTIR
    |--------------------------------------------------------------------------
    */

    private function sameText(
        ?string $first,
        ?string $second
    ): bool {
        return $this->normalizeForCompare(
            (string) $first
        )
        ===
        $this->normalizeForCompare(
            (string) $second
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KARŞILAŞTIRMA NORMALİZASYONU
    |--------------------------------------------------------------------------
    */

    private function normalizeForCompare(
        string $value
    ): string {
        $value = mb_strtolower(
            trim($value),
            'UTF-8'
        );

        $value = strtr(
            $value,
            [
                'ı' => 'i',
                'ş' => 's',
                'ğ' => 'g',
                'ü' => 'u',
                'ö' => 'o',
                'ç' => 'c',
            ]
        );

        $value = preg_replace(
            '/[^\pL\pN]+/u',
            ' ',
            $value
        ) ?? $value;

        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BOŞ SONUÇ
    |--------------------------------------------------------------------------
    */

    private function emptyResult(): array
    {
        return [
            'customer_name' => null,
            'customer_email' => null,
            'company_name' => null,
            'changed' => false,
            'changes' => [],
        ];
    }
}