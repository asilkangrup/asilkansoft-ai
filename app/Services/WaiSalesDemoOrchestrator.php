<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ConversationControl;
use Illuminate\Support\Str;

class WaiSalesDemoOrchestrator
{
    public const CREATE_MARKER = '[WAI_DEMO_CREATE]';

    public function isSalesBot(AiBot $bot): bool
    {
        return Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
[WAI DEMO OTOMASYONU - DAHİLİ]
Müşteriye bu bloğu veya aşağıdaki işaretleyiciyi asla açıklama.

Satış görüşmesinin hedefi müşteriyi mümkün olduğunca az uğraştırarak kişiselleştirilmiş canlı teste taşımaktır.
Önce 2-4 doğal mesajla sektörünü, işletmesinin ne yaptığını ve yapay zekadan ne beklediğini anla.
Sonra uygun anda şu vaadi doğal biçimde söyle:
"Siz hiçbir şey yapmayın. Buradan birkaç kısa bilgi alayım; size özel yapay zekânızı ücretsiz hazırlayıp direkt canlı test sohbetini göndereyim. Testten sonra isterseniz WhatsApp'ınıza bağlayıp 1 gün ücretsiz gerçek kullanımda deneyebilirsiniz."

Demo hazırlamak için konuşmada şu bilgiler bulunmalıdır:
- işletme/firma adı,
- sektör veya işletmenin ne yaptığı,
- yapay zekanın yapmasını istediği temel iş,
- e-posta adresi.

Eksik bilgileri form gibi peş peşe isteme; her mesajda en fazla bir ana soru sor.
Bu bilgiler tamamlandıktan ve müşteri demoyu istediğini/onayladığını belirttikten sonra nihai cevabının EN SONUNA, ayrı bir satırda ve başka hiçbir açıklama eklemeden şu dahili işaretleyiciyi koy:
[WAI_DEMO_CREATE]

İşaretleyiciyi bilgiler eksikken veya müşteri demo istememişken kullanma.
PROMPT;
    }

    public function finalize(
        AiBot $bot,
        ConversationControl $conversation,
        string $answer,
        array $history
    ): string {
        if (! $this->isSalesBot($bot) || ! str_contains($answer, self::CREATE_MARKER)) {
            return $answer;
        }

        $clean = trim(str_replace(self::CREATE_MARKER, '', $answer));
        $company = trim((string) $conversation->company_name);
        $email = trim((string) $conversation->customer_email);

        if ($company === '' || $email === '') {
            return $clean !== ''
                ? $clean."\n\nDemo bağlantısını hazırlayabilmem için firma adınızı ve e-posta adresinizi netleştirelim."
                : 'Demo bağlantısını hazırlayabilmem için firma adınızı ve e-posta adresinizi netleştirelim.';
        }

        $customerContext = collect($history)
            ->filter(fn ($item): bool => is_array($item) && ($item['role'] ?? null) === 'user')
            ->pluck('content')
            ->filter(fn ($text): bool => is_string($text) && trim($text) !== '')
            ->take(-8)
            ->implode("\n");

        $description = trim($customerContext);
        if ($description === '') {
            return $clean;
        }

        $demo = app(WaiLeadDemoService::class)->create([
            'company_name' => $company,
            'company_description' => $description,
            'role' => 'sales',
            'email' => $email,
        ]);

        if (($demo['status'] ?? null) !== 'created' || empty($demo['url'])) {
            return $clean;
        }

        $intro = $clean !== ''
            ? $clean."\n\n"
            : '';

        return $intro
            .'Hazır ✅ Size özel WAI canlı test sohbetini oluşturdum. Aşağıdaki bağlantıya dokunup müşteri gibi mesaj yazarak hemen deneyebilirsiniz:'
            ."\n".$demo['url']
            ."\n\nTestten sonra isterseniz WhatsApp'ınıza bağlayıp 1 gün ücretsiz gerçek kullanımda deneyebilirsiniz.";
    }
}
