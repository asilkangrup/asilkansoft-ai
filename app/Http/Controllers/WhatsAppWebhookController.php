<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationFollowUp;
use App\Models\ConversationControl;
use App\Models\FinanceLead;
use App\Models\Organization;
use App\Services\CrmCustomerExtractorService;
use App\Services\FinanceLeadExtractorService;
use App\Services\FinanceLeadService;
use App\Services\LeadScoringService;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function handle(
        Request $request,
        MemoryService $memoryService,
        OpenAIService $openAIService,
        WhatsAppService $whatsAppService,
        OrderService $orderService,
        FinanceLeadService $financeLeadService,
        FinanceLeadExtractorService $financeLeadExtractorService,
        LeadScoringService $leadScoringService,
        CrmCustomerExtractorService $crmCustomerExtractorService
    ): JsonResponse {
        try {
            $payload = $request->all();

            /*
            |--------------------------------------------------------------------------
            | SADECE YENÄ° MESAJLARI Ä°ÅLE
            |--------------------------------------------------------------------------
            */

            $event = strtolower((string) data_get($payload, 'event', ''));

            if ($event === 'messages.update') {
                $this->handleMessageStatusUpdate($payload);

                return response()->json([
                    'success' => true,
                    'status_update' => true,
                ]);
            }

            if ($event !== 'messages.upsert') {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | WEBHOOK'U HIZLICA QUEUE'YA DEVRET
            |--------------------------------------------------------------------------
            |
            | Evolution API'nin 60 saniye timeout'a dÃ¼ÅŸmemesi iÃ§in aÄŸÄ±r iÅŸlemler
            | (OpenAI, sipariÅŸ, finans, WhatsApp gÃ¶nderimi) queue worker'da Ã§alÄ±ÅŸÄ±r.
            |
            | Queue worker aynÄ± controller'Ä± _wai_queued=true ile yeniden Ã§aÄŸÄ±rÄ±r.
            | BÃ¶ylece bu blok ikinci kez job oluÅŸturmaz.
            |
            */

            if (
                ! (bool) data_get(
                    $payload,
                    '_wai_queued',
                    false
                )
            ) {
                ProcessWhatsAppWebhook::dispatch(
                    $payload
                );

                return response()->json([
                    'success' => true,
                    'queued' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | BOTUN KENDÄ° MESAJINI TEKRAR CEVAPLAMA
            |--------------------------------------------------------------------------
            */

            $fromMe = (bool) data_get(
                $payload,
                'data.key.fromMe',
                false
            );

            if ($fromMe) {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'from_me',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | AYNI WHATSAPP MESAJINI SADECE BÄ°R KEZ Ä°ÅLE
            |--------------------------------------------------------------------------
            */

            $messageId = trim(
                (string) data_get(
                    $payload,
                    'data.key.id',
                    ''
                )
            );

            if ($messageId !== '') {
                $duplicateCacheKey =
                    'whatsapp_webhook_message:'
                    .sha1(
                        (string) data_get(
                            $payload,
                            'instance',
                            ''
                        )
                        .'|'
                        .$messageId
                    );

                if (
                    ! Cache::add(
                        $duplicateCacheKey,
                        true,
                        now()->addHours(24)
                    )
                ) {
                    Log::info(
                        'Tekrarlanan WhatsApp webhook mesajÄ± engellendi',
                        [
                            'message_id' =>
                                $messageId,

                            'instance' =>
                                data_get(
                                    $payload,
                                    'instance'
                                ),
                        ]
                    );

                    return response()->json([
                        'success' => true,
                        'ignored' => true,
                        'reason' =>
                            'duplicate_message',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | INSTANCE
            |--------------------------------------------------------------------------
            */

            $instanceName = trim(
                (string) data_get(
                    $payload,
                    'instance',
                    ''
                )
            );

            if ($instanceName === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Instance bulunamadÄ±.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DOÄRU YAPAY ZEKÃ‚ BOTUNU BUL
            |--------------------------------------------------------------------------
            */

            $aiBot = AiBot::query()
                ->where(
                    'whatsapp_instance',
                    $instanceName
                )
                ->first();

            if (! $aiBot) {
                Log::warning(
                    'Webhook iÃ§in AiBot bulunamadÄ±',
                    [
                        'instance' => $instanceName,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Bu instance iÃ§in yapay zekÃ¢ bulunamadÄ±.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | MÃœÅTERÄ° NUMARASI
            |--------------------------------------------------------------------------
            */

            $remoteJid = trim(
                (string) data_get(
                    $payload,
                    'data.key.remoteJid',
                    ''
                )
            );

            if (
                $remoteJid === ''
                || str_contains(
                    $remoteJid,
                    '@g.us'
                )
            ) {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'unsupported_chat',
                ]);
            }

            $phoneNumber =
                explode('@', $remoteJid)[0];

            /*
            |--------------------------------------------------------------------------
            | MESAJ / MEDYA
            |--------------------------------------------------------------------------
            */

            $messagePayload = data_get($payload, 'data.message', []);

            $message = data_get(
                $messagePayload,
                'conversation'
            )
            ?? data_get(
                $messagePayload,
                'extendedTextMessage.text'
            );

            $messageType = 'text';
            $mediaUrl = null;
            $mediaMimeType = null;
            $mediaFilename = null;
            $mediaCaption = null;

            if (! is_string($message) || trim($message) === '') {
                $image = data_get($messagePayload, 'imageMessage');

                if (is_array($image)) {
                    $messageType = 'image';
                    $mediaUrl = data_get($image, 'url');
                    $mediaMimeType = data_get($image, 'mimetype');
                    $mediaFilename = data_get($image, 'fileName') ?? 'FotoÄŸraf';
                    $mediaCaption = data_get($image, 'caption');
                    $message = $mediaCaption ?: '[FotoÄŸraf]';
                }

                $video = data_get($messagePayload, 'videoMessage');

                if ($messageType === 'text' && is_array($video)) {
                    $messageType = 'video';
                    $mediaUrl = data_get($video, 'url');
                    $mediaMimeType = data_get($video, 'mimetype');
                    $mediaFilename = data_get($video, 'fileName') ?? 'Video';
                    $mediaCaption = data_get($video, 'caption');
                    $message = $mediaCaption ?: '[Video]';
                }

                $document = data_get($messagePayload, 'documentMessage');

                if ($messageType === 'text' && is_array($document)) {
                    $messageType = 'document';
                    $mediaUrl = data_get($document, 'url');
                    $mediaMimeType = data_get($document, 'mimetype');
                    $mediaFilename = data_get($document, 'fileName') ?? 'Belge';
                    $mediaCaption = data_get($document, 'caption');
                    $message = $mediaCaption ?: '[Belge]';
                }

                $audio = data_get($messagePayload, 'audioMessage');

                if ($messageType === 'text' && is_array($audio)) {
                    $messageType = 'audio';
                    $mediaUrl = data_get($audio, 'url');
                    $mediaMimeType = data_get($audio, 'mimetype');
                    $mediaFilename = 'Sesli mesaj';
                    $message = '[Sesli mesaj]';
                }
            }

            $message = trim((string) $message);

            if ($message === '') {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'empty_message',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ABONELÄ°K / 30 MESAJLIK DENEME KONTROLÃœ
            |--------------------------------------------------------------------------
            */

            if (
                ! $aiBot->whatsappAiKullanilabilirMi()
            ) {
                Log::info(
                    'WhatsApp AI kullanÄ±m limiti nedeniyle cevap verilmedi',
                    [
                        'ai_bot_id' =>
                            $aiBot->id,

                        'subscription_status' =>
                            $aiBot->subscription_status,

                        'trial_messages_used' =>
                            $aiBot->trial_messages_used,

                        'trial_message_limit' =>
                            $aiBot->trial_message_limit,

                        'phone_number' =>
                            $phoneNumber,
                    ]
                );

                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' =>
                        'subscription_inactive',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | OTURUM
            |--------------------------------------------------------------------------
            */

            $sessionId =
                'whatsapp:'
                .$aiBot->id
                .':'
                .$phoneNumber;

            /*
            |--------------------------------------------------------------------------
            | KONUÅMA KONTROL KAYDI
            |--------------------------------------------------------------------------
            |
            | Her WhatsApp konuÅŸmasÄ± iÃ§in tek bir kontrol kaydÄ± oluÅŸturulur.
            | BÃ¶ylece panelden sadece bu mÃ¼ÅŸterinin konuÅŸmasÄ± devralÄ±nabilir.
            |
            */

            $organizationId =
                Organization::query()
                    ->where(
                        'owner_user_id',
                        $aiBot->user_id
                    )
                    ->value(
                        'id'
                    );

            $conversationControl =
                ConversationControl::firstOrCreate(
                    [
                        'ai_bot_id' =>
                            $aiBot->id,

                        'session_id' =>
                            $sessionId,
                    ],
                    [
                        'user_id' =>
                            $aiBot->user_id,

                        'organization_id' =>
                            $organizationId,

                        'whatsapp_number' =>
                            $phoneNumber,

                        'customer_name' =>
                            null,

                        'unread_count' =>
                            0,

                        'human_takeover' =>
                            false,
                    ]
                );

            if (
                $conversationControl->organization_id === null
                && $organizationId !== null
            ) {
                $conversationControl->forceFill([
                    'organization_id' =>
                        $organizationId,
                ])->save();
            }

            /*
            |--------------------------------------------------------------------------
            | MÃœÅTERÄ° MESAJINI HAFIZAYA KAYDET
            |--------------------------------------------------------------------------
            */

            $memoryService->mesajKaydet(
                userId: $aiBot->user_id,
                aiBotId: $aiBot->id,
                sessionId: $sessionId,
                role: 'user',
                message: $message,
            );

            /*
            |--------------------------------------------------------------------------
            | MÃœÅTERÄ° ADI
            |--------------------------------------------------------------------------
            */

            $customerName =
                trim(
                    (string) (
                        data_get(
                            $payload,
                            'data.pushName',
                            ''
                        )
                    )
                );

            if (
                $customerName !== ''
                && trim((string) $conversationControl->customer_name) === ''
            ) {
                $conversationControl->customer_name =
                    $customerName;
            }

            /*
            |--------------------------------------------------------------------------
            | OKUNMAMIÅ MESAJ
            |--------------------------------------------------------------------------
            */

            $conversationControl->unread_count =
                (int) $conversationControl->unread_count + 1;

            $conversationControl->last_contact_at =
                now();

            $conversationControl->updated_at =
                now();

            $conversationControl->save();

            /*
            |--------------------------------------------------------------------------
            | WAI SMART LEAD SCORING
            |--------------------------------------------------------------------------
            |
            | MÃ¼ÅŸteri mesajÄ±ndaki satÄ±n alma sinyallerini analiz eder.
            | Ekstra OpenAI Ã§aÄŸrÄ±sÄ± yapmaz; ana WhatsApp cevap akÄ±ÅŸÄ±nÄ± yavaÅŸlatmaz.
            |
            | AynÄ± WhatsApp mesajÄ± yukarÄ±daki messageId / Cache korumasÄ± sayesinde
            | ikinci kez iÅŸlenmediÄŸi iÃ§in lead puanÄ± da iki kez artmaz.
            |
            */

            try {
                $leadScoringService->puanla(
                    conversation: $conversationControl,
                    message: $message,
                );
            } catch (Throwable $exception) {
                Log::warning(
                    'WAI LEAD SCORING FAILED',
                    [
                        'conversation_id' =>
                            $conversationControl->id,

                        'ai_bot_id' =>
                            $aiBot->id,

                        'session_id' =>
                            $sessionId,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | WAI OTOMATİK CRM MÜŞTERİ BİLGİSİ
            |--------------------------------------------------------------------------
            |
            | Müşterinin kendi mesajında açıkça verdiği ad, e-posta ve firma
            | bilgilerini ekstra OpenAI çağrısı yapmadan CRM'e işler.
            |
            | Hata oluşursa ana WhatsApp cevap akışı kesinlikle durmaz.
            |
            */

            try {
                $crmCustomerExtractorService->process(
                    conversation: $conversationControl,
                    message: $message,
                    pushName: $customerName !== ''
                        ? $customerName
                        : null,
                );
            } catch (Throwable $exception) {
                Log::warning(
                    'WAI CRM CUSTOMER EXTRACTOR FAILED',
                    [
                        'conversation_id' =>
                            $conversationControl->id,

                        'ai_bot_id' =>
                            $aiBot->id,

                        'session_id' =>
                            $sessionId,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | MEDYA MESAJINI PANEL HAFIZASINA KAYDET
            |--------------------------------------------------------------------------
            */

            if ($messageType !== 'text') {
                ChatMessage::create([
                    'user_id' => $aiBot->user_id,
                    'organization_id' => $conversationControl->organization_id,
                    'ai_bot_id' => $aiBot->id,
                    'session_id' => $sessionId,
                    'role' => 'user',
                    'sender_type' => 'customer',
                    'message' => $message,
                    'message_type' => $messageType,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $mediaMimeType,
                    'media_filename' => $mediaFilename,
                    'media_caption' => $mediaCaption,
                    'status' => 'received',
                    'whatsapp_message_id' => $messageId !== '' ? $messageId : null,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Ä°NSAN DEVRALMA KONTROLÃœ
            |--------------------------------------------------------------------------
            |
            | Bu WhatsApp konuÅŸmasÄ± bir personel tarafÄ±ndan devralÄ±ndÄ±ysa
            | mÃ¼ÅŸterinin mesajÄ± hafÄ±zada kalÄ±r fakat yapay zekÃ¢ cevap vermez.
            |
            */

            if (
                $conversationControl
                && $conversationControl->human_takeover
            ) {
                Log::info(
                    'KonuÅŸma insan tarafÄ±ndan devralÄ±ndÄ±ÄŸÄ± iÃ§in AI cevap vermedi',
                    [
                        'ai_bot_id' => $aiBot->id,
                        'session_id' => $sessionId,
                        'phone_number' => $phoneNumber,
                    ]
                );

                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'human_takeover',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | OTOMATÄ°K TAKÄ°P SAYACINI BAÅLAT / SIFIRLA
            |--------------------------------------------------------------------------
            */

            if ($aiBot->follow_up_enabled) {
                ConversationFollowUp::updateOrCreate(
                    [
                        'ai_bot_id' =>
                            $aiBot->id,

                        'session_id' =>
                            $sessionId,
                    ],
                    [
                        'user_id' =>
                            $aiBot->user_id,

                        'whatsapp_number' =>
                            $phoneNumber,

                        'last_customer_message_at' =>
                            now(),

                        'first_follow_up_sent_at' =>
                            null,

                        'second_follow_up_sent_at' =>
                            null,

                        'follow_up_sent_at' =>
                            null,

                        'is_active' =>
                            true,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | FÄ°NANS BAÅVURU TAKÄ°BÄ°
            |--------------------------------------------------------------------------
            |
            | Bu bÃ¶lÃ¼m sadece group_routing_enabled = true olan botlarda Ã§alÄ±ÅŸÄ±r.
            | KonuÅŸmayÄ± analiz eder, finance_leads tablosuna kaydeder ve
            | tÃ¼m zorunlu bilgiler tamamlandÄ±ÄŸÄ±nda doÄŸru WhatsApp grubuna yollar.
            |
            */

            if (
                $financeLeadService->aktifMi($aiBot)
            ) {
                $financeHistory =
                    $memoryService
                        ->openAIMesajlariHazirla(
                            userId:
                                $aiBot->user_id,

                            sessionId:
                                $sessionId,

                            limit:
                                10,
                        );

                $extractedFinanceData =
                    $financeLeadExtractorService
                        ->extract(
                            aiBot:
                                $aiBot,

                            messages:
                                $financeHistory,
                        );

                $this->financeLeadKaydet(
                    aiBot:
                        $aiBot,

                    sessionId:
                        $sessionId,

                    whatsappNumber:
                        $phoneNumber,

                    data:
                        $extractedFinanceData,

                    financeLeadService:
                        $financeLeadService,

                    whatsAppService:
                        $whatsAppService,
                );
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL SÄ°PARÄ°Å SÄ°STEMÄ°
            |--------------------------------------------------------------------------
            |
            | Finans botunda normal Ã¼rÃ¼n / sipariÅŸ sistemi Ã§alÄ±ÅŸtÄ±rÄ±lmaz.
            |
            | BÃ¶ylece mÃ¼ÅŸterinin:
            | "limit istiyorum"
            | "almak istiyorum"
            | vb. ifadeleri yanlÄ±ÅŸlÄ±kla Ã¼rÃ¼n sipariÅŸi oluÅŸturmaz.
            |
            */

            if (
                ! $financeLeadService->aktifMi($aiBot)
            ) {
                /*
                |--------------------------------------------------------------------------
                | MEVCUT TASLAK SÄ°PARÄ°ÅÄ° BUL
                |--------------------------------------------------------------------------
                */

                $order =
                    $orderService->taslakSiparisiGetir(
                        aiBotId:
                            $aiBot->id,

                        sessionId:
                            $sessionId,
                    );

                /*
                |--------------------------------------------------------------------------
                | SATIN ALMA NÄ°YETÄ° VARSA TASLAK OLUÅTUR
                |--------------------------------------------------------------------------
                */

                if (! $order) {
                    $siparisBaslat =
                        $this->satinAlmaNiyetiVarMi(
                            $message
                        );

                    if (
                        ! $siparisBaslat
                        && $this->kisaOlumluCevapMi(
                            $message
                        )
                    ) {
                        $sonMesajlar =
                            $memoryService
                                ->gecmisiGetir(
                                    userId:
                                        $aiBot->user_id,

                                    sessionId:
                                        $sessionId,

                                    limit:
                                        8,
                                );

                        $siparisBaslat =
                            $this->oncekiAsistanSiparisTeklifiYaptiMi(
                                $sonMesajlar
                            );
                    }

                    if ($siparisBaslat) {
                        $order =
                            $orderService
                                ->taslakSiparisOlustur(
                                    aiBotId:
                                        $aiBot->id,

                                    sessionId:
                                        $sessionId,

                                    whatsappNumber:
                                        $phoneNumber,
                                );

                        /*
                         * Taslak yeni oluÅŸtuysa son konuÅŸmadaki Ã¼rÃ¼n ve
                         * miktarÄ± da sipariÅŸe aktar. BÃ¶ylece mÃ¼ÅŸteri
                         * "evet" dediÄŸinde Ã¶nceki "5 litre zeytinyaÄŸÄ±"
                         * mesajÄ± kaybolmaz.
                         */
                        $siparisGecmisi =
                            $memoryService
                                ->openAIMesajlariHazirla(
                                    userId:
                                        $aiBot->user_id,

                                    sessionId:
                                        $sessionId,

                                    limit:
                                        10,
                                );

                        $order =
                            $orderService
                                ->gecmistenSiparisiGuncelle(
                                    order:
                                        $order,

                                    mesajlar:
                                        $siparisGecmisi,

                                    aiBot:
                                        $aiBot,
                                );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | SÄ°PARÄ°Å AKIÅI
                |--------------------------------------------------------------------------
                */

                if ($order) {
                    /*
                    |--------------------------------------------------------------------------
                    | GELEN MESAJDAN SÄ°PARÄ°Å BÄ°LGÄ°LERÄ°NÄ° YAKALA
                    |--------------------------------------------------------------------------
                    */

                    $order =
                        $orderService
                            ->mesajdanSiparisiGuncelle(
                                order:
                                    $order,

                                message:
                                    $message,

                                aiBot:
                                    $aiBot,
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | MÃœÅTERÄ° ONAY VERDÄ°YSE
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $orderService
                            ->onayMesajiMi(
                                $message
                            )
                    ) {
                        if (
                            $orderService
                                ->siparisTamamlanmayaHazirMi(
                                    $order
                                )
                        ) {
                            $order =
                                $orderService
                                    ->siparisiOnayla(
                                        $order
                                    );

                            $answer =
                                "âœ… SipariÅŸiniz baÅŸarÄ±yla alÄ±ndÄ±.\n\n"
                                .$orderService
                                    ->siparisOzeti(
                                        $order
                                    )
                                ."\n\nSipariÅŸiniz firmaya iletildi.";

                            $this->cevabiKaydetVeGonder(
                                memoryService:
                                    $memoryService,

                                whatsAppService:
                                    $whatsAppService,

                                aiBot:
                                    $aiBot,

                                sessionId:
                                    $sessionId,

                                instanceName:
                                    $instanceName,

                                phoneNumber:
                                    $phoneNumber,

                                answer:
                                    $answer,
                            );

                            return response()->json([
                                'success' => true,
                                'message' =>
                                    'SipariÅŸ onaylandÄ±.',
                            ]);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | EKSÄ°K BÄ°LGÄ° VARKEN ONAY VERDÄ°YSE
                        |--------------------------------------------------------------------------
                        */

                        $answer =
                            $orderService
                                ->siradakiEksikSoru(
                                    $order
                                )
                            ?? 'SipariÅŸ bilgileriniz henÃ¼z tamamlanmadÄ±.';

                        $this->cevabiKaydetVeGonder(
                            memoryService:
                                $memoryService,

                            whatsAppService:
                                $whatsAppService,

                            aiBot:
                                $aiBot,

                            sessionId:
                                $sessionId,

                            instanceName:
                                $instanceName,

                            phoneNumber:
                                $phoneNumber,

                            answer:
                                $answer,
                        );

                        return response()->json([
                            'success' => true,
                            'message' =>
                                'Eksik sipariÅŸ bilgisi istendi.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | TÃœM BÄ°LGÄ°LER TAMAMSA Ã–ZET GÃ–NDER
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $orderService
                            ->siparisTamamlanmayaHazirMi(
                                $order
                            )
                    ) {
                        $answer =
                            $orderService
                                ->siparisOzeti(
                                    $order
                                )
                            ."\n\nBilgiler doÄŸruysa sadece *OnaylÄ±yorum* yazabilirsiniz.";

                        $this->cevabiKaydetVeGonder(
                            memoryService:
                                $memoryService,

                            whatsAppService:
                                $whatsAppService,

                            aiBot:
                                $aiBot,

                            sessionId:
                                $sessionId,

                            instanceName:
                                $instanceName,

                            phoneNumber:
                                $phoneNumber,

                            answer:
                                $answer,
                        );

                        return response()->json([
                            'success' => true,
                            'message' =>
                                'SipariÅŸ Ã¶zeti gÃ¶nderildi.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | EKSÄ°K BÄ°LGÄ° VARSA SADECE SIRADAKÄ°NÄ° SOR
                    |--------------------------------------------------------------------------
                    */

                    $nextQuestion =
                        $orderService
                            ->siradakiEksikSoru(
                                $order
                            );

                    if ($nextQuestion) {
                        $this->cevabiKaydetVeGonder(
                            memoryService:
                                $memoryService,

                            whatsAppService:
                                $whatsAppService,

                            aiBot:
                                $aiBot,

                            sessionId:
                                $sessionId,

                            instanceName:
                                $instanceName,

                            phoneNumber:
                                $phoneNumber,

                            answer:
                                $nextQuestion,
                        );

                        return response()->json([
                            'success' => true,
                            'message' =>
                                'SÄ±radaki sipariÅŸ bilgisi istendi.',
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL GPT KONUÅMASI
            |--------------------------------------------------------------------------
            */

            $history =
                $memoryService
                    ->openAIMesajlariHazirla(
                        userId:
                            $aiBot->user_id,

                        sessionId:
                            $sessionId,

                        limit:
                            20,
                    );

            $answer =
                $openAIService->cevapVer(
                    mesajlar:
                        $history,

                    aiBot:
                        $aiBot,
                );

            $this->cevabiKaydetVeGonder(
                memoryService:
                    $memoryService,

                whatsAppService:
                    $whatsAppService,

                aiBot:
                    $aiBot,

                sessionId:
                    $sessionId,

                instanceName:
                    $instanceName,

                phoneNumber:
                    $phoneNumber,

                answer:
                    $answer,
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Yapay zekÃ¢ cevabÄ± gÃ¶nderildi.',
            ]);

        } catch (Throwable $exception) {
            Log::error(
                'WhatsApp AI webhook hatasÄ±',
                [
                    'message' =>
                        $exception->getMessage(),

                    'trace' =>
                        $exception
                            ->getTraceAsString(),
                ]
            );

            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Webhook iÅŸlenirken hata oluÅŸtu.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FÄ°NANS BAÅVURUSUNU KAYDET / GÃœNCELLE
    |--------------------------------------------------------------------------
    */

    private function handleMessageStatusUpdate(array $payload): void
    {
        $updates = data_get($payload, 'data', []);

        if (! is_array($updates)) {
            return;
        }

        $items = array_is_list($updates)
            ? $updates
            : [$updates];

        foreach ($items as $update) {
            $messageId =
                data_get($update, 'key.id')
                ?? data_get($update, 'id')
                ?? data_get($update, 'messageId');

            if (! is_string($messageId) || trim($messageId) === '') {
                continue;
            }

            $rawStatus =
                data_get($update, 'update.status')
                ?? data_get($update, 'status')
                ?? data_get($update, 'message.status');

            if (is_numeric($rawStatus)) {
                $rawStatus = match ((int) $rawStatus) {
                    0 => 'error',
                    1 => 'pending',
                    2 => 'sent',
                    3 => 'delivered',
                    4 => 'read',
                    5 => 'played',
                    default => 'sent',
                };
            }

            $status = strtolower((string) $rawStatus);

            $status = match ($status) {
                'pending', 'server_ack' => 'pending',
                'sent', 'device_ack' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'played' => 'played',
                'error', 'failed' => 'error',
                default => $status !== '' ? $status : 'sent',
            };

            ChatMessage::query()
                ->where('whatsapp_message_id', $messageId)
                ->update([
                    'status' => $status,
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FÄ°NANS BAÅVURUSUNU KAYDET / GÃœNCELLE
    |--------------------------------------------------------------------------
    */

    private function financeLeadKaydet(
        AiBot $aiBot,
        string $sessionId,
        string $whatsappNumber,
        array $data,
        FinanceLeadService $financeLeadService,
        WhatsAppService $whatsAppService
    ): void {
        $type =
            $data['type']
            ?? null;

        /*
        |--------------------------------------------------------------------------
        | BAÅVURU TÃœRÃœ HENÃœZ BELLÄ° DEÄÄ°L
        |--------------------------------------------------------------------------
        */

        if (
            ! is_string($type)
            || trim($type) === ''
        ) {
            return;
        }

        $type =
            trim($type);

        /*
        |--------------------------------------------------------------------------
        | MEVCUT KAYDI BUL / YENÄ° KAYIT HAZIRLA
        |--------------------------------------------------------------------------
        */

        $lead =
            FinanceLead::firstOrNew([
                'ai_bot_id' =>
                    $aiBot->id,

                'session_id' =>
                    $sessionId,

                'type' =>
                    $type,
            ]);

        /*
        |--------------------------------------------------------------------------
        | SABÄ°T BÄ°LGÄ°LER
        |--------------------------------------------------------------------------
        */

        $lead->user_id =
            $aiBot->user_id;

        $lead->whatsapp_number =
            $whatsappNumber;

        /*
        |--------------------------------------------------------------------------
        | AI TARAFINDAN Ã‡IKARILAN BÄ°LGÄ°LER
        |--------------------------------------------------------------------------
        |
        | Null gelen deÄŸerlerle daha Ã¶nce kaydedilmiÅŸ doÄŸru bilgileri silmiyoruz.
        |
        */

        $fields = [
            'name',
            'phone',
            'city',
            'line_owner',
            'mother_maiden_surname',
            'limit_score',
            'tc_identity_number',
            'limit',
        ];

        foreach ($fields as $field) {
            $value =
                $data[$field]
                ?? null;

            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                $lead->{$field} =
                    trim(
                        (string) $value
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DOÄUM TARÄ°HÄ°
        |--------------------------------------------------------------------------
        */

        $birthDate =
            $this->dogumTarihiniNormalizeEt(
                $data['birth_date']
                ?? null
            );

        if ($birthDate !== null) {
            $lead->birth_date =
                $birthDate;
        }

        /*
        |--------------------------------------------------------------------------
        | GRUP JID
        |--------------------------------------------------------------------------
        */

        $groupJid =
            $financeLeadService
                ->groupJid(
                    aiBot:
                        $aiBot,

                    type:
                        $type
                );

        if (
            is_string($groupJid)
            && trim($groupJid) !== ''
        ) {
            $lead->group_jid =
                trim($groupJid);
        }

        /*
        |--------------------------------------------------------------------------
        | BAÅVURU TAMAM MI?
        |--------------------------------------------------------------------------
        */

        $birthDateValue = null;

        if ($lead->birth_date) {
            try {
                $birthDateValue =
                    Carbon::parse(
                        $lead->birth_date
                    )->format('d/m/Y');
            } catch (Throwable) {
                $birthDateValue = null;
            }
        }

        $leadData = [
            'name' =>
                $lead->name,

            'phone' =>
                $lead->phone,

            'city' =>
                $lead->city,

            'line_owner' =>
                $lead->line_owner,

            'mother_maiden_surname' =>
                $lead->mother_maiden_surname,

            'limit_score' =>
                $lead->limit_score,

            'birth_date' =>
                $birthDateValue,

            'tc_identity_number' =>
                $lead->tc_identity_number,

            'limit' =>
                $lead->limit,
        ];

        $basvuruTamamlandi =
            $financeLeadService
                ->tamamlandiMi(
                    type:
                        $type,

                    data:
                        $leadData,
                );

        /*
        |--------------------------------------------------------------------------
        | DURUM
        |--------------------------------------------------------------------------
        */

        if ($lead->grubaGonderildiMi()) {
            $lead->status =
                'sent';
        } else {
            $lead->status =
                $basvuruTamamlandi
                    ? 'ready'
                    : 'collecting';
        }

        $lead->save();

        /*
        |--------------------------------------------------------------------------
        | TAMAMLANAN BAÅVURUYU WHATSAPP GRUBUNA GÃ–NDER
        |--------------------------------------------------------------------------
        |
        | AynÄ± baÅŸvuru sent_to_group_at dolduktan sonra tekrar gÃ¶nderilmez.
        | Grup gÃ¶nderiminde hata olursa mÃ¼ÅŸteriyle normal AI konuÅŸmasÄ± devam eder.
        |
        */

        if (
            $lead->status === 'ready'
            && ! $lead->grubaGonderildiMi()
            && is_string($lead->group_jid)
            && trim($lead->group_jid) !== ''
            && is_string($aiBot->whatsapp_instance)
            && trim($aiBot->whatsapp_instance) !== ''
        ) {
            try {
                $groupMessage =
                    $financeLeadService
                        ->grupMesaji(
                            type:
                                $type,

                            data:
                                $leadData,
                        );

                $sendResult =
                    $whatsAppService
                        ->sendGroupText(
                            instanceName:
                                trim(
                                    $aiBot->whatsapp_instance
                                ),

                            groupJid:
                                trim(
                                    $lead->group_jid
                                ),

                            text:
                                $groupMessage,
                        );

                $groupMessageId =
                    data_get(
                        $sendResult,
                        'key.id'
                    )
                    ?? data_get(
                        $sendResult,
                        'messageId'
                    )
                    ?? data_get(
                        $sendResult,
                        'id'
                    );

                if (
                    is_string($groupMessageId)
                    && trim($groupMessageId) !== ''
                ) {
                    $lead->group_message_id =
                        trim($groupMessageId);
                }

                $lead->sent_to_group_at =
                    now();

                $lead->status =
                    'sent';

                $lead->save();

                Log::info(
                    'Finans baÅŸvurusu WhatsApp grubuna gÃ¶nderildi',
                    [
                        'finance_lead_id' =>
                            $lead->id,

                        'ai_bot_id' =>
                            $aiBot->id,

                        'type' =>
                            $type,

                        'group_jid' =>
                            $lead->group_jid,

                        'group_message_id' =>
                            $lead->group_message_id,
                    ]
                );
            } catch (Throwable $exception) {
                Log::error(
                    'Finans baÅŸvurusu WhatsApp grubuna gÃ¶nderilemedi',
                    [
                        'finance_lead_id' =>
                            $lead->id,

                        'ai_bot_id' =>
                            $aiBot->id,

                        'type' =>
                            $type,

                        'group_jid' =>
                            $lead->group_jid,

                        'error' =>
                            $exception->getMessage(),
                    ]
                );

                report($exception);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DOÄUM TARÄ°HÄ°NÄ° VERÄ°TABANI FORMATINA Ã‡EVÄ°R
    |--------------------------------------------------------------------------
    */

    private function dogumTarihiniNormalizeEt(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        $formats = [
            'd/m/Y',
            'd.m.Y',
            'd-m-Y',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $date =
                    Carbon::createFromFormat(
                        $format,
                        $value
                    );

                if ($date !== false) {
                    return $date
                        ->format('Y-m-d');
                }
            } catch (Throwable) {
                // DiÄŸer tarih formatÄ±nÄ± dene.
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | CEVABI WHATSAPP'TAN GÃ–NDER, HAFIZAYA KAYDET VE SAYACI ARTIR
    |--------------------------------------------------------------------------
    */

    private function cevabiKaydetVeGonder(
        MemoryService $memoryService,
        WhatsAppService $whatsAppService,
        AiBot $aiBot,
        string $sessionId,
        string $instanceName,
        string $phoneNumber,
        string $answer
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Ã–NCE WHATSAPP'TAN BAÅARIYLA GÃ–NDER
        |--------------------------------------------------------------------------
        */

        $sendResult = $whatsAppService->sendText(
            instanceName:
                $instanceName,

            number:
                $phoneNumber,

            text:
                $answer,
        );

        /*
        |--------------------------------------------------------------------------
        | PANEL MESAJI / WHATSAPP DURUMU
        |--------------------------------------------------------------------------
        */

        $organizationId =
            ConversationControl::query()
                ->where(
                    'ai_bot_id',
                    $aiBot->id
                )
                ->where(
                    'session_id',
                    $sessionId
                )
                ->value(
                    'organization_id'
                )
            ?? Organization::query()
                ->where(
                    'owner_user_id',
                    $aiBot->user_id
                )
                ->value(
                    'id'
                );

        ChatMessage::create([
            'user_id' => $aiBot->user_id,
            'organization_id' => $organizationId,
            'ai_bot_id' => $aiBot->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => $answer,
            'message_type' => 'text',
            'whatsapp_message_id' =>
                data_get($sendResult, 'key.id')
                ?? data_get($sendResult, 'messageId')
                ?? data_get($sendResult, 'id'),
            'status' => 'sent',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CEVABI HAFIZAYA KAYDET
        |--------------------------------------------------------------------------
        */

        $memoryService->mesajKaydet(
            userId:
                $aiBot->user_id,

            aiBotId:
                $aiBot->id,

            sessionId:
                $sessionId,

            role:
                'assistant',

            message:
                $answer,
        );

        /*
        |--------------------------------------------------------------------------
        | ÃœCRETSÄ°Z DENEME SAYACINI ARTIR
        |--------------------------------------------------------------------------
        */

        if (
            $aiBot->subscription_status
            === 'trial'
        ) {
            $aiBot->increment(
                'trial_messages_used'
            );

            $aiBot->refresh();

            Log::info(
                'Ãœcretsiz deneme mesajÄ± kullanÄ±ldÄ±',
                [
                    'ai_bot_id' =>
                        $aiBot->id,

                    'used' =>
                        $aiBot->trial_messages_used,

                    'limit' =>
                        $aiBot->trial_message_limit,

                    'remaining' =>
                        max(
                            0,
                            $aiBot->trial_message_limit
                            - $aiBot->trial_messages_used
                        ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | 30 MESAJ TAMAMLANDI
            |--------------------------------------------------------------------------
            */

            if (
                $aiBot->trial_messages_used
                >=
                $aiBot->trial_message_limit
            ) {
                $aiBot->update([
                    'trial_messages_used' =>
                        $aiBot->trial_message_limit,

                    'trial_completed_at' =>
                        $aiBot->trial_completed_at
                        ?: now(),

                    'subscription_status' =>
                        'expired',
                ]);

                Log::info(
                    'Ãœcretsiz deneme tamamlandÄ±',
                    [
                        'ai_bot_id' =>
                            $aiBot->id,

                        'trial_message_limit' =>
                            $aiBot->trial_message_limit,
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KISA OLUMLU CEVAP
    |--------------------------------------------------------------------------
    |
    | "Evet" tek baÅŸÄ±na her zaman sipariÅŸ deÄŸildir. Sadece Ã¶nceki asistan
    | mesajÄ± sipariÅŸ teklifiyse sipariÅŸ baÅŸlangÄ±cÄ± olarak kullanÄ±lÄ±r.
    |
    */

    private function kisaOlumluCevapMi(string $message): bool
    {
        $message = $this->siparisMetniniNormalizeEt($message);

        $ifadeler = [
            'evet',
            'evet olur',
            'evet lutfen',
            'olur',
            'olabilir',
            'tamam',
            'tamamdir',
            'tamam olur',
            'isterim',
            'istiyorum',
            'alalim',
            'olsun',
            'gonderin',
            'gonder',
            'baslayalim',
            'yapalim',
        ];

        return in_array($message, $ifadeler, true);
    }

    private function oncekiAsistanSiparisTeklifiYaptiMi(
        $mesajlar
    ): bool {
        $sonAsistanMesaji = collect($mesajlar)
            ->filter(
                fn ($mesaj): bool =>
                    ($mesaj->role ?? null) === 'assistant'
                    && trim((string) ($mesaj->message ?? '')) !== ''
            )
            ->last();

        if (! $sonAsistanMesaji) {
            return false;
        }

        $metin = $this->siparisMetniniNormalizeEt(
            (string) $sonAsistanMesaji->message
        );

        $ipuclari = [
            'siparis vermek ister',
            'siparis olustur',
            'siparisinizi olustur',
            'siparise gec',
            'siparis gec',
            'siparis vermeye',
            'siparisinizi hazirla',
            'siparisinizi birlikte',
            'siparis vermek isterseniz',
            'siparis vermek istersen',
        ];

        foreach ($ipuclari as $ipucu) {
            if (str_contains($metin, $ipucu)) {
                return true;
            }
        }

        return false;
    }

    private function siparisMetniniNormalizeEt(string $message): string
    {
        $message = Str::lower(trim($message));

        $message = strtr($message, [
            'Ä±' => 'i',
            'ÅŸ' => 's',
            'ÄŸ' => 'g',
            'Ã¼' => 'u',
            'Ã¶' => 'o',
            'Ã§' => 'c',
        ]);

        $message = preg_replace(
            '/[^\\pL\\pN\\s]+/u',
            ' ',
            $message
        ) ?? $message;

        return trim(
            preg_replace('/\\s+/u', ' ', $message)
            ?? $message
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SATIN ALMA NÄ°YETÄ°
    |--------------------------------------------------------------------------
    */

    private function satinAlmaNiyetiVarMi(
        string $message
    ): bool {
        $message =
            Str::lower(
                trim($message)
            );

        $ifadeler = [
            'almak istiyorum',
            'alacaÄŸÄ±m',
            'alacagim',
            'alayÄ±m',
            'alayim',

            'sipariÅŸ vermek istiyorum',
            'siparis vermek istiyorum',

            'sipariÅŸ vereceÄŸim',
            'siparis verecegim',

            'sipariÅŸ oluÅŸtur',
            'siparis olustur',

            'satÄ±n almak istiyorum',
            'satin almak istiyorum',

            'ben bunu alayÄ±m',
            'ben bunu alayim',

            'sipariÅŸ geÃ§elim',
            'siparis gecelim',

            'sipariÅŸ verelim',
            'siparis verelim',

            'sipariÅŸ istiyorum',
            'siparis istiyorum',

            'bundan istiyorum',
            'bunu istiyorum',

            'bunu alacaÄŸÄ±m',
            'bunu alacagim',

            'gÃ¶nderin',
            'gonderin',
        ];

        foreach ($ifadeler as $ifade) {
            if (
                str_contains(
                    $message,
                    $ifade
                )
            ) {
                return true;
            }
        }

        return false;
    }
}