<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Str;

class WaiAdAwareOpenAIService extends WaiLifecycleOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        if (
            $this->isMainSalesBot($aiBot)
            && is_array($mesajlar)
            && ! $this->setupHasStarted($mesajlar)
            && $this->isGenericAdEntry($this->lastUser($mesajlar))
        ) {
            return "Merhaba 👋 Şu anda WAI canlı demosundasınız. Size sadece 4 kısa soruda işletmenize özel yapay zekânın ne kadar akıllı çalışabildiğini göstereceğim. Cevaplarınıza göre Test Sohbetinizi anlık oluşturacağım; beğenirseniz WhatsApp'ınıza bağlayıp 1 gün ücretsiz deneyebilirsiniz.\n\nİlk olarak işletme adınız nedir?";
        }

        return parent::cevapVer($mesajlar, $aiBot);
    }

    private function isMainSalesBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && (int) $bot->id === 39
            && Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    private function setupHasStarted(array $messages): bool
    {
        foreach ($messages as $message) {
            if (! is_array($message) || ($message['role'] ?? '') !== 'assistant') {
                continue;
            }

            $text = Str::lower(trim((string) ($message['content'] ?? $message['message'] ?? '')));

            if (
                str_contains($text, 'işletme ad')
                || str_contains($text, 'sektör')
                || str_contains($text, 'ana görev')
                || str_contains($text, 'üslup')
                || str_contains($text, 'üslub')
            ) {
                return true;
            }
        }

        return false;
    }

    private function isGenericAdEntry(string $text): bool
    {
        $n = Str::lower(trim($text));

        if (in_array($n, ['merhaba', 'merhabalar', 'selam', 'selamlar'], true)) {
            return true;
        }

        return (str_contains($n, 'bunun hakkında') && (str_contains($n, 'bilgi') || str_contains($n, 'detay')))
            || str_contains($n, 'daha fazla bilgi')
            || str_contains($n, 'bilgi alabilir miyim')
            || str_contains($n, 'bilgi almak istiyorum');
    }

    private function lastUser(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if (is_array($message) && ($message['role'] ?? '') === 'user') {
                return (string) ($message['content'] ?? $message['message'] ?? '');
            }
        }

        return '';
    }
}
