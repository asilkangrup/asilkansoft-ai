<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/wai-sales.php'));
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | TRUSTED PROXIES
        |--------------------------------------------------------------------------
        |
        | Uygulama Coolify + Cloudflare arkasında çalışıyor.
        |
        | Laravel'in gerçek HTTPS isteğini ve proxy başlıklarını
        | doğru algılaması için proxy'lere güveniyoruz.
        |
        */

        $middleware->trustProxies(
            at: '*'
        );

        /*
        |--------------------------------------------------------------------------
        | PANEL AUTH REDIRECT
        |--------------------------------------------------------------------------
        |
        | Bu uygulamada klasik `login` isimli Laravel route'u yok; panel girişi
        | Filament tarafından sağlanıyor. Oturumu düşmüş bir panel isteğinde
        | Authenticate middleware varsayılan `route('login')` çağrısını yaparsa
        | 500 oluşur. Misafirleri doğrudan Filament admin girişine yönlendir.
        |
        */

        $middleware->redirectGuestsTo(
            fn (Request $request): string => route('filament.admin.auth.login')
        );
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*'),
        );
    })

    ->create();