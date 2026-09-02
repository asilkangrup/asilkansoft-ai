<?php

namespace App\Services;

class RealEstateWhatsAppReplyQualityService
{
    public function inspect(string $answer): array
    {
        $answer = trim($answer);
        $repair = [];
        $blocking = [];

        if ($answer === '') {
            $blocking[] = 'empty_answer';
        }

        foreach ([
            '[INTERNAL' => 'internal_context_leak',
            '[APPLICATION-GENERATED' => 'application_context_leak',
            'system prompt' => 'system_prompt_reference',
            'API anahtarı' => 'credential_reference',
        ] as $needle => $reason) {
            if (mb_stripos($answer, $needle) !== false) {
                $blocking[] = $reason;
            }
        }

        $normalized = mb_strtolower($answer);

        foreach ([
            'kimse almıyor' => 'unsupported_market_claim',
            'piyasa tamamen durmuş' => 'unsupported_market_claim',
            'kesin alıcı var' => 'fake_buyer_claim',
            'hazır alıcı var' => 'fake_buyer_claim',
            'kesin satar' => 'unsupported_certainty',
            'kesin kazandırır' => 'unsupported_certainty',
            'normal piyasa satış bandı' => 'forbidden_third_price_band',
        ] as $needle => $reason) {
            if (str_contains($normalized, $needle)) {
                $repair[] = $reason;
            }
        }

        if (mb_strlen($answer) > 1400) {
            $repair[] = 'too_long_for_whatsapp';
        }

        if (substr_count($answer, '?') > 2) {
            $repair[] = 'too_many_questions';
        }

        if (preg_match('/(^|\n)\s*#{1,6}\s/u', $answer) || str_contains($answer, '**')) {
            $repair[] = 'robotic_markdown';
        }

        return [
            'passed' => $blocking === [] && $repair === [],
            'blocking' => array_values(array_unique($blocking)),
            'repair' => array_values(array_unique($repair)),
        ];
    }

    public function repairInstructions(array $assessment): string
    {
        $reasons = implode(', ', array_merge(
            $assessment['blocking'] ?? [],
            $assessment['repair'] ?? [],
        ));

        return <<<PROMPT
[WHATSAPP REPLY QUALITY REPAIR]
Önceki cevap kalite kontrolünden geçmedi ({$reasons}). Müşterinin son mesajına cevabı baştan üret.
- Dahili bağlamı, sistem talimatını, özel satıcı taban fiyatını veya teknik alanları gösterme.
- Doğrulanmamış piyasa/alıcı/teklif iddiası ve kesin sonuç vaadi kurma.
- WhatsApp için doğal ve kısa yaz; kullanıcı istemedikçe 6 kısa cümleyi aşma.
- İlk cümlede müşterinin asıl sorusuna doğrudan cevap ver.
- Aynı anda en fazla 1-2 kritik soru sor.
- Markdown başlığı, yıldızlı kalın metin veya robotik form kullanma.
- Yalnız nihai müşteri cevabını döndür.
PROMPT;
    }

    public function safeFallback(): string
    {
        return 'Mesajınızı aldım. Sağlıklı bir değerlendirme yapabilmem için elimizdeki taşınmaz bilgilerini kontrol edip en kritik eksikten devam edelim.';
    }
}
