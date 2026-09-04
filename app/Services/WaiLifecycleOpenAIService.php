<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class WaiLifecycleOpenAIService extends WaiSalesSetupOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        if (
            $this->isMainWaiSalesBot($aiBot)
            && is_array($mesajlar)
            && $this->demoLinkAlreadySent($mesajlar)
            && ! $this->isResetRequest($mesajlar)
        ) {
            return $this->postDemoReply($mesajlar);
        }

        return parent::cevapVer($mesajlar, $aiBot);
    }

    private function isMainWaiSalesBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && (int) $bot->id === 39
            && Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    private function demoLinkAlreadySent(array $messages): bool
    {
        $afterLastReset = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? '');
            $content = trim((string) ($message['content'] ?? $message['message'] ?? ''));

            if ($role === 'user' && $this->isResetText($content)) {
                $afterLastReset = [];
                continue;
            }

            $afterLastReset[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        foreach ($afterLastReset as $message) {
            if (
                ($message['role'] ?? '') === 'assistant'
                && str_contains((string) ($message['content'] ?? ''), '/demo/lead/')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isResetRequest(array $messages): bool
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (! is_array($messages[$i]) || ($messages[$i]['role'] ?? '') !== 'user') {
                continue;
            }

            return $this->isResetText((string) ($messages[$i]['content'] ?? $messages[$i]['message'] ?? ''));
        }

        return false;
    }

    private function isResetText(string $text): bool
    {
        $text = Str::lower(trim($text));

        return in_array($text, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true);
    }

    private function postDemoReply(array $messages): string
    {
        $input = collect($messages)
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'role' => in_array(($item['role'] ?? ''), ['user', 'assistant'], true)
                    ? (string) $item['role']
                    : 'user',
                'content' => trim((string) ($item['content'] ?? $item['message'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['content'] !== '')
            ->take(-12)
            ->values()
            ->all();

        $instructions = <<<'PROMPT'
Sen WAI'nin kıdemli satış danışmanısın.

Müşteri daha önce işletmesine özel Test Sohbeti linkini aldı. Artık kurulum sorularına geri dönme ve işletme adı/sektör/görev/üslup bilgilerini tekrar isteme.

Müşteri fiyat, paket, özellik, WhatsApp bağlantısı, yapay zekanın neler yapabildiği, entegrasyon veya kullanım hakkında ne sorarsa WAI'ye uygun, kısa, net ve profesyonel cevap ver.

WAI; işletmeye özel WhatsApp yapay zekası oluşturur. Müşterileri karşılayabilir, soruları yanıtlayabilir, işletmenin verdiği ürün/hizmet ve firma bilgilerine göre konuşabilir, gerekli bilgileri sohbet içinde toplayabilir ve işletmenin ihtiyacına göre özel iş akışları kurulabilir.

Fiyat veya paket sorulursa sistemde doğrulanmış net bir fiyat yoksa rakam uydurma. Kapsamın işletmenin ihtiyacına göre belirlendiğini söyle. Kullanıcının ihtiyacı için teknik veya ticari detay gerekiyorsa ekip tarafından netleştirileceğini belirt.

Olmayan entegrasyonu, kampanyayı, fiyatı veya özelliği kesin varmış gibi söyleme. Gerektiğinde "teknik yapısını kontrol edip netleştirebiliriz" de.

Müşteri ilgisini kaybetmeden cevapları çoğunlukla 1-4 kısa cümlede tut. Aynı soruyu tekrar sorma. Demo kurulum akışına dönme.

Her cevabın sonunda doğal biçimde şu cümleyi kullan:
"Ekip arkadaşlarımız sizi arayacak."
PROMPT;

        try {
            $response = OpenAI::responses()->create([
                'model' => 'gpt-5-mini',
                'instructions' => $instructions,
                'input' => $input,
            ]);

            $answer = trim((string) $response->outputText);

            if ($answer === '') {
                return 'Detayları ihtiyacınıza göre netleştirebiliriz. Ekip arkadaşlarımız sizi arayacak.';
            }

            if (! str_contains(Str::lower($answer), 'ekip arkadaşlarımız sizi arayacak')) {
                $answer = rtrim($answer)."\n\nEkip arkadaşlarımız sizi arayacak.";
            }

            return $answer;
        } catch (Throwable $exception) {
            report($exception);

            return 'Detayları ihtiyacınıza göre netleştirebiliriz. Ekip arkadaşlarımız sizi arayacak.';
        }
    }
}
