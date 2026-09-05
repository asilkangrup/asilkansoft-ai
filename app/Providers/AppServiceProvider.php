<?php

namespace App\Providers;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Observers\ChatMessageObserver;
use App\Observers\ConversationControlObserver;
use App\Services\CrmConversationSummaryService;
use App\Services\CrmManagerSummaryService;
use App\Services\FinanceLeadExtractorService;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\RealEstateAwareCrmConversationSummaryService;
use App\Services\RealEstateAwareCrmManagerSummaryService;
use App\Services\RealEstateAwareFinanceLeadExtractorService;
use App\Services\RealEstateGuardedMediaAnalysisService;
use App\Services\RealEstateLiveReadinessService;
use App\Services\RealEstateMediaAnalysisService;
use App\Services\RealEstateReadinessService;
use App\Services\WaiPricingOpenAIService;
use App\Services\WaiResetAwareMemoryService;
use App\Services\WhatsAppService;
use App\Services\WaiSalesAwareWhatsAppService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OpenAIService::class,
            WaiPricingOpenAIService::class
        );

        $this->app->bind(
            MemoryService::class,
            WaiResetAwareMemoryService::class
        );

        $this->app->bind(
            WhatsAppService::class,
            WaiSalesAwareWhatsAppService::class
        );

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

        $this->app->bind(
            RealEstateMediaAnalysisService::class,
            RealEstateGuardedMediaAnalysisService::class
        );

        $this->app->bind(
            RealEstateReadinessService::class,
            RealEstateLiveReadinessService::class
        );
    }

    public function boot(): void
    {
        ChatMessage::observe(ChatMessageObserver::class);
        ConversationControl::observe(ConversationControlObserver::class);
    }
}
