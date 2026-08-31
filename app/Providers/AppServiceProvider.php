<?php

namespace App\Providers;

use App\Services\OpenAIService;
use App\Services\RealEstateOpenAIService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
        |--------------------------------------------------------------------------
        | OPENAI SERVICE DECORATOR
        |--------------------------------------------------------------------------
        |
        | RealEstateOpenAIService yalnızca ana hesap (user_id=1) için özel
        | gayrimenkul davranışı uygular. Diğer kullanıcılar parent OpenAIService
        | akışına devam eder; böylece SaaS müşterileri etkilenmez.
        */
        $this->app->bind(
            OpenAIService::class,
            RealEstateOpenAIService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
