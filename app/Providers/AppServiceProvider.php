<?php

namespace App\Providers;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Observers\ChatMessageObserver;
use App\Observers\ConversationControlObserver;
use App\Services\CrmConversationSummaryService;
use App\Services\CrmManagerSummaryService;
use App\Services\FinanceLeadExtractorService;
use App\Services\OpenAIService;
use App\Services\RealEstateAwareCrmConversationSummaryService;
use App\Services\RealEstateAwareCrmManagerSummaryService;
use App\Services\RealEstateAwareFinanceLeadExtractorService;
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

        // Shared CRM/finance services below use the global WAI OpenAI facade.
        // Route user 40 / bot 35 through explicit real-estate-aware wrappers so
        // the isolated tenant can never fall through to that global credential.
        $this->app->bind(
            CrmConversationSummaryService::class,
            RealEstateAwareCrmConversationSummaryService::class
        );

        $this->app->bind(
            CrmManagerSummaryService::class,
            RealEstateAwareCrmManagerSummaryService::class
        );

        $this->app->bind(
            FinanceLeadExtractorService::class,
            RealEstateAwareFinanceLeadExtractorService::class
        );
    }

    public function boot(): void
    {
        ChatMessage::observe(ChatMessageObserver::class);
        ConversationControl::observe(ConversationControlObserver::class);
    }
}
