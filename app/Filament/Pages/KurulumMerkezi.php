<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\AiBot;
use App\Models\Product;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class KurulumMerkezi extends Page
{
    protected string $view = 'filament.pages.kurulum-merkezi';

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Kurulum Merkezi';

    protected static ?string $title = 'Kurulum Merkezi';

    protected static ?int $navigationSort = 1;


    /*
    |--------------------------------------------------------------------------
    | ROL BAZLI ERİŞİM
    |--------------------------------------------------------------------------
    */

    public static function canAccess(): bool
    {
        return app(
            OrganizationAccessService::class
        )->can(
            'setup'
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getViewData(): array
    {
        $user = Filament::auth()->user();

        $bot = AiBot::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | KURULUM DURUMU
        |--------------------------------------------------------------------------
        */

        $botCreated = (bool) $bot;

        $companyCompleted = $bot
            && filled($bot->company_name)
            && filled($bot->company_description);

        $whatsappConnected = $bot
            && $bot->whatsapp_status === 'connected';

        $productsAdded = $bot
            ? Product::query()
                ->where('ai_bot_id', $bot->id)
                ->exists()
            : false;

        $followUpConfigured = $bot
            && $bot->follow_up_enabled
            && filled($bot->first_follow_up_minutes)
            && filled($bot->first_follow_up_message);

        /*
        |--------------------------------------------------------------------------
        | KURULUM ADIMLARI
        |--------------------------------------------------------------------------
        */

        $steps = [
            [
                'title' => 'Yapay ZekÃ¢nÄ± OluÅŸtur',
                'description' =>
                    'Ä°lk yapay zekÃ¢ botunu oluÅŸtur ve temel rolÃ¼nÃ¼ belirle.',

                'completed' =>
                    $botCreated,

                'url' =>
                    $bot
                        ? AiBotResource::getUrl(
                            'edit',
                            [
                                'record' => $bot,
                            ]
                        )
                        : AiBotResource::getUrl(
                            'create'
                        ),

                'button' =>
                    $botCreated
                        ? 'Yapay ZekÃ¢yÄ± DÃ¼zenle'
                        : 'Yapay ZekÃ¢ OluÅŸtur',

                'icon' =>
                    'ğŸ¤–',
            ],

            [
                'title' =>
                    'Firma Bilgilerini Tamamla',

                'description' =>
                    'Firma aÃ§Ä±klamasÄ±, Ã§alÄ±ÅŸma saatleri, Ã¶deme ve Ã¶zel kurallarÄ± gir.',

                'completed' =>
                    $companyCompleted,

                'url' =>
                    $bot
                        ? AiBotResource::getUrl(
                            'edit',
                            [
                                'record' => $bot,
                            ]
                        )
                        : AiBotResource::getUrl(
                            'create'
                        ),

                'button' =>
                    'Firma Bilgilerini DÃ¼zenle',

                'icon' =>
                    'ğŸ¢',
            ],

            [
                'title' =>
                    'WhatsApp BaÄŸlantÄ±sÄ±nÄ± Kur',

                'description' =>
                    'WhatsApp hesabÄ±nÄ± baÄŸlayarak yapay zekÃ¢yÄ± canlÄ± kullanÄ±ma aÃ§.',

                'completed' =>
                    $whatsappConnected,

                'url' =>
                    $bot
                        ? AiBotResource::getUrl(
                            'whatsapp',
                            [
                                'record' => $bot,
                            ]
                        )
                        : AiBotResource::getUrl(
                            'create'
                        ),

                'button' =>
                    $whatsappConnected
                        ? 'WhatsApp Durumunu GÃ¶r'
                        : 'WhatsApp BaÄŸla',

                'icon' =>
                    'ğŸ’¬',
            ],

            [
                'title' =>
                    'ÃœrÃ¼nlerini Ekle',

                'description' =>
                    'Yapay zekÃ¢nÄ±n mÃ¼ÅŸterilere Ã¶nereceÄŸi Ã¼rÃ¼n ve hizmetleri ekle.',

                'completed' =>
                    $productsAdded,

                'url' =>
                    ProductResource::getUrl(
                        'create'
                    ),

                'button' =>
                    'ÃœrÃ¼n Ekle',

                'icon' =>
                    'ğŸ“¦',
            ],

            [
                'title' =>
                    'Otomatik Takibi Ayarla',

                'description' =>
                    'Cevap vermeyen mÃ¼ÅŸterilere otomatik hatÄ±rlatma mesajlarÄ± gÃ¶nder.',

                'completed' =>
                    $followUpConfigured,

                'url' =>
                    $bot
                        ? AiBotResource::getUrl(
                            'edit',
                            [
                                'record' => $bot,
                            ]
                        )
                        : AiBotResource::getUrl(
                            'create'
                        ),

                'button' =>
                    'Takip AyarlarÄ±nÄ± DÃ¼zenle',

                'icon' =>
                    'â±ï¸',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | KURULUM YÃœZDESÄ°
        |--------------------------------------------------------------------------
        */

        $completedCount = collect($steps)
            ->where(
                'completed',
                true
            )
            ->count();

        $progress = count($steps) > 0
            ? (int) round(
                (
                    $completedCount
                    /
                    count($steps)
                )
                * 100
            )
            : 0;

        /*
        |--------------------------------------------------------------------------
        | ÃœCRETSÄ°Z DENEME / ABONELÄ°K
        |--------------------------------------------------------------------------
        */

        $subscriptionStatus =
            $bot?->subscription_status
            ?: 'trial';

        $trialMessageLimit =
            (int) (
                $bot?->trial_message_limit
                ?? 30
            );

        $trialMessagesUsed =
            (int) (
                $bot?->trial_messages_used
                ?? 0
            );

        $trialMessagesRemaining =
            max(
                0,
                $trialMessageLimit
                -
                $trialMessagesUsed
            );

        $trialProgress =
            $trialMessageLimit > 0
                ? (int) min(
                    100,
                    round(
                        (
                            $trialMessagesUsed
                            /
                            $trialMessageLimit
                        )
                        * 100
                    )
                )
                : 0;

        $trialCompleted =
            $bot
            && (
                $subscriptionStatus === 'expired'
                ||
                (
                    $subscriptionStatus === 'trial'
                    &&
                    $trialMessagesUsed
                    >=
                    $trialMessageLimit
                )
            );

        $subscriptionActive =
            $bot
            && $subscriptionStatus === 'active'
            && $bot->whatsappAiKullanilabilirMi();

        /*
        |--------------------------------------------------------------------------
        | PAKET DURUM METÄ°NLERÄ°
        |--------------------------------------------------------------------------
        */

        if ($subscriptionActive) {
            $planTitle =
                'Paketiniz Aktif';

            $planDescription =
                'Yapay zekÃ¢nÄ±z WhatsApp Ã¼zerinden aktif olarak cevap vermeye devam ediyor.';
        } elseif ($trialCompleted) {
            $planTitle =
                'Ãœcretsiz Denemeniz Sona Erdi';

            $planDescription =
                '30 Ã¼cretsiz WhatsApp yapay zekÃ¢ cevabÄ±nÄ±z tamamlandÄ±. Devam etmek iÃ§in paketinizi aktifleÅŸtirin.';
        } else {
            $planTitle =
                'Ãœcretsiz Deneme';

            $planDescription =
                $trialMessagesRemaining
                .' Ã¼cretsiz WhatsApp yapay zekÃ¢ cevabÄ±nÄ±z kaldÄ±.';
        }

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return [
            'user' =>
                $user,

            'bot' =>
                $bot,

            'steps' =>
                $steps,

            'progress' =>
                $progress,

            'completedCount' =>
                $completedCount,

            'totalSteps' =>
                count($steps),

            /*
            |--------------------------------------------------------------------------
            | DENEME / PAKET BÄ°LGÄ°LERÄ°
            |--------------------------------------------------------------------------
            */

            'subscriptionStatus' =>
                $subscriptionStatus,

            'subscriptionActive' =>
                $subscriptionActive,

            'trialMessageLimit' =>
                $trialMessageLimit,

            'trialMessagesUsed' =>
                $trialMessagesUsed,

            'trialMessagesRemaining' =>
                $trialMessagesRemaining,

            'trialProgress' =>
                $trialProgress,

            'trialCompleted' =>
                $trialCompleted,

            'planTitle' =>
                $planTitle,

            'planDescription' =>
                $planDescription,
        ];
    }
}