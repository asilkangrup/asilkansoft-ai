<?php

namespace App\Jobs;

use App\Http\Controllers\WhatsAppWebhookController;
use App\Services\CrmCustomerExtractorService;
use App\Services\FinanceLeadExtractorService;
use App\Services\FinanceLeadService;
use App\Services\LeadScoringService;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\OrderService;
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
        | Bu nedenle database queue'ya atılan WhatsApp webhook işleri worker
        | olmadığı için işlenmeden bekliyordu. Bu job yalnızca WhatsApp gelen
        | mesaj akışı için kullanıldığı için bağlantıyı zorunlu olarak `sync`
        | yapıyoruz. Böylece mesaj aynı HTTP isteğinde güvenilir şekilde işlenir
        | ve yapay zeka cevabı WhatsApp'a gönderilir.
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
    ): void {
        $payload = $this->payload;
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
