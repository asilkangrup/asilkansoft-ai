<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Throwable;

class WaiSalesSetupOpenAIService extends TenantAwareOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        if ($this->isTemporaryDemoBot($aiBot) && is_array($mesajlar)) {
            return $this->temporaryDemoReply($mesajlar, $aiBot);
        }

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

        $lastUser = $this->lastUserMessage($messages);
        $normalizedUser = $this->normalize($lastUser);

        if (in_array($normalizedUser, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true)) {
            return $this->welcome();
        }

        $setupStarted = $this->hasSetupStarted($messages);

        if ($this->isWaiQuestion($lastUser)) {
            $withoutLastUser = $messages;
            for ($i = count($withoutLastUser) - 1; $i >= 0; $i--) {
                if (($withoutLastUser[$i]['role'] ?? '') === 'user') {
                    array_splice($withoutLastUser, $i, 1);
                    break;
                }
            }

            $answers = $this->extractAnswers($withoutLastUser);
            $reply = trim(parent::cevapVer($mesajlar, $aiBot));
            $next = $this->nextQuestion($answers, $setupStarted);

            return $next !== '' ? $reply."\n\n".$next : $reply;
        }

        $answers = $this->extractAnswers($messages);
        $next = $this->nextQuestion($answers, $setupStarted);

        if ($next !== '') {
            return $next;
        }

        return $this->createDemoLink($answers);
    }

    private function isTemporaryDemoBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && (int) $bot->id !== 39
            && str_starts_with(trim((string) $bot->whatsapp_instance), 'wai-demo-');
    }

    private function temporaryDemoReply(array $mesajlar, AiBot $bot): string
    {
        $input = collect($mesajlar)
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'role' => in_array(($item['role'] ?? ''), ['user', 'assistant'], true)
                    ? (string) $item['role']
                    : 'user',
                'content' => trim((string) ($item['content'] ?? $item['message'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['content'] !== '')
            ->take(-10)
            ->values()
            ->all();

        if ($input === []) {
            return 'Merhaba 👋 Size nasıl yardımcı olabilirim?';
        }

        $company = trim((string) ($bot->company_name ?: 'İşletme'));
        $description = trim((string) ($bot->company_description ?: ''));

        $instructions = <<<PROMPT
Sen WAI isimli WhatsApp yapay zeka platformunun canlı demo asistanısın.

Şu anda gerçek bir işletmenin WhatsApp müşterisiyle konuşuyormuş gibi davranacaksın.

İŞLETME ADI:
{$company}

İŞLETME HAKKINDA BİLDİĞİN BİLGİLER:
{$description}

GÖREVİN:
Sen satış ve müşteri iletişimi uzmanısın.
Müşterinin ne istediğini doğal şekilde anlamaya çalış.
Ürün veya hizmetle ilgileniyorsa görüşmeyi mantıklı şekilde ilerlet.
Gereksiz baskıcı satış dili kullanma.
Müşteriye aynı anda çok fazla soru sorma.
Uygun olduğunda yalnızca bir sonraki mantıklı soruyu sor.

GENEL KONUŞMA KURALLARI:
- Türkçe konuş.
- WhatsApp'a uygun doğal ve kısa mesajlar yaz.
- Robot gibi konuşma.
- Gereksiz uzun açıklamalar yapma.
- Müşterinin sorduğu soruya doğrudan cevap ver.
- İşletme açıklamasında olmayan fiyat, kampanya, stok, teslimat süresi, adres veya başka bir bilgiyi ASLA uydurma.
- Bilmediğin bir bilgi sorulursa bunu açıkça belirt; stok kontrol etmiş, kayıt açmış veya işlem yapmış gibi davranma.
- Daha önce konuşmada verilen bilgileri tekrar sorma.
- Kullanıcının son mesajını önceki konuşmanın bağlamına göre yorumla.
- Her mesajda genel karşılama cümlesini tekrar etme.
- Müşteri tek kelimelik cevap verse bile önceki konuşmayla bağlantısını kur.
- Satış görevinde müşteriyi doğal biçimde bir sonraki mantıklı adıma ilerlet.
- Bir cevap çoğu durumda 1-4 kısa cümleyi geçmesin.
- Emoji kullanabilirsin fakat abartma.
- Bu bir demo olduğunu müşteriye söyleme.
- Kendini OpenAI, ChatGPT veya başka bir model olarak tanıtma.
- Yalnızca {$company} işletmesinin WhatsApp yapay zeka çalışanı gibi davran.

ÇOK ÖNEMLİ:
Yalnızca sana verilen işletme bilgilerine dayan.
Gerçek olmayan fiyat, stok, kampanya, garanti, teslimat veya işletme politikası üretme.
PROMPT;

        try {
            $response = OpenAI::responses()->create([
                'model' => trim((string) ($bot->openai_model ?: 'gpt-5-mini')),
                'instructions' => $instructions,
                'input' => $input,
            ]);

            $answer = trim((string) $response->outputText);

            return $answer !== ''
                ? $answer
                : 'Şu anda uygun bir yanıt oluşturamadım. Mesajınızı biraz daha açık yazar mısınız?';
        } catch (Throwable $exception) {
            report($exception);

            return 'Şu anda kısa bir bağlantı sorunu yaşıyorum. Lütfen birkaç saniye sonra tekrar yazın.';
        }
    }

    private function isWaiSalesBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && (int) $bot->id === 39
            && Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    private function welcome(): string
    {
        return "Merhaba 👋 Şu anda WAI canlı demosundasınız. Size sadece 4 kısa soruda işletmenize özel yapay zekânın ne kadar akıllı çalışabildiğini göstereceğim. Cevaplarınıza göre Test Sohbetinizi anlık oluşturacağım; beğenirseniz WhatsApp'ınıza bağlayıp 1 gün ücretsiz deneyebilirsiniz.\n\nİlk olarak işletme adınız nedir?";
    }

    private function nextQuestion(array $answers, bool $setupStarted): string
    {
        if ($answers['company'] === '') {
            return $setupStarted ? 'İşletme adınız nedir?' : $this->welcome();
        }

        if ($answers['sector'] === '') {
            return 'Hangi sektörde faaliyet gösteriyorsunuz?';
        }

        if ($answers['task'] === '') {
            return $this->taskQuestion($answers['sector']);
        }

        if ($answers['style'] === '') {
            return 'Konuşma üslubunu nasıl istersiniz? (örn. samimi, kurumsal, kısa ve net)';
        }

        return '';
    }

    private function taskQuestion(string $sector): string
    {
        $s = $this->normalize($sector);

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

            $value = $this->normalize((string) ($item['content'] ?? ''));
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

            if ($role === 'user' && $pending !== null && $content !== '' && ! $this->isWaiQuestion($content)) {
                $answers[$pending] = $content;
                $pending = null;
            }
        }

        return $answers;
    }

    private function questionField(string $text): ?string
    {
        $text = $this->normalize($text);

        if (str_contains($text, 'işletme ad') || str_contains($text, 'şletme ad') || str_contains($text, 'firma ad')) {
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

        if (str_contains($text, 'üslup') || str_contains($text, 'üslub') || str_contains($text, 'nasıl konuş')) {
            return 'style';
        }

        return null;
    }

    private function isWaiQuestion(string $text): bool
    {
        $n = $this->normalize($text);

        if ($n === '') {
            return false;
        }

        foreach ([
            'wai', 'fiyat', 'ücret', 'paket', 'nasıl çalış', 'ne yap', 'özellik',
            'whatsapp', 'entegrasyon', 'kurulum', 'crm', 'yapay zeka', 'yapay zekâ',
            'ne kadar', 'kaç para', 'ücretsiz mi', 'demo nedir',
        ] as $needle) {
            if (str_contains($n, $this->normalize($needle))) {
                return true;
            }
        }

        if (str_contains($text, '?')) {
            return true;
        }

        foreach (['neden ', 'nasıl ', 'nedir', 'ne zaman', 'nerede', 'nereye', 'hangi ', 'kaç ', 'var mı', 'olur mu'] as $questionCue) {
            if (str_contains($n, $questionCue)) {
                return true;
            }
        }

        return false;
    }

    private function lastUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $item) {
            if (($item['role'] ?? '') === 'user') {
                return (string) ($item['content'] ?? '');
            }
        }

        return '';
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(trim($text));
        return str_replace(["i̇", "ı̇"], ['i', 'ı'], $text);
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
            return "Hazır ✅ Size özel deneme yapay zekânızı oluşturdum. Aşağıdaki linkten direkt Test Sohbeti'ne geçebilirsiniz:\n".$demo['url']."\n\nSadece verdiğiniz 4 kısa cevapla ne kadar akıllı ve işletmenize uygun çalışabildiğini keşfedin. Demoyu beğenirseniz yapay zekânız işletmenizin ihtiyaçlarına, süreçlerine ve kurallarına göre çok daha detaylı şekilde tamamen size özel kurgulanacaktır.\n\nBeğenirseniz test ekranından WhatsApp'ınıza bağlayıp 1 gün ücretsiz deneyebilirsiniz.";
        }

        return 'Deneme bağlantısı hazırlanırken kısa bir sorun oluştu. Lütfen tekrar deneyin.';
    }
}
