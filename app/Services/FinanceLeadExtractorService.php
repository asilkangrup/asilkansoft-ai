<?php

namespace App\Services;

use App\Models\AiBot;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class FinanceLeadExtractorService
{
    /**
     * Bu extractor sadece Kredi Rehberim botunda çalışır.
     */
    private const AI_BOT_ID = 13;

    /**
     * Konuşma geçmişinden finans başvuru verilerini yapılandırılmış olarak çıkarır.
     */
    public function extract(
        AiBot $aiBot,
        array $messages
    ): array {
        if ((int) $aiBot->id !== self::AI_BOT_ID) {
            return $this->emptyResult();
        }

        if ($messages === []) {
            return $this->emptyResult();
        }

        try {
            $response = OpenAI::responses()->create([
                'model' => $aiBot->openai_model ?: 'gpt-5-mini',

                'instructions' => $this->instructions(),

                'input' => $messages,
            ]);

            $text = trim(
                (string) ($response->outputText ?? '')
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
     * Extractor'a verilen kesin kurallar.
     */
    private function instructions(): string
    {
        return <<<'PROMPT'
Sen yalnızca finans başvuru verisi çıkaran bir sistemsin.

Müşteriyle konuşma yapma.
Cevap üretme.
Tavsiye verme.
Sadece verilen konuşma geçmişindeki bilgileri analiz et.

SADECE aşağıdaki JSON yapısını döndür:

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

TYPE sadece şu değerlerden biri olabilir:

"vodafone"
"turk_telekom"
"turkcell"
"findeks"
"elden_taksit"
null

BAŞVURU TÜRÜ KURALLARI

1. Vodafone konuşmasıysa:
type = "vodafone"

Gerekli alanlar:
- name
- phone
- city
- line_owner

2. Türk Telekom konuşmasıysa:
type = "turk_telekom"

Gerekli alanlar:
- name
- phone
- city
- line_owner
- mother_maiden_surname

Türk Telekom için T.C. kimlik numarası çıkarma.

3. Turkcell konuşmasıysa:
type = "turkcell"

Gerekli alanlar:
- name
- phone
- city
- line_owner
- limit_score

limit_score alanına Turkcell Pasaj limit / puan bilgisini yaz.

4. Findeks / banka kredi danışmanlığı konuşmasıysa:
type = "findeks"

Gerekli alanlar:
- name
- phone
- city
- birth_date
- tc_identity_number

5. Bankasız / Kefilsiz Elden Taksit / Fair Finans konuşmasıysa:
type = "elden_taksit"

Gerekli alanlar:
- name
- phone
- city
- limit

ÇOK ÖNEMLİ KURALLAR

- Konuşmada açıkça bulunmayan hiçbir bilgiyi uydurma.
- Emin olmadığın alanı null bırak.
- Müşterinin daha önce verdiği bilgileri konuşmanın tamamından bul.
- AI'ın sorduğu soruyu müşterinin cevabı sanma.
- Sadece müşterinin verdiği gerçek bilgileri çıkar.
- Telefon numarasını mümkünse sadece rakamlardan oluşan biçime getir.
- T.C. kimlik numarasını sadece Findeks akışında çıkar.
- Doğum tarihini mümkünse GG/AA/YYYY formatında döndür.
- Hat sahibi cevabını mümkünse "Evet" veya "Hayır" olarak normalize et.
- Limit veya puan değerlerini uydurma.
- Bir konuşmada birden fazla finans seçeneği geçmiş olabilir. Müşterinin şu anda aktif olarak ilerlediği başvuru türünü seç.
- Başvuru türünden emin değilsen type alanını null bırak.
- JSON dışında hiçbir metin yazma.
PROMPT;
    }

    /**
     * OpenAI bazen JSON'u code fence içinde döndürebilir.
     */
    private function jsonTemizle(string $text): string
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
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
    private function normalize(array $data): array
    {
        $allowedTypes = [
            'vodafone',
            'turk_telekom',
            'turkcell',
            'findeks',
            'elden_taksit',
        ];

        $type = $data['type'] ?? null;

        if (! in_array($type, $allowedTypes, true)) {
            $type = null;
        }

        return [
            'type' =>
                $type,

            'name' =>
                $this->nullableString(
                    $data['name'] ?? null
                ),

            'phone' =>
                $this->normalizePhone(
                    $data['phone'] ?? null
                ),

            'city' =>
                $this->nullableString(
                    $data['city'] ?? null
                ),

            'line_owner' =>
                $this->normalizeLineOwner(
                    $data['line_owner'] ?? null
                ),

            'mother_maiden_surname' =>
                $this->nullableString(
                    $data['mother_maiden_surname'] ?? null
                ),

            'limit_score' =>
                $this->nullableString(
                    $data['limit_score'] ?? null
                ),

            'birth_date' =>
                $this->nullableString(
                    $data['birth_date'] ?? null
                ),

            'tc_identity_number' =>
                $this->normalizeTc(
                    $data['tc_identity_number'] ?? null
                ),

            'limit' =>
                $this->nullableString(
                    $data['limit'] ?? null
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
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $value
        );

        if (! is_string($digits) || $digits === '') {
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
        $value = $this->nullableString($value);

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
        $value = $this->nullableString($value);

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

        foreach ($yesValues as $yesValue) {
            if (str_contains($lower, $yesValue)) {
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

        foreach ($noValues as $noValue) {
            if (str_contains($lower, $noValue)) {
                return 'Hayır';
            }
        }

        return $value;
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