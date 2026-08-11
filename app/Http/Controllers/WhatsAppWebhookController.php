<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Models\ConversationFollowUp;
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

            if (
                data_get($payload, 'event')
                !== 'messages.upsert'
            ) {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
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
            | MESAJ
            |--------------------------------------------------------------------------
            */

            $message =
                data_get(
                    $payload,
                    'data.message.conversation'
                )
                ?? data_get(
                    $payload,
                    'data.message.extendedTextMessage.text'
                );

            $message = trim(
                (string) $message
            );

            if ($message === '') {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'non_text_message',
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
            | KREDİ REHBERİM / BOT 13 FİNANS BAŞVURU TAKİBİ
            |--------------------------------------------------------------------------
            |
            | Bu bölüm sadece Bot 13 için çalışır.
            |
            | Şimdilik yalnızca konuşmayı analiz eder ve finance_leads
            | tablosuna bilgileri yazar.
            |
            | Grup JID bilgileri henüz tanımlı olmadığı için WhatsApp
            | grubuna mesaj gönderilmez.
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

                if (
                    ! $order
                    && $this->satinAlmaNiyetiVarMi(
                        $message
                    )
                ) {
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
    | BOT 13 FİNANS BAŞVURUSUNU KAYDET / GÜNCELLE
    |--------------------------------------------------------------------------
    */

    private function financeLeadKaydet(
        AiBot $aiBot,
        string $sessionId,
        string $whatsappNumber,
        array $data,
        FinanceLeadService $financeLeadService
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
        |
        | QR bağlanana kadar FinanceLeadService null döndürür.
        |
        */

        $groupJid =
            $financeLeadService
                ->groupJid(
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

        $lead->status =
            $financeLeadService
                ->tamamlandiMi(
                    type:
                        $type,

                    data:
                        $leadData,
                )
            ? 'ready'
            : 'collecting';

        $lead->save();

        /*
        |--------------------------------------------------------------------------
        | GRUP GÖNDERİMİ ŞİMDİLİK KAPALI
        |--------------------------------------------------------------------------
        |
        | QR bağlandıktan sonra 5 grubun gerçek JID bilgilerini ekleyeceğiz.
        | Ardından yalnızca status=ready ve sent_to_group_at=null kayıtları
        | doğru WhatsApp grubuna göndereceğiz.
        |
        */
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

        $whatsAppService->sendText(
            instanceName:
                $instanceName,

            number:
                $phoneNumber,

            text:
                $answer,
        );

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