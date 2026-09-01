<?php

namespace App\Providers;

use App\Models\ChatMessage;
use App\Observers\ChatMessageObserver;
use App\Services\OpenAIService;
use App\Services\RealEstateOpenAIService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OpenAIService::class,
            RealEstateOpenAIService::class
        );
    }

    public function boot(): void
    {
        ChatMessage::observe(ChatMessageObserver::class);
    }
}
