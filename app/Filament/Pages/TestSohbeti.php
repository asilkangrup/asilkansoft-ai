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

    protected static ?string $title = 'Yapay ZekÃ¢yÄ± Test Et';

    protected static ?string $slug = 'test-sohbeti';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.test-sohbeti';

    public static function canAccess(): bool
    {
        $role =
            app(
                OrganizationAccessService::class
            )->currentRole();

        return in_array(
            $role,
            [
                'admin',
                'owner',
                'manager',
                'sales',
                'support',
            ],
            true
        );
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

        if (! $user) {
            return 0;
        }

        if ($user->is_admin) {
            return $user->id;
        }

        return (int) (
            $this->currentOrganization()?->owner_user_id
            ?? $user->id
        );
    }

    protected function canManageBotSettings(): bool
    {
        return in_array(
            $this->currentRole(),
            [
                'admin',
                'owner',
                'manager',
            ],
            true
        );
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
    | CANLI DÃœZENLENEBÄ°LÄ°R BOT AYARLARI
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
    | SAYFA AÃ‡ILIÅI
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
                ->title('Yapay zekÃ¢ bulunamadÄ±')
                ->body(
                    'Test sohbetini kullanmadan Ã¶nce bir yapay zekÃ¢ oluÅŸturmalÄ±sÄ±nÄ±z.'
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
    | BOT AYARLARINI FORMA YÃœKLE
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
                ?: 'Merhaba ğŸ‘‹ Daha Ã¶nce gÃ¶rÃ¼ÅŸtÃ¼ÄŸÃ¼mÃ¼z Ã¼rÃ¼nle hÃ¢lÃ¢ ilgileniyor musunuz? Size yardÄ±mcÄ± olabilirim.'
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
                ?: 'Merhaba ğŸ‘‹ Daha Ã¶nce gÃ¶rÃ¼ÅŸtÃ¼ÄŸÃ¼mÃ¼z Ã¼rÃ¼nle ilgili yardÄ±mcÄ± olabileceÄŸimiz bir konu var mÄ±? Dilerseniz sipariÅŸinizi birlikte oluÅŸturabiliriz.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | YENÄ° TEST OTURUMU BAÅLAT
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
                    'Merhaba ğŸ‘‹ Ben '
                    .$aiBot->name
                    .'. Size nasÄ±l yardÄ±mcÄ± olabilirim?',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AKTÄ°F BOTU GÃœVENLÄ° ÅEKÄ°LDE BUL
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
    | FORM VERÄ°LERÄ°NÄ° BOTA KAYDET
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
    | ZORUNLU ALAN KONTROLÃœ
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
    | AYARLARI KAYDET VE TESTÄ° YENÄ°LE
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
                ->title('Yapay zekÃ¢ bulunamadÄ±.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->zorunluAlanlarTamamMi()) {
            Notification::make()
                ->title('Eksik bilgi var')
                ->body(
                    'Yapay zekÃ¢ adÄ±, firma adÄ± ve firma hakkÄ±nda alanlarÄ±nÄ± doldurun.'
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
        | ESKÄ° TEST KONUÅMASINI TEMÄ°ZLE
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
        | VERÄ°TABANINDAN GÃœNCEL BÄ°LGÄ°LERÄ° AL
        |--------------------------------------------------------------------------
        */

        $aiBot->refresh();

        $this->ayarlariFormaYukle($aiBot);

        /*
        |--------------------------------------------------------------------------
        | YENÄ° AYARLARLA YENÄ° TEST OTURUMU
        |--------------------------------------------------------------------------
        */

        $this->yeniTestOturumuBaslat(
            $memoryService,
            $aiBot
        );

        Notification::make()
            ->title('Ayarlar gÃ¼ncellendi')
            ->body(
                'Test sohbeti yeni yapay zekÃ¢ ayarlarÄ±yla yeniden baÅŸlatÄ±ldÄ±.'
            )
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | MESAJ GÃ–NDER
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
                ->title('LÃ¼tfen bir mesaj yazÄ±n.')
                ->warning()
                ->send();

            return;
        }

        $aiBot = $this->aktifBotuGetir();

        if (! $aiBot) {
            Notification::make()
                ->title('Yapay zekÃ¢ bulunamadÄ±.')
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
        | KONUÅMA GEÃ‡MÄ°ÅÄ°NÄ° HAZIRLA
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
        | WHATSAPP Ä°LE AYNI YAPAY ZEKÃ‚ MOTORUNU Ã‡ALIÅTIR
        |--------------------------------------------------------------------------
        */

        $yapayZekaCevabi =
            $openAIService->cevapVer(
                mesajlar: $gecmis,
                aiBot: $aiBot,
            );

        /*
        |--------------------------------------------------------------------------
        | YAPAY ZEKÃ‚ CEVABINI HAFIZAYA KAYDET
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
    | SOHBETÄ° TEMÄ°ZLE
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
    | TESTÄ° ONAYLA VE WHATSAPP'A GEÃ‡
    |--------------------------------------------------------------------------
    |
    | KullanÄ±cÄ± saÄŸdaki ayarlarÄ± deÄŸiÅŸtirmiÅŸ fakat "AyarlarÄ± Kaydet"
    | butonuna basmamÄ±ÅŸ olsa bile burada son bilgiler otomatik kaydedilir.
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
                ->title('Yapay zekÃ¢ bulunamadÄ±.')
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
                    'WhatsApp baÄŸlantÄ±sÄ±na geÃ§meden Ã¶nce Yapay ZekÃ¢ AdÄ±, Firma AdÄ± ve Firma HakkÄ±nda alanlarÄ±nÄ± doldurun.'
                )
                ->warning()
                ->send();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SON DEÄÄ°ÅÄ°KLÄ°KLERÄ° OTOMATÄ°K KAYDET
        |--------------------------------------------------------------------------
        */

        $this->botAyarlariniKaydet($aiBot);

        $aiBot->refresh();

        /*
        |--------------------------------------------------------------------------
        | TEST OTURUMUNU TEMÄ°ZLE
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
        | KULLANICIYA BÄ°LGÄ° VER
        |--------------------------------------------------------------------------
        */

        Notification::make()
            ->title('Test onaylandÄ±')
            ->body(
                'Son ayarlarÄ±nÄ±z kaydedildi. Åimdi WhatsApp baÄŸlantÄ±nÄ±zÄ± tamamlayabilirsiniz.'
            )
            ->success()
            ->send();

        /*
        |--------------------------------------------------------------------------
        | WHATSAPP QR EKRANINA GÄ°T
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