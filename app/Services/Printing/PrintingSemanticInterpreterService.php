<?php

namespace App\Services\Printing;

use Throwable;

final class PrintingSemanticInterpreterService
{
    public function enabled(): bool
    {
        return (bool) config('matbaa.enabled') && filled(config('matbaa.api_key'));
    }

    /**
     * Converts short/contextual WhatsApp replies into an explicit sentence that
     * the deterministic order engine can safely consume. It may clarify only
     * what the customer already meant in the current state; it must never invent
     * quantity, material, price, delivery time or design content.
     */
    public function normalize(string $message, array $state, array $recentMessages = []): string
    {
        $original = trim($message);
        if ($original === '' || ! $this->enabled()) {
            return $original;
        }

        try {
            $client = \OpenAI::client((string) config('matbaa.api_key'));
            $history = [];
            foreach (array_slice($recentMessages, -8) as $item) {
                if (! is_array($item) || ! isset($item['role'], $item['content'])) {
                    continue;
                }
                if (! in_array($item['role'], ['user', 'assistant'], true)) {
                    continue;
                }
                $history[] = [
                    'role' => (string) $item['role'],
                    'content' => mb_substr((string) $item['content'], 0, 1000, 'UTF-8'),
                ];
            }

            $system = <<<'PROMPT'
Sen bir matbaa WhatsApp görüşmesinde yalnızca ANLAMLANDIRMA yapan bir ara katmansın.
Görevin müşterinin son mesajını, mevcut sipariş durumu ve beklenen sorulara göre açık bir Türkçe cümleye çevirmektir.

KATI KURALLAR:
- Müşterinin söylemediği hiçbir bilgi ekleme.
- Fiyat, teslim süresi, adet, ölçü, gramaj, ürün veya iletişim bilgisi uydurma.
- Mevcut state'teki ürün ve daha önce kesinleşmiş bilgiler korunabilir; yeni bilgi gibi uydurulamaz.
- Kısa cevabın anlamı bağlamdan açıksa açıklaştır. Belirsizse mesajı aynen döndür.
- "Hazırlayın", "siz yapın", "yapalım" gibi bir cevap, eğer beklenen alan design_status ise "Tasarımım yok, siz hazırlayın." anlamındadır.
- "Hazır", "var", "evet" gibi cevaplar design_status bekleniyorsa ancak tasarım dosyasının hazır olduğunu ifade eder.
- "Standart", "siz seçin", "fark etmez" gibi cevapları yalnızca beklenen teknik alana uygula.
- "Soykan Auto, premium" gibi bir cevap brief:brand_name ve brief:style bekleniyorsa "Firma adı Soykan Auto, tarz premium." olarak açıklaştırılabilir.
- "0536 475 0098" gibi bir cevap brief:content bekleniyorsa "Kartta telefon: 0536 475 0098 yer alsın." olarak açıklaştırılabilir.
- Ürün belli olduktan sonra tekrar ürün seçimi anlamı üretme.

SADECE JSON döndür:
{"normalized":"..."}
PROMPT;

            $messages = [['role' => 'system', 'content' => $system]];
            foreach ($history as $item) {
                $messages[] = $item;
            }
            $messages[] = [
                'role' => 'user',
                'content' => "MEVCUT STATE:\n".
                    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).
                    "\n\nSON MÜŞTERİ MESAJI:\n{$original}",
            ];

            $response = $client->chat()->create([
                'model' => (string) config('matbaa.model', 'gpt-5.6'),
                'messages' => $messages,
                'temperature' => 0,
            ]);

            $content = trim((string) ($response->choices[0]->message->content ?? ''));
            if ($content === '') {
                return $original;
            }

            $content = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $content) ?? $content;
            $decoded = json_decode(trim($content), true);
            $normalized = is_array($decoded) ? trim((string) ($decoded['normalized'] ?? '')) : '';

            if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 1000) {
                return $original;
            }

            return $normalized;
        } catch (Throwable $exception) {
            report($exception);
            return $original;
        }
    }
}
