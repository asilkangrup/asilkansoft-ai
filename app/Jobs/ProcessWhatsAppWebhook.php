<?php

namespace App\Jobs;

use App\Http\Controllers\WhatsAppWebhookController;
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

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public array $payload
    ) {
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
    ): void {
        /*
        |--------------------------------------------------------------------------
        | QUEUE İÇİN YENİ REQUEST OLUŞTUR
        |--------------------------------------------------------------------------
        */

        $payload = $this->payload;

        $payload['_wai_queued'] = true;

        $request = Request::create(
            '/api/whatsapp/webhook',
            'POST',
            $payload
        );

        /*
        |--------------------------------------------------------------------------
        | MEVCUT WEBHOOK AKIŞINI ÇALIŞTIR
        |--------------------------------------------------------------------------
        */

        $controller->handle(
            request: $request,
            memoryService: $memoryService,
            openAIService: $openAIService,
            whatsAppService: $whatsAppService,
            orderService: $orderService,
            financeLeadService: $financeLeadService,
            financeLeadExtractorService: $financeLeadExtractorService,
            leadScoringService: $leadScoringService,
        );
    }
}