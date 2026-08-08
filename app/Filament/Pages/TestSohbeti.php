<?php

namespace App\Filament\Pages;

use App\Models\AiBot;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TestSohbeti extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Test Sohbeti';

    protected static ?string $title = 'Yapay Zekâyı Test Et';

    protected static ?string $slug = 'test-sohbeti';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.test-sohbeti';

    public string $mesaj = '';

    public array $mesajlar = [];

    public string $sessionId = '';

    public ?int $aiBotId = null;

    public function mount(
        MemoryService $memoryService
    ): void {
        $user = Filament::auth()->user();

        if (! $user) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | KULLANICININ YAPAY ZEKÂSINI BUL
        |--------------------------------------------------------------------------
        |
        | Şimdilik kullanıcının en son oluşturduğu yapay zekâyı test ediyoruz.
        | Böylece başka müşterinin botuna erişim mümkün olmaz.
        |
        */

        $aiBot = AiBot::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı')
                ->body(
                    'Test sohbetini kullanmadan önce bir yapay zekâ oluşturmalısınız.'
                )
                ->warning()
                ->send();

            return;
        }

        $this->aiBotId = $aiBot->id;

        /*
        |--------------------------------------------------------------------------
        | TEST OTURUMU
        |--------------------------------------------------------------------------
        */

        $this->sessionId =
            'test:'
            .$aiBot->id
            .':'
            .$memoryService->yeniOturumId();

        $this->mesajlar = [
            [
                'rol' => 'assistant',
                'metin' =>
                    'Merhaba 👋 Ben '
                    .$aiBot->name
                    .'. Size nasıl yardımcı olabilirim?',
            ],
        ];
    }

    public function mesajGonder(
        OpenAIService $openAIService,
        MemoryService $memoryService
    ): void {
        $kullaniciMesaji = trim(
            $this->mesaj
        );

        if ($kullaniciMesaji === '') {
            Notification::make()
                ->title('Lütfen bir mesaj yazın.')
                ->warning()
                ->send();

            return;
        }

        $user = Filament::auth()->user();

        if (! $user) {
            Notification::make()
                ->title('Oturum bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | DOĞRU YAPAY ZEKÂYI BUL
        |--------------------------------------------------------------------------
        |
        | ID tek başına yeterli değil.
        | user_id kontrolü sayesinde müşteri sadece kendi botunu test edebilir.
        |
        */

        $aiBot = AiBot::query()
            ->where('id', $this->aiBotId)
            ->where('user_id', $user->id)
            ->first();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı.')
                ->body(
                    'Lütfen Yapay Zekâlar bölümünden botunuzu kontrol edin.'
                )
                ->danger()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİ MESAJINI HAFIZAYA KAYDET
        |--------------------------------------------------------------------------
        |
        | WhatsApp ile aynı şekilde ai_bot_id kullanıyoruz.
        |
        */

        $memoryService->mesajKaydet(
            userId: $aiBot->user_id,
            aiBotId: $aiBot->id,
            sessionId: $this->sessionId,
            role: 'user',
            message: $kullaniciMesaji,
        );

        $this->mesajlar[] = [
            'rol' => 'user',
            'metin' => $kullaniciMesaji,
        ];

        $this->mesaj = '';

        /*
        |--------------------------------------------------------------------------
        | KONUŞMA GEÇMİŞİNİ HAZIRLA
        |--------------------------------------------------------------------------
        |
        | WhatsApp tarafındaki gibi son 20 mesajı kullanıyoruz.
        |
        */

        $gecmis =
            $memoryService->openAIMesajlariHazirla(
                userId: $aiBot->user_id,
                sessionId: $this->sessionId,
                limit: 20,
            );

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP İLE AYNI YAPAY ZEKÂ MOTORU
        |--------------------------------------------------------------------------
        |
        | EN ÖNEMLİ KISIM BURASI.
        |
        | Artık aiBot parametresi null değil.
        |
        | Böylece OpenAIService:
        |
        | - Firma adını
        | - Firma açıklamasını
        | - Yapay zekâ rolünü
        | - Çalışma saatlerini
        | - Kargo bilgilerini
        | - Ödeme bilgilerini
        | - İade politikasını
        | - Firma kurallarını
        | - Özel yapay zekâ talimatlarını
        | - Ürünleri
        | - Ürün fiyatlarını
        | - Stok durumlarını
        |
        | WhatsApp'ta olduğu gibi kullanır.
        |
        */

        $yapayZekaCevabi =
            $openAIService->cevapVer(
                mesajlar: $gecmis,
                aiBot: $aiBot,
            );

        /*
        |--------------------------------------------------------------------------
        | YAPAY ZEKÂ CEVABINI HAFIZAYA KAYDET
        |--------------------------------------------------------------------------
        */

        $memoryService->mesajKaydet(
            userId: $aiBot->user_id,
            aiBotId: $aiBot->id,
            sessionId: $this->sessionId,
            role: 'assistant',
            message: $yapayZekaCevabi,
        );

        $this->mesajlar[] = [
            'rol' => 'assistant',
            'metin' => $yapayZekaCevabi,
        ];
    }

    public function sohbetiTemizle(
        MemoryService $memoryService
    ): void {
        $user = Filament::auth()->user();

        if (
            $user
            && $this->sessionId !== ''
        ) {
            $memoryService->sohbetiTemizle(
                userId: $user->id,
                sessionId: $this->sessionId,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | YENİ TEST OTURUMU
        |--------------------------------------------------------------------------
        */

        $this->sessionId =
            'test:'
            .$this->aiBotId
            .':'
            .$memoryService->yeniOturumId();

        $this->mesaj = '';

        $aiBot = null;

        if ($user && $this->aiBotId) {
            $aiBot = AiBot::query()
                ->where('id', $this->aiBotId)
                ->where('user_id', $user->id)
                ->first();
        }

        $botAdi =
            $aiBot?->name
            ?: 'yapay zekâ asistanınız';

        $this->mesajlar = [
            [
                'rol' => 'assistant',
                'metin' =>
                    'Sohbet temizlendi. Ben '
                    .$botAdi
                    .'. Size nasıl yardımcı olabilirim?',
            ],
        ];
    }
}