<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RedirectIncompleteSetup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()

            ->id('admin')

            ->path('admin')

            /*
            |--------------------------------------------------------------------------
            | MARKA
            |--------------------------------------------------------------------------
            */

            ->brandName(
                'ASILKANSOFT AI'
            )

            /*
            |--------------------------------------------------------------------------
            | AUTH
            |--------------------------------------------------------------------------
            */

            ->login(
                Login::class
            )

            ->registration(
                Register::class
            )

            /*
            |--------------------------------------------------------------------------
            | RENK
            |--------------------------------------------------------------------------
            */

            ->colors([
                'primary' =>
                    Color::Amber,
            ])

            /*
            |--------------------------------------------------------------------------
            | TAM GENİŞLİK
            |--------------------------------------------------------------------------
            */

            ->maxContentWidth(
                Width::Full
            )

            /*
            |--------------------------------------------------------------------------
            | SIDEBAR
            |--------------------------------------------------------------------------
            |
            | Açık:
            | İkon + Menü Adı
            |
            | Kapalı:
            | Sadece İkon
            |
            */

            ->sidebarWidth(
                '15rem'
            )

            ->sidebarCollapsibleOnDesktop()

            ->collapsedSidebarWidth(
                '4.5rem'
            )

            /*
            |--------------------------------------------------------------------------
            | GLOBAL DENEME / KREDİ BARI
            |--------------------------------------------------------------------------
            */

            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string =>
                    Blade::render(
                        "@include('filament.partials.trial-bar')"
                    )
            )

            /*
            |--------------------------------------------------------------------------
            | WAI PREMIUM SIDEBAR TOGGLE
            |--------------------------------------------------------------------------
            */

            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => <<<'HTML'
<style>
    /*
    |--------------------------------------------------------------------------
    | SIDEBAR ALT OK BUTONU
    |--------------------------------------------------------------------------
    */

    #wai-sidebar-toggle {
        position: fixed;

        left: 14px;
        bottom: 18px;

        z-index: 99999;

        width: 44px;
        height: 44px;

        display: flex;
        align-items: center;
        justify-content: center;

        padding: 0;

        border: 1px solid rgba(15, 23, 42, .10);
        border-radius: 14px;

        color: #475569;

        background:
            rgba(255, 255, 255, .97);

        box-shadow:
            0 8px 24px rgba(15, 23, 42, .10),
            inset 0 1px 0 rgba(255, 255, 255, .70);

        cursor: pointer;

        transition:
            left .25s ease,
            transform .18s ease,
            color .18s ease,
            box-shadow .18s ease,
            background .18s ease;
    }

    #wai-sidebar-toggle:hover {
        color: #ea580c;

        background: #ffffff;

        transform:
            translateY(-2px);

        box-shadow:
            0 12px 28px rgba(15, 23, 42, .15);
    }

    /*
    |--------------------------------------------------------------------------
    | SIDEBAR AÇIKKEN BUTON SAĞA GİDER
    |--------------------------------------------------------------------------
    */

    #wai-sidebar-toggle.is-open {
        left:
            calc(
                15rem - 58px
            );
    }

    /*
    |--------------------------------------------------------------------------
    | OK
    |--------------------------------------------------------------------------
    */

    #wai-sidebar-toggle svg {
        width: 22px;
        height: 22px;

        transition:
            transform .25s ease;
    }

    /*
    | Menü kapalı:
    | ok sağa bakar.
    */

    #wai-sidebar-toggle:not(.is-open) svg {
        transform:
            rotate(
                0deg
            );
    }

    /*
    | Menü açık:
    | ok sola bakar.
    */

    #wai-sidebar-toggle.is-open svg {
        transform:
            rotate(
                180deg
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MOBİL
    |--------------------------------------------------------------------------
    |
    | Mobilde Filament'in kendi mobil menüsü çalışsın.
    |
    */

    @media (
        max-width: 1023px
    ) {
        #wai-sidebar-toggle {
            display:
                none;
        }
    }
</style>


<button
    id="wai-sidebar-toggle"
    type="button"
    title="Menüyü aç / kapat"
    aria-label="Menüyü aç veya kapat"
>

    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path
            d="M8 5l7 7-7 7"
        />
    </svg>

</button>


