<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Http\Middleware\RedirectIncompleteSetup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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

            ->brandName(
                'ASILKANSOFT AI'
            )

            ->login(
                Login::class
            )

            ->registration(
                Register::class
            )

            ->colors([
                'primary' => Color::Amber,
            ])

            /*
            |--------------------------------------------------------------------------
            | GLOBAL DENEME / KREDİ BARI
            |--------------------------------------------------------------------------
            |
            | Bot WhatsApp'a bağlandıktan sonra tüm Filament sayfalarının
            | en üstünde görünür.
            |
            */

            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string =>
                    Blade::render(
                        "@include('filament.partials.trial-bar')"
                    )
            )

            ->discoverResources(
                in: app_path(
                    'Filament/Resources'
                ),
                for: 'App\Filament\Resources'
            )

            ->discoverPages(
                in: app_path(
                    'Filament/Pages'
                ),
                for: 'App\Filament\Pages'
            )

            ->pages([
                Dashboard::class,
            ])

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

            ->authMiddleware([
                Authenticate::class,
                RedirectIncompleteSetup::class,
            ]);
    }
}