<?php

namespace App\Services;

use App\Models\AiBot;
use App\Services\AiUsageService;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class FinanceLeadExtractorService
{
    /*
    |--------------------------------------------------------------------------
    | MALİYET / PERFORMANS AYARLARI
    |--------------------------------------------------------------------------
    */

    private const MAX_HISTORY_MESSAGES = 10;

    private const MAX_OUTPUT_TOKENS = 350;

    /**
     * Konuşma geçmişinden finans başvuru verilerini yapılandırılmış olarak çıkarır.
     *
     * Bu servis yalnızca group_routing_enabled = true olan botlarda çalışır.
     */
    public function extract(
        AiBot $aiBot,
        array $messages
    ): array {
        if (! (bool) $aiBot->group_routing_enabled) {
            return $this->emptyResult();
        }

        if ($messages === []) {
            return $this->emptyResult();
        }

        /*
        |--------------------------------------------------------------------------
        | GEREKSİZ OPENAI ÇAĞRISINI ENGELLE
        |--------------------------------------------------------------------------
        |
        | Yalnızca selamlama / teşekkür / vedalaşma gibi başvuru verisi taşımayan
        | müşteri mesajlarında ikinci bir OpenAI çağrısı yapmayız.
        |
        | "evet", "hayır", "tamam" gibi kısa cevaplar özellikle atlanmaz.
        | Bunlar hat sahipliği veya başvuru akışı için gerçek veri olabilir.
        |
        */

        if ($this->yalnizcaSosyalMesajMi($messages)) {
            return $this->emptyResult();
        }

        /*
        |--------------------------------------------------------------------------
        | GEÇMİŞİ SINIRLA
        |--------------------------------------------------------------------------
        |
        | Controller tarafında da geçmiş sınırlandırılıyor. Burada ikinci bir
        | güvenlik katmanı olarak en fazla son 10 mesajı OpenAI'ye gönderiyoruz.
        |
        */

        $messages = array_slice(
            array_values($messages),
            -self::MAX_HISTORY_MESSAGES
        );

        try {
            $model =
                $aiBot->openai_model
                ?: 'gpt-5-mini';

            $request = [
                'model' =>
                    $model,

                'instructions' =>
                    $this->instructions(),

                'input' =>
                    $messages,

                'max_output_tokens' =>
                    self::MAX_OUTPUT_TOKENS,
            ];

            /*
            |--------------------------------------------------------------------------
            | REASONING MALİYETİNİ DÜŞÜR
            |--------------------------------------------------------------------------
            |
            | Bu servis yalnızca mevcut metinden alan çıkarıyor.
            | Derin reasoning gerektirmediği için GPT-5 / o-serisinde low yeterlidir.
            |
            */

            if (
                str_starts_with(
                    $model,
                    'gpt-5'
                )
                || preg_match(
                    '/^o\d/i',
                    $model
                )
            ) {
                $request['reasoning'] = [
                    'effort' =>
                        'low',
                ];
            }

            $response =
                OpenAI::responses()
                    ->create(
                        $request
                    );

            app(AiUsageService::class)->record(
                response: $response,
                operation: 'finance_extractor',
                aiBot: $aiBot,
                meta: [
                    'input_messages' => count($messages),
                ],
            );

            $text = trim(
                (string) (
                    $response->outputText
                    ?? ''
                )
            );

            if ($text === '') {
                return $this->emptyResult();
            }

            $data = json_decode(
                $this->jsonTemizle($text),
                true
            );

            if (! is_array($data)) {
                return $this->emptyResult();
            }

            return $this->normalize($data);

        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyResult();
        }
    }

    /**
     * Son müşteri mesajı yalnızca sosyal / nezaket mesajı mı?
     */
    private function yalnizcaSosyalMesajMi(
        array $messages
    ): bool {
        $lastUserMessage = collect($messages)
            ->reverse()
            ->first(
                fn (array $message): bool =>
                    ($message['role'] ?? null)
                    === 'user'
            );

        if (! is_array($lastUserMessage)) {
            return false;
        }

        $text = trim(
            (string) (
                $lastUserMessage['content']
                ?? ''
            )
        );

        if ($text === '') {
            return true;
        }

        $normalized = Str::lower(
            $this->turkceNormalize(
                $text
            )
        );

        $normalized = preg_replace(
            '/[^\pL\pN\s]+/u',
            ' ',
            $normalized
        ) ?? '';

        $normalized = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $normalized
            ) ?? ''
        );

        $socialMessages = [
            'merhaba',
            'selam',
            'selamlar',
            'gunaydin',
            'iyi gunler',
            'iyi aksamlar',
            'iyi geceler',
            'tesekkurler',
            'tesekkur ederim',
            'tesekkur ederiz',
            'sagol',
            'sag ol',
            'cok sagol',
            'cok sag ol',
            'gorusuruz',
            'iyi calismalar',
            'kolay gelsin',
        ];

        return in_array(
            $normalized,
            $socialMessages,
            true
        );
    }

    /**
     * Extractor'a verilen kesin kurallar.
     */
    private function instructions(): string
    {
        return <<<'PROMPT'
Yalnızca verilen konuşmadan finans başvuru verisi çıkar.

Müşteriyle konuşma, tavsiye verme, açıklama yazma.
Konuşmada açıkça bulunmayan hiçbir bilgiyi üretme.
Emin olmadığın alanı null bırak.
Assistant mesajlarını müşteri cevabı kabul etme.

SADECE şu JSON yapısını döndür:

{
  "type": null,
  "name": null,
  "phone": null,
  "city": null,
  "line_owner": null,
  "mother_maiden_surname": null,
  "limit_score": null,
  "birth_date": null,
  "tc_identity_number": null,
  "limit": null
}

type yalnızca:
"vodafone", "turk_telekom", "turkcell", "findeks", "elden_taksit" veya null.

ALAN KURALLARI

vodafone:
name, phone, city, line_owner

turk_telekom:
name, phone, city, line_owner, mother_maiden_surname
T.C. kimlik numarası çıkarma.

turkcell:
name, phone, city, line_owner, limit_score
limit_score = Turkcell Pasaj limit / puan bilgisi.

findeks:
name, phone, city, birth_date, tc_identity_number

elden_taksit:
name, phone, city, limit

EK KURALLAR

- Telefonu mümkünse sadece rakamlara dönüştür.
- T.C. kimlik numarasını yalnızca findeks akışında çıkar.
- Doğum tarihini mümkünse GG/AA/YYYY biçiminde döndür.
- line_owner bilgisini mümkünse "Evet" veya "Hayır" olarak normalize et.
- Limit veya puan uydurma.
- Birden fazla finans seçeneği geçmişse müşterinin şu anda ilerlediği türü seç.
- Türden emin değilsen type = null.
- JSON dışında hiçbir metin yazma.
PROMPT;
    }

    /**
     * OpenAI bazen JSON'u code fence içinde döndürebilir.
     */
    private function jsonTemizle(
        string $text
    ): string {
        $text = trim($text);

        if (
            str_starts_with(
                $text,
                '```'
            )
        ) {
            $text = preg_replace(
                '/^```(?:json)?\s*/i',
                '',
                $text
            ) ?? $text;

            $text = preg_replace(
                '/\s*```$/',
                '',
                $text
            ) ?? $text;
        }

        return trim($text);
    }

    /**
     * Gelen veriyi güvenli biçimde normalize eder.
     */
    private function normalize(
        array $data
    ): array {
        $allowedTypes = [
            'vodafone',
            'turk_telekom',
            'turkcell',
            'findeks',
            'elden_taksit',
        ];

        $type =
            $data['type']
            ?? null;

        if (
            ! in_array(
                $type,
                $allowedTypes,
                true
            )
        ) {
            $type = null;
        }

        return [
            'type' =>
                $type,

            'name' =>
                $this->nullableString(
                    $data['name']
                    ?? null
                ),

            'phone' =>
                $this->normalizePhone(
                    $data['phone']
                    ?? null
                ),

            'city' =>
                $this->nullableString(
                    $data['city']
                    ?? null
                ),

            'line_owner' =>
                $this->normalizeLineOwner(
                    $data['line_owner']
                    ?? null
                ),

            'mother_maiden_surname' =>
                $this->nullableString(
                    $data['mother_maiden_surname']
                    ?? null
                ),

            'limit_score' =>
                $this->nullableString(
                    $data['limit_score']
                    ?? null
                ),

            'birth_date' =>
                $this->nullableString(
                    $data['birth_date']
                    ?? null
                ),

            'tc_identity_number' =>
                $this->normalizeTc(
                    $data['tc_identity_number']
                    ?? null
                ),

            'limit' =>
                $this->nullableString(
                    $data['limit']
                    ?? null
                ),
        ];
    }

    /**
     * Boş değerleri null yapar.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Telefon numarasını mümkün olduğunca temizler.
     */
    private function normalizePhone(
        mixed $value
    ): ?string {
        $value =
            $this->nullableString(
                $value
            );

        if ($value === null) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        if (
            ! is_string($digits)
            || $digits === ''
        ) {
            return null;
        }

        return $digits;
    }

    /**
     * T.C. kimlik numarası yalnızca 11 rakamsa kabul edilir.
     */
    private function normalizeTc(
        mixed $value
    ): ?string {
        $value =
            $this->nullableString(
                $value
            );

        if ($value === null) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        if (
            ! is_string($digits)
            || strlen($digits) !== 11
        ) {
            return null;
        }

        return $digits;
    }

    /**
     * Hat sahibi bilgisini normalize eder.
     */
    private function normalizeLineOwner(
        mixed $value
    ): ?string {
        $value =
            $this->nullableString(
                $value
            );

        if ($value === null) {
            return null;
        }

        $lower = mb_strtolower(
            $value,
            'UTF-8'
        );

        $yesValues = [
            'evet',
            'benim',
            'kendi adıma',
            'kendi adima',
            'hat benim',
            'benim adıma',
            'benim adima',
        ];

        foreach (
            $yesValues
            as $yesValue
        ) {
            if (
                str_contains(
                    $lower,
                    $yesValue
                )
            ) {
                return 'Evet';
            }
        }

        $noValues = [
            'hayır',
            'hayir',
            'değil',
            'degil',
            'başkasının',
            'baskasinin',
        ];

        foreach (
            $noValues
            as $noValue
        ) {
            if (
                str_contains(
                    $lower,
                    $noValue
                )
            ) {
                return 'Hayır';
            }
        }

        return $value;
    }

    /**
     * Türkçe karakterleri arama / karşılaştırma için normalize eder.
     */
    private function turkceNormalize(
        string $text
    ): string {
        return strtr(
            $text,
            [
                'İ' => 'i',
                'I' => 'i',
                'ı' => 'i',
                'Ş' => 's',
                'ş' => 's',
                'Ğ' => 'g',
                'ğ' => 'g',
                'Ü' => 'u',
                'ü' => 'u',
                'Ö' => 'o',
                'ö' => 'o',
                'Ç' => 'c',
                'ç' => 'c',
            ]
        );
    }

    /**
     * Boş sonuç.
     */
    private function emptyResult(): array
    {
        return [
            'type' => null,
            'name' => null,
            'phone' => null,
            'city' => null,
            'line_owner' => null,
            'mother_maiden_surname' => null,
            'limit_score' => null,
            'birth_date' => null,
            'tc_identity_number' => null,
            'limit' => null,
        ];
    }
}