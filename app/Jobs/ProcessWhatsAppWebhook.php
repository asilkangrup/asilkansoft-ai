<?php

namespace App\Jobs;

use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\AiBot;
use App\Services\CrmCustomerExtractorService;
use App\Services\FinanceLeadExtractorService;
use App\Services\FinanceLeadService;
use App\Services\LeadScoringService;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\OrderService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateWhatsAppInboundService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public array $payload
    ) {
        /*
        |--------------------------------------------------------------------------
        | PRODUCTION'DA AYRI QUEUE WORKER YOK
        |--------------------------------------------------------------------------
        |
        | Coolify bu uygulamayı yalnızca `php artisan serve` ile çalıştırıyor.
        | Database queue'ya atılan WhatsApp işleri worker olmadığı için bekler.
        | Bu job bu nedenle sync bağlantısında çalışır.
        |
        */
        $this->onConnection('sync');
    }

    public function handle(
        WhatsAppWebhookController $controller,
        MemoryService $memoryService,
        OpenAIService $openAIService,
        WhatsAppService $whatsAppService,
        OrderService $orderService,
        FinanceLeadService $financeLeadService,
        FinanceLeadExtractorService $financeLeadExtractorService,
        LeadScoringService $leadScoringService,
        CrmCustomerExtractorService $crmCustomerExtractorService,
        RealEstateIsolationService $realEstateIsolation,
        RealEstateWhatsAppInboundService $realEstateInbound,
    ): void {
        $payload = $this->payload;

        /*
        |--------------------------------------------------------------------------
        | EVOLUTION API EVENT ADINI NORMALIZE ET
        |--------------------------------------------------------------------------
        */
        $event = strtolower(trim((string) ($payload['event'] ?? '')));

        if ($event !== '') {
            $payload['event'] = str_replace(
                ['_', '-'],
                '.',
                $event
            );
        }

        /*
        |--------------------------------------------------------------------------
        | İZOLE EMLAK AI: SHARED WAI PIPELINE'A ASLA GİRME
        |--------------------------------------------------------------------------
        |
        | user_id=40 / bot_id=35 için e-ticaret siparişleri, finans akışı,
        | generic CRM ve otomatik takip mantığı kesinlikle çalıştırılmaz.
        | Controller dış sınırda imza/instance doğrulaması yapar; job da ikinci
        | bir tenant sınırı olarak bot kimliğini tekrar doğrular.
        |
        */
        $instance = trim((string) ($payload['instance'] ?? ''));

        if ($instance !== '') {
            $realEstateBot = AiBot::query()
                ->whereKey(RealEstateIsolationService::BOT_ID)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('business_sector', 'real_estate')
                ->where('whatsapp_instance', $instance)
                ->first();

            if ($realEstateIsolation->supportsBotIdentity($realEstateBot)) {
                $realEstateInbound->process($payload);

                return;
            }
        }

        $payload['_wai_queued'] = true;

        $request = Request::create(
            '/api/whatsapp/webhook',
            'POST',
            $payload
        );

        $controller->handle(
            request: $request,
            memoryService: $memoryService,
            openAIService: $openAIService,
            whatsAppService: $whatsAppService,
            orderService: $orderService,
            financeLeadService: $financeLeadService,
            financeLeadExtractorService: $financeLeadExtractorService,
            leadScoringService: $leadScoringService,
            crmCustomerExtractorService: $crmCustomerExtractorService,
        );
    }
}
