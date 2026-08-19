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
                'title' => 'Yapay Zekânı Oluştur',
                'description' =>
                    'İlk yapay zekâ botunu oluştur ve temel rolünü belirle.',

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
                        ? 'Yapay Zekâyı Düzenle'
                        : 'Yapay Zekâ Oluştur',

                'icon' =>
                    '🤖',
            ],

            [
                'title' =>
                    'Firma Bilgilerini Tamamla',

                'description' =>
                    'Firma açıklaması, çalışma saatleri, ödeme ve özel kuralları gir.',

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
                    'Firma Bilgilerini Düzenle',

                'icon' =>
                    '🏢',
            ],

            [
                'title' =>
                    'WhatsApp Bağlantısını Kur',

                'description' =>
                    'WhatsApp hesabını bağlayarak yapay zekâyı canlı kullanıma aç.',

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
                        ? 'WhatsApp Durumunu Gör'
                        : 'WhatsApp Bağla',

                'icon' =>
                    '💬',
            ],

            [
                'title' =>
                    'Ürünlerini Ekle',

                'description' =>
                    'Yapay zekânın müşterilere önereceği ürün ve hizmetleri ekle.',

                'completed' =>
                    $productsAdded,

                'url' =>
                    ProductResource::getUrl(
                        'create'
                    ),

                'button' =>
                    'Ürün Ekle',

                'icon' =>
                    '📦',
            ],

            [
                'title' =>
                    'Otomatik Takibi Ayarla',

                'description' =>
                    'Cevap vermeyen müşterilere otomatik hatırlatma mesajları gönder.',

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
                    'Takip Ayarlarını Düzenle',

                'icon' =>
                    '⏱️',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | KURULUM YÜZDESİ
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
        | ÜCRETSİZ DENEME / ABONELİK
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
        | PAKET DURUM METİNLERİ
        |--------------------------------------------------------------------------
        */

        if ($subscriptionActive) {
            $planTitle =
                'Paketiniz Aktif';

            $planDescription =
                'Yapay zekânız WhatsApp üzerinden aktif olarak cevap vermeye devam ediyor.';
        } elseif ($trialCompleted) {
            $planTitle =
                'Ücretsiz Denemeniz Sona Erdi';

            $planDescription =
                '30 ücretsiz WhatsApp yapay zekâ cevabınız tamamlandı. Devam etmek için paketinizi aktifleştirin.';
        } else {
            $planTitle =
                'Ücretsiz Deneme';

            $planDescription =
                $trialMessagesRemaining
                .' ücretsiz WhatsApp yapay zekâ cevabınız kaldı.';
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
            | DENEME / PAKET BİLGİLERİ
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