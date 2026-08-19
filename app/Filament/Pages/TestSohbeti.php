<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Models\AiBot;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\OrganizationAccessService;
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

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function currentOrganization()
    {
        return $this
            ->accessService()
            ->currentOrganization();
    }

    protected function currentRole(): ?string
    {
        return $this
            ->accessService()
            ->currentRole();
    }

    protected function ownerUserId(): int
    {
        $user = Filament::auth()->user();

        return $user ? (int) $user->id : 0;
    }

    protected function canManageBotSettings(): bool
    {
        return (bool) Filament::auth()->user();
    }

    /*
    |--------------------------------------------------------------------------
    | SOHBET
    |--------------------------------------------------------------------------
    */

    public string $mesaj = '';

    public array $mesajlar = [];

    public string $sessionId = '';

    public ?int $aiBotId = null;

    /*
    |--------------------------------------------------------------------------
    | CANLI DÜZENLENEBİLİR BOT AYARLARI
    |--------------------------------------------------------------------------
    */

    public string $botName = '';

    public string $companyName = '';

    public string $role = 'sales';

    public string $companyDescription = '';

    public string $workingHours = '';

    public string $cargoInformation = '';

    public string $paymentInformation = '';

    public string $returnPolicy = '';

    public string $companyRules = '';

    public string $systemPrompt = '';

    public bool $followUpEnabled = false;

    public int $firstFollowUpMinutes = 1440;

    public string $firstFollowUpMessage = '';

    public bool $secondFollowUpEnabled = true;

    public int $secondFollowUpMinutes = 4320;

    public string $secondFollowUpMessage = '';

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

        $aiBot = AiBot::query()
            ->where(
                'user_id',
                $this->ownerUserId()
            )
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

        $this->ayarlariFormaYukle($aiBot);

        $this->yeniTestOturumuBaslat(
            $memoryService,
            $aiBot
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BOT AYARLARINI FORMA YÜKLE
    |--------------------------------------------------------------------------
    */

    private function ayarlariFormaYukle(
        AiBot $aiBot
    ): void {
        $this->botName =
            (string) ($aiBot->name ?? '');

        $this->companyName =
            (string) ($aiBot->company_name ?? '');

        $this->role =
            (string) ($aiBot->role ?: 'sales');

        $this->companyDescription =
            (string) ($aiBot->company_description ?? '');

        $this->workingHours =
            (string) ($aiBot->working_hours ?? '');

        $this->cargoInformation =
            (string) ($aiBot->cargo_information ?? '');

        $this->paymentInformation =
            (string) ($aiBot->payment_information ?? '');

        $this->returnPolicy =
            (string) ($aiBot->return_policy ?? '');

        $this->companyRules =
            (string) ($aiBot->company_rules ?? '');

        $this->systemPrompt =
            (string) ($aiBot->system_prompt ?? '');

        $this->followUpEnabled =
            (bool) $aiBot->follow_up_enabled;

        $this->firstFollowUpMinutes =
            (int) (
                $aiBot->first_follow_up_minutes
                ?: 1440
            );

        $this->firstFollowUpMessage =
            (string) (
                $aiBot->first_follow_up_message
                ?: 'Merhaba 👋 Daha önce görüştüğümüz ürünle hâlâ ilgileniyor musunuz? Size yardımcı olabilirim.'
            );

        $this->secondFollowUpEnabled =
            (bool) $aiBot->second_follow_up_enabled;

        $this->secondFollowUpMinutes =
            (int) (
                $aiBot->second_follow_up_minutes
                ?: 4320
            );

        $this->secondFollowUpMessage =
            (string) (
                $aiBot->second_follow_up_message
                ?: 'Merhaba 👋 Daha önce görüştüğümüz ürünle ilgili yardımcı olabileceğimiz bir konu var mı? Dilerseniz siparişinizi birlikte oluşturabiliriz.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | YENİ TEST OTURUMU BAŞLAT
    |--------------------------------------------------------------------------
    */

    private function yeniTestOturumuBaslat(
        MemoryService $memoryService,
        AiBot $aiBot
    ): void {
        $this->sessionId =
            'test:'
            .$aiBot->id
            .':'
            .$memoryService->yeniOturumId();

        $this->mesaj = '';

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
    | AKTİF BOTU GÜVENLİ ŞEKİLDE BUL
    |--------------------------------------------------------------------------
    */

    private function aktifBotuGetir(): ?AiBot
    {
        $user = Filament::auth()->user();

        if (! $user || ! $this->aiBotId) {
            return null;
        }

        return AiBot::query()
            ->where(
                'id',
                $this->aiBotId
            )
            ->where(
                'user_id',
                $this->ownerUserId()
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM VERİLERİNİ BOTA KAYDET
    |--------------------------------------------------------------------------
    */

    private function botAyarlariniKaydet(
        AiBot $aiBot
    ): void {
        $aiBot->update([
            'name' =>
                trim($this->botName),

            'company_name' =>
                trim($this->companyName),

            'role' =>
                $this->role,

            'company_description' =>
                trim($this->companyDescription),

            'working_hours' =>
                trim($this->workingHours),

            'cargo_information' =>
                trim($this->cargoInformation),

            'payment_information' =>
                trim($this->paymentInformation),

            'return_policy' =>
                trim($this->returnPolicy),

            'company_rules' =>
                trim($this->companyRules),

            'system_prompt' =>
                trim($this->systemPrompt),

            'follow_up_enabled' =>
                $this->followUpEnabled,

            'first_follow_up_minutes' =>
                $this->firstFollowUpMinutes,

            'first_follow_up_message' =>
                trim($this->firstFollowUpMessage),

            'second_follow_up_enabled' =>
                $this->secondFollowUpEnabled,

            'second_follow_up_minutes' =>
                $this->secondFollowUpMinutes,

            'second_follow_up_message' =>
                trim($this->secondFollowUpMessage),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ZORUNLU ALAN KONTROLÜ
    |--------------------------------------------------------------------------
    */

    private function zorunluAlanlarTamamMi(): bool
    {
        return
            trim($this->botName) !== ''
            && trim($this->companyName) !== ''
            && trim($this->companyDescription) !== '';
    }

    /*
    |--------------------------------------------------------------------------
    | AYARLARI KAYDET VE TESTİ YENİLE
    |--------------------------------------------------------------------------
    */

    public function ayarlariKaydet(
        MemoryService $memoryService
    ): void {
        if (! $this->canManageBotSettings()) {
            abort(403);
        }

        $aiBot = $this->aktifBotuGetir();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->zorunluAlanlarTamamMi()) {
            Notification::make()
                ->title('Eksik bilgi var')
                ->body(
                    'Yapay zekâ adı, firma adı ve firma hakkında alanlarını doldurun.'
                )
                ->warning()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | AYARLARI KAYDET
        |--------------------------------------------------------------------------
        */

        $this->botAyarlariniKaydet($aiBot);

        /*
        |--------------------------------------------------------------------------
        | ESKİ TEST KONUŞMASINI TEMİZLE
        |--------------------------------------------------------------------------
        */

        if ($this->sessionId !== '') {
            $memoryService->sohbetiTemizle(
                userId: $aiBot->user_id,
                sessionId: $this->sessionId,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERİTABANINDAN GÜNCEL BİLGİLERİ AL
        |--------------------------------------------------------------------------
        */

        $aiBot->refresh();

        $this->ayarlariFormaYukle($aiBot);

        /*
        |--------------------------------------------------------------------------
        | YENİ AYARLARLA YENİ TEST OTURUMU
        |--------------------------------------------------------------------------
        */

        $this->yeniTestOturumuBaslat(
            $memoryService,
            $aiBot
        );

        Notification::make()
            ->title('Ayarlar güncellendi')
            ->body(
                'Test sohbeti yeni yapay zekâ ayarlarıyla yeniden başlatıldı.'
            )
            ->success()
            ->send();
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
        $kullaniciMesaji =
            trim($this->mesaj);

        if ($kullaniciMesaji === '') {
            Notification::make()
                ->title('Lütfen bir mesaj yazın.')
                ->warning()
                ->send();

            return;
        }

        $aiBot = $this->aktifBotuGetir();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | KULLANICI MESAJINI HAFIZAYA KAYDET
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
        | KONUŞMA GEÇMİŞİNİ HAZIRLA
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
        | WHATSAPP İLE AYNI YAPAY ZEKÂ MOTORUNU ÇALIŞTIR
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
        $aiBot = $this->aktifBotuGetir();

        if (! $aiBot) {
            return;
        }

        if ($this->sessionId !== '') {
            $memoryService->sohbetiTemizle(
                userId: $aiBot->user_id,
                sessionId: $this->sessionId,
            );
        }

        $this->yeniTestOturumuBaslat(
            $memoryService,
            $aiBot
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TESTİ ONAYLA VE WHATSAPP'A GEÇ
    |--------------------------------------------------------------------------
    |
    | Kullanıcı sağdaki ayarları değiştirmiş fakat "Ayarları Kaydet"
    | butonuna basmamış olsa bile burada son bilgiler otomatik kaydedilir.
    |
    */

    public function whatsappBaglantisinaGec(
        MemoryService $memoryService
    ): void {
        if (! $this->canManageBotSettings()) {
            abort(403);
        }

        $aiBot = $this->aktifBotuGetir();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekâ bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ZORUNLU ALANLARI KONTROL ET
        |--------------------------------------------------------------------------
        */

        if (! $this->zorunluAlanlarTamamMi()) {
            Notification::make()
                ->title('Eksik bilgi var')
                ->body(
                    'WhatsApp bağlantısına geçmeden önce Yapay Zekâ Adı, Firma Adı ve Firma Hakkında alanlarını doldurun.'
                )
                ->warning()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SON DEĞİŞİKLİKLERİ OTOMATİK KAYDET
        |--------------------------------------------------------------------------
        */

        $this->botAyarlariniKaydet($aiBot);

        $aiBot->refresh();

        /*
        |--------------------------------------------------------------------------
        | TEST OTURUMUNU TEMİZLE
        |--------------------------------------------------------------------------
        */

        if ($this->sessionId !== '') {
            $memoryService->sohbetiTemizle(
                userId: $aiBot->user_id,
                sessionId: $this->sessionId,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | KULLANICIYA BİLGİ VER
        |--------------------------------------------------------------------------
        */

        Notification::make()
            ->title('Test onaylandı')
            ->body(
                'Son ayarlarınız kaydedildi. Şimdi WhatsApp bağlantınızı tamamlayabilirsiniz.'
            )
            ->success()
            ->send();

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP QR EKRANINA GİT
        |--------------------------------------------------------------------------
        */

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