<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationFollowUp;
use App\Models\ConversationControl;
use App\Models\FinanceLead;
use App\Services\FinanceLeadExtractorService;
use App\Services\FinanceLeadService;
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
        FinanceLeadExtractorService $financeLeadExtractorService
    ): JsonResponse {
        try {
            $payload = $request->all();

            /*
            |--------------------------------------------------------------------------
            | SADECE YENİ MESAJLARI İŞLE
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
            | Evolution API'nin 60 saniye timeout'a düşmemesi için ağır işlemler
            | (OpenAI, sipariş, finans, WhatsApp gönderimi) queue worker'da çalışır.
            |
            | Queue worker aynı controller'ı _wai_queued=true ile yeniden çağırır.
            | Böylece bu blok ikinci kez job oluşturmaz.
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
            | BOTUN KENDİ MESAJINI TEKRAR CEVAPLAMA
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
            | AYNI WHATSAPP MESAJINI SADECE BİR KEZ İŞLE
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
                        'Tekrarlanan WhatsApp webhook mesajı engellendi',
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
                    'message' => 'Instance bulunamadı.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DOĞRU YAPAY ZEKÂ BOTUNU BUL
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
                    'Webhook için AiBot bulunamadı',
                    [
                        'instance' => $instanceName,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Bu instance için yapay zekâ bulunamadı.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ NUMARASI
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
                    $mediaFilename = data_get($image, 'fileName') ?? 'Fotoğraf';
                    $mediaCaption = data_get($image, 'caption');
                    $message = $mediaCaption ?: '[Fotoğraf]';
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
            | ABONELİK / 30 MESAJLIK DENEME KONTROLÜ
            |--------------------------------------------------------------------------
            */

            if (
                ! $aiBot->whatsappAiKullanilabilirMi()
            ) {
                Log::info(
                    'WhatsApp AI kullanım limiti nedeniyle cevap verilmedi',
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
            | KONUŞMA KONTROL KAYDI
            |--------------------------------------------------------------------------
            |
            | Her WhatsApp konuşması için tek bir kontrol kaydı oluşturulur.
            | Böylece panelden sadece bu müşterinin konuşması devralınabilir.
            |
            */

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

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ MESAJINI HAFIZAYA KAYDET
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
            | MÜŞTERİ ADI
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

            if ($customerName !== '') {
                $conversationControl->customer_name =
                    $customerName;
            }

            /*
            |--------------------------------------------------------------------------
            | OKUNMAMIŞ MESAJ
            |--------------------------------------------------------------------------
            */

            $conversationControl->unread_count =
                (int) $conversationControl->unread_count + 1;

            $conversationControl->updated_at =
                now();

            $conversationControl->save();

            /*
            |--------------------------------------------------------------------------
            | MEDYA MESAJINI PANEL HAFIZASINA KAYDET
            |--------------------------------------------------------------------------
            */

            if ($messageType !== 'text') {
                ChatMessage::create([
                    'user_id' => $aiBot->user_id,
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
            | İNSAN DEVRALMA KONTROLÜ
            |--------------------------------------------------------------------------
            |
            | Bu WhatsApp konuşması bir personel tarafından devralındıysa
            | müşterinin mesajı hafızada kalır fakat yapay zekâ cevap vermez.
            |
            */

            if (
                $conversationControl
                && $conversationControl->human_takeover
            ) {
                Log::info(
                    'Konuşma insan tarafından devralındığı için AI cevap vermedi',
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
            | OTOMATİK TAKİP SAYACINI BAŞLAT / SIFIRLA
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
            | FİNANS BAŞVURU TAKİBİ
            |--------------------------------------------------------------------------
            |
            | Bu bölüm sadece group_routing_enabled = true olan botlarda çalışır.
            | Konuşmayı analiz eder, finance_leads tablosuna kaydeder ve
            | tüm zorunlu bilgiler tamamlandığında doğru WhatsApp grubuna yollar.
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
                                30,
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
            | NORMAL SİPARİŞ SİSTEMİ
            |--------------------------------------------------------------------------
            |
            | Finans botunda normal ürün / sipariş sistemi çalıştırılmaz.
            |
            | Böylece müşterinin:
            | "limit istiyorum"
            | "almak istiyorum"
            | vb. ifadeleri yanlışlıkla ürün siparişi oluşturmaz.
            |
            */

            if (
                ! $financeLeadService->aktifMi($aiBot)
            ) {
                /*
                |--------------------------------------------------------------------------
                | MEVCUT TASLAK SİPARİŞİ BUL
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
                | SATIN ALMA NİYETİ VARSA TASLAK OLUŞTUR
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
                         * Taslak yeni oluştuysa son konuşmadaki ürün ve
                         * miktarı da siparişe aktar. Böylece müşteri
                         * "evet" dediğinde önceki "5 litre zeytinyağı"
                         * mesajı kaybolmaz.
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
                | SİPARİŞ AKIŞI
                |--------------------------------------------------------------------------
                */

                if ($order) {
                    /*
                    |--------------------------------------------------------------------------
                    | GELEN MESAJDAN SİPARİŞ BİLGİLERİNİ YAKALA
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
                    | MÜŞTERİ ONAY VERDİYSE
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
                                "✅ Siparişiniz başarıyla alındı.\n\n"
                                .$orderService
                                    ->siparisOzeti(
                                        $order
                                    )
                                ."\n\nSiparişiniz firmaya iletildi.";

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
                                    'Sipariş onaylandı.',
                            ]);
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | EKSİK BİLGİ VARKEN ONAY VERDİYSE
                        |--------------------------------------------------------------------------
                        */

                        $answer =
                            $orderService
                                ->siradakiEksikSoru(
                                    $order
                                )
                            ?? 'Sipariş bilgileriniz henüz tamamlanmadı.';

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
                                'Eksik sipariş bilgisi istendi.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | TÜM BİLGİLER TAMAMSA ÖZET GÖNDER
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
                            ."\n\nBilgiler doğruysa sadece *Onaylıyorum* yazabilirsiniz.";

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
                                'Sipariş özeti gönderildi.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | EKSİK BİLGİ VARSA SADECE SIRADAKİNİ SOR
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
                                'Sıradaki sipariş bilgisi istendi.',
                        ]);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL GPT KONUŞMASI
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
                    'Yapay zekâ cevabı gönderildi.',
            ]);

        } catch (Throwable $exception) {
            Log::error(
                'WhatsApp AI webhook hatası',
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
                    'Webhook işlenirken hata oluştu.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FİNANS BAŞVURUSUNU KAYDET / GÜNCELLE
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
    | FİNANS BAŞVURUSUNU KAYDET / GÜNCELLE
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
        | BAŞVURU TÜRÜ HENÜZ BELLİ DEĞİL
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
        | MEVCUT KAYDI BUL / YENİ KAYIT HAZIRLA
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
        | SABİT BİLGİLER
        |--------------------------------------------------------------------------
        */

        $lead->user_id =
            $aiBot->user_id;

        $lead->whatsapp_number =
            $whatsappNumber;

        /*
        |--------------------------------------------------------------------------
        | AI TARAFINDAN ÇIKARILAN BİLGİLER
        |--------------------------------------------------------------------------
        |
        | Null gelen değerlerle daha önce kaydedilmiş doğru bilgileri silmiyoruz.
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
        | DOĞUM TARİHİ
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
        | BAŞVURU TAMAM MI?
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
        | TAMAMLANAN BAŞVURUYU WHATSAPP GRUBUNA GÖNDER
        |--------------------------------------------------------------------------
        |
        | Aynı başvuru sent_to_group_at dolduktan sonra tekrar gönderilmez.
        | Grup gönderiminde hata olursa müşteriyle normal AI konuşması devam eder.
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
                    'Finans başvurusu WhatsApp grubuna gönderildi',
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
                    'Finans başvurusu WhatsApp grubuna gönderilemedi',
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
    | DOĞUM TARİHİNİ VERİTABANI FORMATINA ÇEVİR
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
                // Diğer tarih formatını dene.
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | CEVABI WHATSAPP'TAN GÖNDER, HAFIZAYA KAYDET VE SAYACI ARTIR
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
        | ÖNCE WHATSAPP'TAN BAŞARIYLA GÖNDER
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

        ChatMessage::create([
            'user_id' => $aiBot->user_id,
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
        | ÜCRETSİZ DENEME SAYACINI ARTIR
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
                'Ücretsiz deneme mesajı kullanıldı',
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
                    'Ücretsiz deneme tamamlandı',
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
    | "Evet" tek başına her zaman sipariş değildir. Sadece önceki asistan
    | mesajı sipariş teklifiyse sipariş başlangıcı olarak kullanılır.
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
            'ı' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
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
    | SATIN ALMA NİYETİ
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
            'alacağım',
            'alacagim',
            'alayım',
            'alayim',

            'sipariş vermek istiyorum',
            'siparis vermek istiyorum',

            'sipariş vereceğim',
            'siparis verecegim',

            'sipariş oluştur',
            'siparis olustur',

            'satın almak istiyorum',
            'satin almak istiyorum',

            'ben bunu alayım',
            'ben bunu alayim',

            'sipariş geçelim',
            'siparis gecelim',

            'sipariş verelim',
            'siparis verelim',

            'sipariş istiyorum',
            'siparis istiyorum',

            'bundan istiyorum',
            'bunu istiyorum',

            'bunu alacağım',
            'bunu alacagim',

            'gönderin',
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