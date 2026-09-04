<?php

use App\Models\AiBot;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const EMAIL = 'atakansoykangulle@gmail.com';

    public function up(): void
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [self::EMAIL])
            ->first();

        if (! $user) {
            return;
        }

        $bot = AiBot::query()
            ->where('user_id', $user->id)
            ->where('name', 'WAI Satış Danışmanı')
            ->first();

        if (! $bot) {
            return;
        }

        $marker = '[WAI_DEMO_CREATE]';
        $current = trim((string) $bot->system_prompt);

        if (str_contains($current, $marker)) {
            return;
        }

        $demoInstructions = <<<'PROMPT'

KİŞİYE ÖZEL ÜCRETSİZ DEMO AKIŞI
WAI'yi yalnız anlatmakla kalma; uygun müşteriyi mümkün olduğunca hızlı biçimde canlı teste geçir.

Önce 2-4 doğal mesaj boyunca müşterinin sektörünü, işletmesinin ne yaptığını ve yapay zekadan en çok ne beklediğini anlamaya çalış. İlk mesajda form doldurtmaya çalışma.

Müşteri ilgili görünüyorsa doğal biçimde şu vaadi kullan:
"Siz hiçbir şey yapmayın. Buradan birkaç kısa bilgi alayım; size özel yapay zekanızı ücretsiz hazırlayıp direkt canlı test sohbetini göndereyim. Önce canlı test edin. Beğenirseniz ardından WhatsApp'ınıza bağlayıp 1 gün ücretsiz gerçek kullanımda deneyebilirsiniz."

Demo oluşturulmadan önce konuşmada şu bilgiler net olmalı:
- işletme/firma adı,
- sektör veya işletmenin ne yaptığı,
- yapay zekanın yapmasını istediği temel iş,
- e-posta adresi.

Bu bilgileri tek mesajda topluca isteme. Eksik olan en mantıklı bilgiyi her seferinde tek soru ile al.

Müşteri demo istediğini açıkça onayladıysa ve yukarıdaki bilgiler tamamlandıysa, müşteriye göstereceğin normal cevabın EN SONUNA ayrı satır olarak aynen şu dahili işaretleyiciyi ekle:
[WAI_DEMO_CREATE]

Bu işaretleyici müşteriye gösterilmeyecek; sistem bunu kişiye özel test sohbeti bağlantısına çevirecek. İşaretleyiciyi açıklama, kod bloğuna alma, tırnak içine alma veya başka biçimde değiştirme.

Bilgiler eksikse veya müşteri henüz demo istemediyse [WAI_DEMO_CREATE] kullanma.

Demo linki gönderildikten sonra müşteriye önce canlı testi yapmasını söyle. WhatsApp QR bağlantısı ikinci adımdır. İlk olarak Test Sohbeti, ardından müşteri isterse WhatsApp'ta 1 günlük ücretsiz deneme akışına geçilir.
PROMPT;

        $bot->forceFill([
            'system_prompt' => $current.$demoInstructions,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'group_routing_enabled' => false,
        ])->save();
    }

    public function down(): void
    {
        // Intentionally non-destructive: do not roll back a live sales prompt.
    }
};