<script>
    document.addEventListener(
        'DOMContentLoaded',
        function () {

            /*
            |--------------------------------------------------------------------------
            | SADECE MASAÜSTÜ
            |--------------------------------------------------------------------------
            */

            if (
                window.innerWidth
                <
                1024
            ) {
                return;
            }

            const button =
                document.getElementById(
                    'wai-sidebar-toggle'
                );

            if (! button) {
                return;
            }

            let attempts =
                0;

            const initializeSidebar =
                setInterval(
                    function () {

                        attempts++;

                        /*
                        |--------------------------------------------------------------------------
                        | ALPINE HAZIR DEĞİLSE BEKLE
                        |--------------------------------------------------------------------------
                        */

                        if (
                            ! window.Alpine
                            ||
                            ! Alpine.store(
                                'sidebar'
                            )
                        ) {
                            if (
                                attempts
                                >=
                                40
                            ) {
                                clearInterval(
                                    initializeSidebar
                                );
                            }

                            return;
                        }

                        const sidebar =
                            Alpine.store(
                                'sidebar'
                            );

                        clearInterval(
                            initializeSidebar
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | DURUMU BUTONA YANSIT
                        |--------------------------------------------------------------------------
                        */

                        const updateButton =
                            function () {

                                if (
                                    sidebar.isOpen
                                ) {
                                    button
                                        .classList
                                        .add(
                                            'is-open'
                                        );

                                    button.setAttribute(
                                        'title',
                                        'Menüyü daralt'
                                    );

                                    button.setAttribute(
                                        'aria-label',
                                        'Menüyü daralt'
                                    );

                                    return;
                                }

                                button
                                    .classList
                                    .remove(
                                        'is-open'
                                    );

                                button.setAttribute(
                                    'title',
                                    'Menüyü aç'
                                );

                                button.setAttribute(
                                    'aria-label',
                                    'Menüyü aç'
                                );
                            };

                        /*
                        |--------------------------------------------------------------------------
                        | KAYITLI DURUM
                        |--------------------------------------------------------------------------
                        */

                        const savedState =
                            localStorage.getItem(
                                'wai-sidebar-state'
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | İLK GİRİŞ
                        |--------------------------------------------------------------------------
                        |
                        | İlk girişte ikon görünümünde başlasın.
                        |
                        */

                        if (
                            savedState
                            ===
                            null
                        ) {
                            if (
                                typeof sidebar.close
                                ===
                                'function'
                            ) {
                                sidebar.close();
                            }

                            localStorage.setItem(
                                'wai-sidebar-state',
                                'collapsed'
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | DAHA ÖNCE KAPALI BIRAKILDIYSA
                        |--------------------------------------------------------------------------
                        */

                        else if (
                            savedState
                            ===
                            'collapsed'
                        ) {
                            if (
                                typeof sidebar.close
                                ===
                                'function'
                            ) {
                                sidebar.close();
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | DAHA ÖNCE AÇIK BIRAKILDIYSA
                        |--------------------------------------------------------------------------
                        */

                        else if (
                            savedState
                            ===
                            'open'
                        ) {
                            if (
                                typeof sidebar.open
                                ===
                                'function'
                            ) {
                                sidebar.open();
                            }
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | İLK BUTON DURUMU
                        |--------------------------------------------------------------------------
                        */

                        setTimeout(
                            updateButton,
                            100
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | TIKLAMA
                        |--------------------------------------------------------------------------
                        */

                        button.addEventListener(
                            'click',
                            function () {

                                /*
                                |--------------------------------------------------------------------------
                                | AÇIKSA KAPAT
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    sidebar.isOpen
                                ) {
                                    if (
                                        typeof sidebar.close
                                        ===
                                        'function'
                                    ) {
                                        sidebar.close();
                                    }

                                    localStorage.setItem(
                                        'wai-sidebar-state',
                                        'collapsed'
                                    );

                                    setTimeout(
                                        updateButton,
                                        30
                                    );

                                    return;
                                }

                                /*
                                |--------------------------------------------------------------------------
                                | KAPALIYSA AÇ
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    typeof sidebar.open
                                    ===
                                    'function'
                                ) {
                                    sidebar.open();
                                }

                                localStorage.setItem(
                                    'wai-sidebar-state',
                                    'open'
                                );

                                setTimeout(
                                    updateButton,
                                    30
                                );
                            }
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | DURUM TAKİBİ
                        |--------------------------------------------------------------------------
                        |
                        | Filament başka bir yerden sidebar durumunu değiştirirse
                        | ok yönü de güncellenir.
                        |
                        */

                        setInterval(
                            updateButton,
                            300
                        );
                    },
                    100
                );
        }
    );
</script>
HTML
            )

            /*
            |--------------------------------------------------------------------------
            | RESOURCES
            |--------------------------------------------------------------------------
            */

            ->discoverResources(
                in: app_path(
                    'Filament/Resources'
                ),
                for: 'App\Filament\Resources'
            )

            /*
            |--------------------------------------------------------------------------
            | PAGES
            |--------------------------------------------------------------------------
            */

            ->discoverPages(
                in: app_path(
                    'Filament/Pages'
                ),
                for: 'App\Filament\Pages'
            )

            ->pages([
                Dashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | WIDGETS
            |--------------------------------------------------------------------------
            */

            ->discoverWidgets(
                in: app_path(
                    'Filament/Widgets'
                ),
                for: 'App\Filament\Widgets'
            )

            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | DATABASE NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            ->databaseNotifications()

            /*
            |--------------------------------------------------------------------------
            | MIDDLEWARE
            |--------------------------------------------------------------------------
            */

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | AUTH MIDDLEWARE
            |--------------------------------------------------------------------------
            */

            ->authMiddleware([
                Authenticate::class,
                RedirectIncompleteSetup::class,
            ]);
    }
}