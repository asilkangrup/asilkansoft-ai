<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use App\Models\ConversationFollowUp;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\OrderService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function handle(
        Request $request,
        MemoryService $memoryService,
        OpenAIService $openAIService,
        WhatsAppService $whatsAppService,
        OrderService $orderService
    ): JsonResponse {
        try {
            $payload = $request->all();

            /*
            |--------------------------------------------------------------------------
            | SADECE YENİ MESAJLARI İŞLE
            |--------------------------------------------------------------------------
            */

            if (data_get($payload, 'event') !== 'messages.upsert') {
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
            | INSTANCE
            |--------------------------------------------------------------------------
            */

            $instanceName = trim((string) data_get(
                $payload,
                'instance',
                ''
            ));

            if ($instanceName === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Instance bulunamadı.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DOĞRU YAPAY ZEKA BOTUNU BUL
            |--------------------------------------------------------------------------
            */

            $aiBot = AiBot::query()
                ->where('whatsapp_instance', $instanceName)
                ->first();

            if (! $aiBot) {
                Log::warning('Webhook için AiBot bulunamadı', [
                    'instance' => $instanceName,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bu instance için yapay zekâ bulunamadı.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ NUMARASI
            |--------------------------------------------------------------------------
            */

            $remoteJid = trim((string) data_get(
                $payload,
                'data.key.remoteJid',
                ''
            ));

            if (
                $remoteJid === ''
                || str_contains($remoteJid, '@g.us')
            ) {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'unsupported_chat',
                ]);
            }

            $phoneNumber = explode('@', $remoteJid)[0];

            /*
            |--------------------------------------------------------------------------
            | MESAJ
            |--------------------------------------------------------------------------
            */

            $message =
                data_get($payload, 'data.message.conversation')
                ?? data_get(
                    $payload,
                    'data.message.extendedTextMessage.text'
                );

            $message = trim((string) $message);

            if ($message === '') {
                return response()->json([
                    'success' => true,
                    'ignored' => true,
                    'reason' => 'non_text_message',
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
            |
            | Müşteri her yeni mesaj gönderdiğinde son mesaj zamanı güncellenir.
            |
            | Daha önce gönderilmiş takip mesajları sıfırlanır.
            |
            | Böylece müşteri tekrar yazarsa takip süreci baştan başlar.
            |
            */

            if ($aiBot->follow_up_enabled) {
                ConversationFollowUp::updateOrCreate(
                    [
                        'ai_bot_id' => $aiBot->id,
                        'session_id' => $sessionId,
                    ],
                    [
                        'user_id' => $aiBot->user_id,
                        'whatsapp_number' => $phoneNumber,
                        'last_customer_message_at' => now(),

                        'first_follow_up_sent_at' => null,
                        'second_follow_up_sent_at' => null,

                        'follow_up_sent_at' => null,

                        'is_active' => true,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | MEVCUT TASLAK SİPARİŞİ BUL
            |--------------------------------------------------------------------------
            */

            $order = $orderService->taslakSiparisiGetir(
                aiBotId: $aiBot->id,
                sessionId: $sessionId,
            );

            /*
            |--------------------------------------------------------------------------
            | SATIN ALMA NİYETİ VARSA TASLAK OLUŞTUR
            |--------------------------------------------------------------------------
            */

            if (
                ! $order
                && $this->satinAlmaNiyetiVarMi($message)
            ) {
                $order = $orderService->taslakSiparisOlustur(
                    aiBotId: $aiBot->id,
                    sessionId: $sessionId,
                    whatsappNumber: $phoneNumber,
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SİPARİŞ AKIŞI
            |--------------------------------------------------------------------------
            */

            if ($order) {

                /*
                 * Gelen mesajdan ürün, miktar, isim,
                 * adres veya ödeme bilgisini yakala.
                 */

                $order = $orderService->mesajdanSiparisiGuncelle(
                    order: $order,
                    message: $message,
                    aiBot: $aiBot,
                );

                /*
                |--------------------------------------------------------------------------
                | MÜŞTERİ ONAY VERDİYSE
                |--------------------------------------------------------------------------
                */

                if ($orderService->onayMesajiMi($message)) {

                    if (
                        $orderService->siparisTamamlanmayaHazirMi($order)
                    ) {
                        $order = $orderService->siparisiOnayla($order);

                        $answer =
                            "✅ Siparişiniz başarıyla alındı.\n\n".
                            $orderService->siparisOzeti($order).
                            "\n\nSiparişiniz firmaya iletildi.";

                        $this->cevabiKaydetVeGonder(
                            memoryService: $memoryService,
                            whatsAppService: $whatsAppService,
                            aiBot: $aiBot,
                            sessionId: $sessionId,
                            instanceName: $instanceName,
                            phoneNumber: $phoneNumber,
                            answer: $answer,
                        );

                        return response()->json([
                            'success' => true,
                            'message' => 'Sipariş onaylandı.',
                        ]);
                    }

                    /*
                     * Eksik bilgi varken onay vermeye çalıştıysa
                     * doğru eksik bilgiyi sor.
                     */

                    $answer = $orderService->siradakiEksikSoru($order)
                        ?? 'Sipariş bilgileriniz henüz tamamlanmadı.';

                    $this->cevabiKaydetVeGonder(
                        memoryService: $memoryService,
                        whatsAppService: $whatsAppService,
                        aiBot: $aiBot,
                        sessionId: $sessionId,
                        instanceName: $instanceName,
                        phoneNumber: $phoneNumber,
                        answer: $answer,
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'Eksik sipariş bilgisi istendi.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | TÜM BİLGİLER TAMAMSA ÖZET GÖNDER
                |--------------------------------------------------------------------------
                */

                if (
                    $orderService->siparisTamamlanmayaHazirMi($order)
                ) {
                    $answer =
                        $orderService->siparisOzeti($order).
                        "\n\nBilgiler doğruysa sadece *Onaylıyorum* yazabilirsiniz.";

                    $this->cevabiKaydetVeGonder(
                        memoryService: $memoryService,
                        whatsAppService: $whatsAppService,
                        aiBot: $aiBot,
                        sessionId: $sessionId,
                        instanceName: $instanceName,
                        phoneNumber: $phoneNumber,
                        answer: $answer,
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'Sipariş özeti gönderildi.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | EKSİK BİLGİ VARSA SADECE SIRADAKİNİ SOR
                |--------------------------------------------------------------------------
                */

                $nextQuestion = $orderService->siradakiEksikSoru($order);

                if ($nextQuestion) {
                    $this->cevabiKaydetVeGonder(
                        memoryService: $memoryService,
                        whatsAppService: $whatsAppService,
                        aiBot: $aiBot,
                        sessionId: $sessionId,
                        instanceName: $instanceName,
                        phoneNumber: $phoneNumber,
                        answer: $nextQuestion,
                    );

                    return response()->json([
                        'success' => true,
                        'message' => 'Sıradaki sipariş bilgisi istendi.',
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NORMAL GPT KONUŞMASI
            |--------------------------------------------------------------------------
            |
            | Aktif sipariş yoksa normal yapay zekâ konuşması devam eder.
            |
            */

            $history = $memoryService->openAIMesajlariHazirla(
                userId: $aiBot->user_id,
                sessionId: $sessionId,
                limit: 20,
            );

            $answer = $openAIService->cevapVer(
                mesajlar: $history,
                aiBot: $aiBot,
            );

            $this->cevabiKaydetVeGonder(
                memoryService: $memoryService,
                whatsAppService: $whatsAppService,
                aiBot: $aiBot,
                sessionId: $sessionId,
                instanceName: $instanceName,
                phoneNumber: $phoneNumber,
                answer: $answer,
            );

            return response()->json([
                'success' => true,
                'message' => 'Yapay zekâ cevabı gönderildi.',
            ]);

        } catch (Throwable $exception) {
            Log::error('WhatsApp AI webhook hatası', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Webhook işlenirken hata oluştu.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CEVABI HAFIZAYA KAYDET VE WHATSAPP'TAN GÖNDER
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
        $memoryService->mesajKaydet(
            userId: $aiBot->user_id,
            aiBotId: $aiBot->id,
            sessionId: $sessionId,
            role: 'assistant',
            message: $answer,
        );

        $whatsAppService->sendText(
            instanceName: $instanceName,
            number: $phoneNumber,
            text: $answer,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SATIN ALMA NİYETİ
    |--------------------------------------------------------------------------
    */

    private function satinAlmaNiyetiVarMi(string $message): bool
    {
        $message = Str::lower(trim($message));

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
            if (str_contains($message, $ifade)) {
                return true;
            }
        }

        return false;
    }
}