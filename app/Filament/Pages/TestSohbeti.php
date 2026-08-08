<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
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

    /*
    |--------------------------------------------------------------------------
    | SAYFA AÇILIŞI
    |--------------------------------------------------------------------------
    */

    public function mount(
        MemoryService $memoryService
    ): void {
        $user = Filament::auth()->user();

        if (! $user) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MÜŞTERİNİN KENDİ BOTUNU BUL
        |--------------------------------------------------------------------------
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
        | YENİ TEST OTURUMU
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

    /*
    |--------------------------------------------------------------------------
    | VIEW VERİLERİ
    |--------------------------------------------------------------------------
    |
    | Sağ taraftaki Yapay Zekâ Ayarları paneli için gerekli bilgiler.
    |
    */

    public function getViewData(): array
    {
        $user = Filament::auth()->user();

        $aiBot = null;

        if ($user && $this->aiBotId) {
            $aiBot = AiBot::query()
                ->where('id', $this->aiBotId)
                ->where('user_id', $user->id)
                ->first();
        }

        $roleLabel = match ($aiBot?->role) {
            'sales' => 'Satış Uzmanı',
            'support' => 'Müşteri Temsilcisi',
            'technical' => 'Teknik Destek',
            'assistant' => 'Sekreter / Asistan',
            default => 'Tanımlanmadı',
        };

        $whatsappLabel = match ($aiBot?->whatsapp_status) {
            'connected' => 'Bağlı',
            'connecting' => 'QR Bekleniyor',
            default => 'Henüz Bağlanmadı',
        };

        return [
            'aiBot' => $aiBot,

            'roleLabel' => $roleLabel,

            'whatsappLabel' => $whatsappLabel,

            'editUrl' => $aiBot
                ? AiBotResource::getUrl(
                    'edit',
                    [
                        'record' => $aiBot,
                    ]
                )
                : '#',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJ GÖNDER
    |--------------------------------------------------------------------------
    */

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
        | DOĞRU BOTU GÜVENLİ ŞEKİLDE BUL
        |--------------------------------------------------------------------------
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
        | KONUŞMA GEÇMİŞİ
        |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | SOHBETİ TEMİZLE
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | TESTİ ONAYLA → WHATSAPP'A GEÇ
    |--------------------------------------------------------------------------
    */

    public function whatsappBaglantisinaGec(): void
    {
        $user = Filament::auth()->user();

        if (! $user || ! $this->aiBotId) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        $aiBot = AiBot::query()
            ->where('id', $this->aiBotId)
            ->where('user_id', $user->id)
            ->first();

        if (! $aiBot) {
            Notification::make()
                ->title('Bu yapay zekâya erişilemiyor.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Test onaylandı')
            ->body(
                'Şimdi WhatsApp bağlantınızı tamamlayabilirsiniz.'
            )
            ->success()
            ->send();

        $this->redirect(
            AiBotResource::getUrl(
                'whatsapp',
                [
                    'record' => $aiBot,
                ]
            ),
            navigate: true
        );
    }
}