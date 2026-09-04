<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Str;

class WaiSalesSetupOpenAIService extends TenantAwareOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        if (! $this->isWaiSalesBot($aiBot) || ! is_array($mesajlar)) {
            return parent::cevapVer($mesajlar, $aiBot);
        }

        $messages = collect($mesajlar)
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'role' => (string) ($item['role'] ?? ''),
                'content' => trim((string) ($item['content'] ?? $item['message'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['content'] !== '')
            ->values();

        $lastUser = $messages->where('role', 'user')->last()['content'] ?? '';
        $lastAssistant = $messages->where('role', 'assistant')->last()['content'] ?? '';
        $normalizedUser = Str::lower(trim($lastUser));

        if (in_array($normalizedUser, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true)) {
            return $this->welcome();
        }

        if ($lastAssistant === '' || ! $this->isSetupQuestion($lastAssistant)) {
            return $this->welcome();
        }

        if (str_contains($lastAssistant, 'İşletme adınız nedir?')) {
            return 'Hangi sektörde faaliyet gösteriyorsunuz?';
        }

        if (str_contains($lastAssistant, 'Hangi sektörde faaliyet gösteriyorsunuz?')) {
            return 'Yapay zekânın ana görevi ne olsun?';
        }

        if (str_contains($lastAssistant, 'Yapay zekânın ana görevi ne olsun?')) {
            return 'Konuşma üslubunu nasıl istersiniz? (ör. samimi, kurumsal, kısa ve net)';
        }

        if (str_contains($lastAssistant, 'Konuşma üslubunu nasıl istersiniz?')) {
            $answers = $this->extractAnswers($messages->all());

            if ($answers['company'] === '' || $answers['sector'] === '' || $answers['task'] === '') {
                return $this->welcome();
            }

            $description = implode("\n", [
                'Sektör: '.$answers['sector'],
                'Ana görev: '.$answers['task'],
                'Konuşma üslubu: '.($answers['style'] !== '' ? $answers['style'] : 'doğal ve kısa'),
                'Bu demo yapay zekası müşterilerle '.$answers['sector'].' sektörüne uygun, gerçek bir işletme temsilcisi gibi konuşmalıdır.',
            ]);

            $demo = app(WaiLeadDemoService::class)->create([
                'company_name' => $answers['company'],
                'company_description' => $description,
                'role' => 'sales',
            ]);

            if (($demo['status'] ?? null) === 'created' && ! empty($demo['url'])) {
                return "Hazır ✅ Size özel deneme yapay zekânızı oluşturdum. Aşağıdaki linkten hemen müşteri gibi yazıp test edebilirsiniz:\n".$demo['url']."\n\nBeğenirseniz test ekranından WhatsApp'ınıza bağlayıp 1 gün ücretsiz deneyebilirsiniz.";
            }

            return 'Deneme bağlantısı hazırlanırken kısa bir sorun oluştu. Lütfen tekrar deneyin.';
        }

        return $this->welcome();
    }

    private function isWaiSalesBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    private function welcome(): string
    {
        return "1 gün ücretsiz deneyebilirsiniz. Size birkaç kısa soru soracağım; verdiğiniz bilgilere göre deneme yapay zekânızı anlık hazırlayıp test linkini göndereceğim. Beğenirseniz ardından WhatsApp'ınıza bağlayabilirsiniz.\n\nİşletme adınız nedir?";
    }

    private function isSetupQuestion(string $text): bool
    {
        return str_contains($text, 'İşletme adınız nedir?')
            || str_contains($text, 'Hangi sektörde faaliyet gösteriyorsunuz?')
            || str_contains($text, 'Yapay zekânın ana görevi ne olsun?')
            || str_contains($text, 'Konuşma üslubunu nasıl istersiniz?');
    }

    private function extractAnswers(array $messages): array
    {
        $answers = [
            'company' => '',
            'sector' => '',
            'task' => '',
            'style' => '',
        ];

        $pending = null;

        foreach ($messages as $item) {
            if (! is_array($item)) {
                continue;
            }

            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? $item['message'] ?? ''));

            if ($role === 'assistant') {
                $pending = match (true) {
                    str_contains($content, 'İşletme adınız nedir?') => 'company',
                    str_contains($content, 'Hangi sektörde faaliyet gösteriyorsunuz?') => 'sector',
                    str_contains($content, 'Yapay zekânın ana görevi ne olsun?') => 'task',
                    str_contains($content, 'Konuşma üslubunu nasıl istersiniz?') => 'style',
                    default => null,
                };
                continue;
            }

            if ($role === 'user' && $pending !== null && $content !== '') {
                $answers[$pending] = $content;
                $pending = null;
            }
        }

        return $answers;
    }
}
