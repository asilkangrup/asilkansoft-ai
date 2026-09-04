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
            ->values()
            ->all();

        $lastUser = '';
        foreach (array_reverse($messages) as $item) {
            if (($item['role'] ?? '') === 'user') {
                $lastUser = (string) ($item['content'] ?? '');
                break;
            }
        }

        $normalizedUser = Str::lower(trim($lastUser));
        if (in_array($normalizedUser, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true)) {
            return $this->welcome();
        }

        $answers = $this->extractAnswers($messages);

        if ($answers['company'] === '') {
            return $this->hasSetupStarted($messages)
                ? 'İşletme adınız nedir?'
                : $this->welcome();
        }

        if ($answers['sector'] === '') {
            return 'Hangi sektörde faaliyet gösteriyorsunuz?';
        }

        if ($answers['task'] === '') {
            return $this->taskQuestion($answers['sector']);
        }

        if ($answers['style'] === '') {
            return 'Konuşma üslubunu nasıl istersiniz? (ör. samimi, kurumsal, kısa ve net)';
        }

        return $this->createDemoLink($answers);
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

    private function taskQuestion(string $sector): string
    {
        $s = Str::lower($sector);

        $examples = match (true) {
            str_contains($s, 'emlak'), str_contains($s, 'gayrimenkul') => 'gelen mesajları cevaplama, ilan bilgisi verme, randevu alma',
            str_contains($s, 'güzellik'), str_contains($s, 'klinik'), str_contains($s, 'estetik') => 'randevu alma, hizmet bilgisi verme, müşteri sorularını yanıtlama',
            str_contains($s, 'gıda'), str_contains($s, 'market'), str_contains($s, 'restoran'), str_contains($s, 'yemek') => 'sipariş alma, ürün bilgisi verme, teslimat sorularını yanıtlama',
            str_contains($s, 'e-ticaret'), str_contains($s, 'eticaret') => 'ürün sorularını yanıtlama, sipariş alma, kargo durumunu açıklama',
            str_contains($s, 'inşaat'), str_contains($s, 'yapı') => 'ürün/hizmet sorularını yanıtlama, teklif ön bilgisi alma, talep toplama',
            str_contains($s, 'otomotiv'), str_contains($s, 'galeri'), str_contains($s, 'oto') => 'araç bilgisi verme, müşteri talebi toplama, randevu oluşturma',
            default => 'müşteri sorularını yanıtlama, talep toplama, randevu veya sipariş alma',
        };

        return 'Yapay zekânın ana görevi ne olsun? (örn. '.$examples.')';
    }

    private function hasSetupStarted(array $messages): bool
    {
        foreach ($messages as $item) {
            if (($item['role'] ?? '') !== 'assistant') {
                continue;
            }

            if ($this->questionField((string) ($item['content'] ?? '')) !== null) {
                return true;
            }
        }

        return false;
    }

    private function extractAnswers(array $messages): array
    {
        $answers = [
            'company' => '',
            'sector' => '',
            'task' => '',
            'style' => '',
        ];

        $start = 0;
        foreach ($messages as $index => $item) {
            if (($item['role'] ?? '') !== 'user') {
                continue;
            }

            $value = Str::lower(trim((string) ($item['content'] ?? '')));
            if (in_array($value, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true)) {
                $start = $index + 1;
                $answers = ['company' => '', 'sector' => '', 'task' => '', 'style' => ''];
            }
        }

        $pending = null;

        for ($i = $start, $count = count($messages); $i < $count; $i++) {
            $item = $messages[$i];
            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));

            if ($role === 'assistant') {
                $field = $this->questionField($content);
                if ($field !== null) {
                    $pending = $field;
                }
                continue;
            }

            if ($role === 'user' && $pending !== null && $content !== '') {
                $answers[$pending] = $content;
                $pending = null;
            }
        }

        return $answers;
    }

    private function questionField(string $text): ?string
    {
        $text = Str::lower($text);

        if (str_contains($text, 'işletme ad') || str_contains($text, 'firma ad')) {
            return 'company';
        }

        if (str_contains($text, 'sektör')) {
            return 'sector';
        }

        if (
            str_contains($text, 'ana görev')
            || str_contains($text, 'görevi ne')
            || str_contains($text, 'ne yapsın')
        ) {
            return 'task';
        }

        if (str_contains($text, 'üslup') || str_contains($text, 'nasıl konuş')) {
            return 'style';
        }

        return null;
    }

    private function createDemoLink(array $answers): string
    {
        $description = implode("\n", [
            'Sektör: '.$answers['sector'],
            'Ana görev: '.$answers['task'],
            'Konuşma üslubu: '.$answers['style'],
            'Bu demo yapay zekası müşterilerle '.$answers['sector'].' sektörüne uygun, gerçek bir işletme temsilcisi gibi konuşmalıdır.',
        ]);

        $demo = app(WaiLeadDemoService::class)->create([
            'company_name' => $answers['company'],
            'company_description' => $description,
            'role' => 'sales',
        ]);

        if (($demo['status'] ?? null) === 'created' && ! empty($demo['url'])) {
            return "Hazır ✅ Deneme yapay zekânızı oluşturdum. Aşağıdaki linkten direkt Test Sohbeti'ne geçebilirsiniz:\n".$demo['url']."\n\nBeğenirseniz test ekranından WhatsApp'ınıza bağlayıp 1 gün ücretsiz deneyebilirsiniz.";
        }

        return 'Deneme bağlantısı hazırlanırken kısa bir sorun oluştu. Lütfen tekrar deneyin.';
    }
}
