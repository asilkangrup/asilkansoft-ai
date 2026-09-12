<?php

namespace App\Providers;

use App\Models\AiBot;
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
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $this->app['router']->pushMiddlewareToGroup(
            'api',
            TextileHumanTakeoverMiddleware::class
        );
    }
}

class TextileHumanTakeoverMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (
            $request->is('api/whatsapp/webhook')
            && (bool) data_get($request->all(), 'data.key.fromMe', false)
        ) {
            $instance = trim((string) data_get($request->all(), 'instance', ''));
            $remoteJid = trim((string) data_get($request->all(), 'data.key.remoteJid', ''));
            $phone = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';

            if ($instance !== '' && $phone !== '' && ! str_ends_with($remoteJid, '@g.us')) {
                $bot = AiBot::query()
                    ->whereKey(51)
                    ->where('whatsapp_instance', $instance)
                    ->first();

                if ($bot) {
                    $message = data_get($request->all(), 'data.message', []);
                    $text = trim((string) (
                        data_get($message, 'conversation')
                        ?? data_get($message, 'extendedTextMessage.text')
                        ?? data_get($message, 'imageMessage.caption')
                        ?? data_get($message, 'documentMessage.caption')
                        ?? ''
                    ));

                    $isApiOutbound = false;
                    if ($text !== '') {
                        $isApiOutbound = (bool) Cache::store('database')->pull(
                            'wai_api_outbound:'.sha1($instance.'|'.$phone.'|'.$text),
                            false,
                        );
                    }

                    if (! $isApiOutbound) {
                        ConversationControl::query()
                            ->where('ai_bot_id', 51)
                            ->where('whatsapp_number', $phone)
                            ->update([
                                'human_takeover' => true,
                                'taken_over_at' => now(),
                                'released_at' => null,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
        }

        return $next($request);
    }
}
