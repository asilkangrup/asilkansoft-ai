<?php

namespace App\Jobs;

use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Services\CrmCustomerExtractorService;
use App\Services\FinanceLeadExtractorService;
use App\Services\FinanceLeadService;
use App\Services\Insurance\InsuranceWhatsAppInboundService;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public array $payload
    ) {
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
        InsuranceWhatsAppInboundService $insuranceInbound,
    ): void {
        $payload = $this->payload;

        $event = strtolower(trim((string) ($payload['event'] ?? '')));

        if ($event !== '') {
            $payload['event'] = str_replace(
                ['_', '-'],
                '.',
                $event
            );
        }

        $instance = trim((string) ($payload['instance'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | WAI MANUEL MESAJ = İNSAN DEVRALMA
        |--------------------------------------------------------------------------
        |
        | WAI'nin API üzerinden kendi gönderdiği outbound mesajlar sendText()
        | içinde kısa süreli cache anahtarıyla işaretlenir. fromMe=true mesajı
        | geldiğinde bu işaret varsa AI'nin kendi mesajıdır ve devralma yapılmaz.
        | İşaret yoksa mesaj WhatsApp uygulamasından personel tarafından manuel
        | gönderilmiş kabul edilir ve yalnızca o müşteri için AI susturulur.
        |
        */
        if (
            $instance !== ''
            && (bool) data_get($payload, 'data.key.fromMe', false)
        ) {
            $waiBot = AiBot::query()
                ->whereKey(39)
                ->where('whatsapp_instance', $instance)
                ->first();

            if ($waiBot) {
                $remoteJid = trim((string) data_get($payload, 'data.key.remoteJid', ''));
                $number = preg_replace('/\D+/', '', explode('@', $remoteJid)[0] ?? '') ?? '';
                $messagePayload = data_get($payload, 'data.message', []);
                $text = trim((string) (
                    data_get($messagePayload, 'conversation')
                    ?? data_get($messagePayload, 'extendedTextMessage.text')
                    ?? ''
                ));

                if ($number !== '' && $text !== '') {
                    $outboundKey = 'wai_api_outbound:'.sha1(
                        $instance.'|'.$number.'|'.$text
                    );

                    $isAiOutbound = (bool) Cache::pull($outboundKey, false);

                    if (! $isAiOutbound) {
                        ConversationControl::query()
                            ->where('ai_bot_id', 39)
                            ->where('whatsapp_number', $number)
                            ->update([
                                'human_takeover' => true,
                                'updated_at' => now(),
                            ]);

                        Log::info('WAI conversation paused after manual WhatsApp reply', [
                            'ai_bot_id' => 39,
                            'phone_number' => $number,
                        ]);
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SİGORTA OPERASYON BOTU
        |--------------------------------------------------------------------------
        |
        | business_sector=insurance olan botlarda ruhsat görseli/belgesi bu
        | noktada ayrıştırılır. Normal ürün/sipariş akışına düşmeden sigorta
        | operasyon kaydı oluşturulur. Metin mesajları ise mevcut profesyonel
        | WAI konuşma motorunda devam eder.
        |
        */
        if ($instance !== '' && $insuranceInbound->processPayload($payload)) {
            return;
        }

        if ($instance !== '') {
            $realEstateBot = AiBot::query()
                ->whereKey(RealEstateIsolationService::BOT_ID)
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('business_sector', 'real_estate')
                ->where('whatsapp_instance', $instance)
                ->first();

            if ($realEstateIsolation->supportsBotIdentity($realEstateBot)) {
                if (! (bool) ($payload['_real_estate_authorized'] ?? false)) {
                    Log::warning('UNAUTHORIZED REAL ESTATE JOB PAYLOAD DROPPED', [
                        'instance' => $instance,
                        'event' => $payload['event'] ?? null,
                    ]);

                    return;
                }

                unset($payload['_real_estate_authorized']);
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

        $previousOpenAiKey = config('openai.api_key');
        $dedicatedOpenAiKey = '';

        if ($instance !== '') {
            $apiBot = AiBot::query()
                ->where('whatsapp_instance', $instance)
                ->first();

            $dedicatedOpenAiKey = trim((string) ($apiBot?->openai_api_key ?? ''));
        }

        if ($dedicatedOpenAiKey !== '') {
            config(['openai.api_key' => $dedicatedOpenAiKey]);
            OpenAI::clearResolvedInstances();
        }

        try {
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
        } finally {
            if ($dedicatedOpenAiKey !== '') {
                config(['openai.api_key' => $previousOpenAiKey]);
                OpenAI::clearResolvedInstances();
            }
        }
    }
}
