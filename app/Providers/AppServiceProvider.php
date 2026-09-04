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
use App\Services\RealEstateGuardedMediaAnalysisService;
use App\Services\RealEstateLiveReadinessService;
use App\Services\RealEstateMediaAnalysisService;
use App\Services\RealEstateReadinessService;
use App\Services\TenantAwareOpenAIService;
use App\Services\WhatsAppService;
use App\Services\WaiSalesAwareWhatsAppService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OpenAIService::class,
            TenantAwareOpenAIService::class
        );

        $this->app->bind(
            WhatsAppService::class,
            WaiSalesAwareWhatsAppService::class
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

        // The parser includes media_context metadata on every inbound turn.
        // Ignore non-image/document contexts before the strict media firewall so
        // ordinary text/location/audio/video traffic cannot inflate rejection
        // telemetry. Actual media still uses the same fail-closed analyzer.
        $this->app->bind(
            RealEstateMediaAnalysisService::class,
            RealEstateGuardedMediaAnalysisService::class
        );

        // Health/readiness must use Evolution's live state for the isolated
        // instance instead of trusting a potentially stale ai_bots status flag.
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
