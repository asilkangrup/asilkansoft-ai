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

        // WAI satış hesabında artık zorunlu 4 soruluk demo sihirbazı yok.
        // Satış danışmanı normal LLM akışında, botun sistem promptuna göre
        // ihtiyaç analizi -> telefon görüşmesi -> canlı demo -> kapanış mantığıyla konuşur.
        if ($this->isWaiSalesBot($aiBot)) {
            return parent::cevapVer($mesajlar, $aiBot);
        }

        return parent::cevapVer($mesajlar, $aiBot);
    }

    private function isTemporaryDemoBot(?AiBot $bot): bool
    {
        if (! $bot instanceof AiBot) {
            return false;
        }

        if (! str_starts_with(trim((string) $bot->whatsapp_instance), 'wai-demo-')) {
            return false;
        }

        return ! $this->isWaiSalesBot($bot);
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
        if (! $bot instanceof AiBot) {
            return false;
        }

        if (
            Str::lower(trim((string) $bot->business_sector)) !== 'saas'
            || Str::lower(trim((string) $bot->role)) !== 'sales'
            || Str::lower(trim((string) $bot->name)) !== Str::lower('WAI Satış Danışmanı')
        ) {
            return false;
        }

        $email = Str::lower(trim((string) optional($bot->user)->email));

        return $email === 'soykan@gmail.com';
    }
}
