<?php

namespace App\Console\Commands;

use App\Models\ConversationFollowUp;
use App\Services\MemoryService;
use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('app:send-conversation-follow-ups')]
#[Description('Cevap vermeyen WhatsApp müşterilerine otomatik takip mesajları gönderir.')]
class SendConversationFollowUps extends Command
{
    public function handle(
        WhatsAppService $whatsAppService,
        MemoryService $memoryService
    ): int {
        $this->info('Otomatik takip mesajları kontrol ediliyor...');

        /*
        |--------------------------------------------------------------------------
        | SADECE AKTİF VE GEÇERLİ KAYITLARI GETİR
        |--------------------------------------------------------------------------
        |
        | Not:
        | Zaman filtresi bot bazında değiştiği için burada tamamen SQL'e
        | taşımıyoruz. Ancak gereksiz ilişkileri ve bozuk kayıtları erken eliyoruz.
        |
        */

        $followUps = ConversationFollowUp::query()
            ->with('aiBot')
            ->where('is_active', true)
            ->whereNotNull('last_customer_message_at')
            ->whereNotNull('ai_bot_id')
            ->whereNotNull('whatsapp_number')
            ->orderBy('last_customer_message_at')
            ->get();

        if ($followUps->isEmpty()) {
            $this->info('Kontrol edilecek aktif takip kaydı bulunamadı.');

            return self::SUCCESS;
        }

        foreach ($followUps as $followUp) {
            try {
                $aiBot = $followUp->aiBot;

                /*
                |--------------------------------------------------------------------------
                | BOT VAR MI?
                |--------------------------------------------------------------------------
                */

                if (! $aiBot) {
                    $followUp->update([
                        'is_active' => false,
                    ]);

                    $this->warn(
                        "Takip #{$followUp->id}: AiBot bulunamadı, pasif yapıldı."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | TAKİP SİSTEMİ AÇIK MI?
                |--------------------------------------------------------------------------
                */

                if (! $aiBot->follow_up_enabled) {
                    $followUp->update([
                        'is_active' => false,
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP INSTANCE / NUMARA VAR MI?
                |--------------------------------------------------------------------------
                */

                if (
                    blank($aiBot->whatsapp_instance)
                    || blank($followUp->whatsapp_number)
                ) {
                    $this->warn(
                        "Takip #{$followUp->id}: WhatsApp bilgileri eksik."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP GERÇEKTEN BAĞLI MI?
                |--------------------------------------------------------------------------
                |
                | DB'deki status alanı connection closed olan botlara gereksiz
                | HTTP isteği atılmasını engeller.
                |
                */

                $whatsappStatus = strtolower(
                    trim((string) $aiBot->whatsapp_status)
                );

                if (
                    $whatsappStatus !== ''
                    && ! in_array(
                        $whatsappStatus,
                        [
                            'connected',
                            'open',
                        ],
                        true
                    )
                ) {
                    $this->warn(
                        "Takip #{$followUp->id}: WhatsApp bağlı değil ({$whatsappStatus})."
                    );

                    continue;
                }

                $lastCustomerMessageAt =
                    $followUp->last_customer_message_at;

                if (! $lastCustomerMessageAt) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 1. TAKİP
                |--------------------------------------------------------------------------
                */

                $firstFollowUpMinutes = max(
                    1,
                    (int) (
                        $aiBot->first_follow_up_minutes
                        ?: 1440
                    )
                );

                $firstFollowUpTime =
                    $lastCustomerMessageAt
                        ->copy()
                        ->addMinutes(
                            $firstFollowUpMinutes
                        );

                if (! $followUp->first_follow_up_sent_at) {
                    /*
                    |--------------------------------------------------------------------------
                    | HENÜZ ZAMANI GELMEDİYSE HİÇBİR ŞEY YAPMA
                    |--------------------------------------------------------------------------
                    */

                    if (now()->lessThan($firstFollowUpTime)) {
                        continue;
                    }

                    $firstMessage = trim(
                        (string) $aiBot->first_follow_up_message
                    );

                    if ($firstMessage === '') {
                        $firstMessage =
                            'Merhaba 👋 Daha önce görüştüğümüz ürünle hâlâ ilgileniyor musunuz? Size yardımcı olabilirim.';
                    }

                    $whatsAppService->sendText(
                        instanceName: trim(
                            (string) $aiBot->whatsapp_instance
                        ),
                        number: (string) $followUp->whatsapp_number,
                        text: $firstMessage,
                    );

                    $memoryService->mesajKaydet(
                        userId: $followUp->user_id,
                        aiBotId: $followUp->ai_bot_id,
                        sessionId: $followUp->session_id,
                        role: 'assistant',
                        message: $firstMessage,
                    );

                    $now = now();

                    $followUp->update([
                        'first_follow_up_sent_at' => $now,
                        'follow_up_sent_at' => $now,
                        'last_bot_message_at' => $now,
                    ]);

                    $this->info(
                        "Takip #{$followUp->id}: 1. takip mesajı gönderildi."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | İKİNCİ TAKİP KAPALIYSA TAMAMLA
                |--------------------------------------------------------------------------
                */

                if (! $aiBot->second_follow_up_enabled) {
                    $followUp->update([
                        'is_active' => false,
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | İKİNCİ TAKİP ZATEN GÖNDERİLDİYSE PASİF YAP
                |--------------------------------------------------------------------------
                */

                if ($followUp->second_follow_up_sent_at) {
                    $followUp->update([
                        'is_active' => false,
                    ]);

                    continue;
                }

                $secondFollowUpMinutes = max(
                    1,
                    (int) (
                        $aiBot->second_follow_up_minutes
                        ?: 4320
                    )
                );

                $secondFollowUpTime =
                    $lastCustomerMessageAt
                        ->copy()
                        ->addMinutes(
                            $secondFollowUpMinutes
                        );

                /*
                |--------------------------------------------------------------------------
                | 2. TAKİP ZAMANI GELMEDİYSE GEÇ
                |--------------------------------------------------------------------------
                */

                if (now()->lessThan($secondFollowUpTime)) {
                    continue;
                }

                $secondMessage = trim(
                    (string) $aiBot->second_follow_up_message
                );

                if ($secondMessage === '') {
                    $secondMessage =
                        'Merhaba 👋 Daha önce görüştüğümüz ürünle ilgili yardımcı olabileceğimiz bir konu var mı? Dilerseniz siparişinizi birlikte oluşturabiliriz.';
                }

                $whatsAppService->sendText(
                    instanceName: trim(
                        (string) $aiBot->whatsapp_instance
                    ),
                    number: (string) $followUp->whatsapp_number,
                    text: $secondMessage,
                );

                $memoryService->mesajKaydet(
                    userId: $followUp->user_id,
                    aiBotId: $followUp->ai_bot_id,
                    sessionId: $followUp->session_id,
                    role: 'assistant',
                    message: $secondMessage,
                );

                $now = now();

                $followUp->update([
                    'second_follow_up_sent_at' => $now,
                    'follow_up_sent_at' => $now,
                    'last_bot_message_at' => $now,
                    'is_active' => false,
                ]);

                $this->info(
                    "Takip #{$followUp->id}: 2. ve son takip mesajı gönderildi."
                );
            } catch (Throwable $exception) {
                Log::error(
                    'Otomatik takip mesajı gönderilemedi',
                    [
                        'follow_up_id' =>
                            $followUp->id,

                        'ai_bot_id' =>
                            $followUp->ai_bot_id,

                        'session_id' =>
                            $followUp->session_id,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | BAĞLANTI / INSTANCE HATASINDA SONSUZ TEKRAR YAPMA
                |--------------------------------------------------------------------------
                |
                | Mesaj içeriği veya geçici OpenAI hatası nedeniyle takip
                | tamamen kapatılmıyor.
                |
                */

                $errorMessage = strtolower(
                    $exception->getMessage()
                );

                if (
                    str_contains(
                        $errorMessage,
                        'connection closed'
                    )
                    || str_contains(
                        $errorMessage,
                        'instance does not exist'
                    )
                    || str_contains(
                        $errorMessage,
                        'instance is not connected'
                    )
                ) {
                    $followUp->update([
                        'is_active' => false,
                    ]);
                }

                report($exception);

                $this->error(
                    "Takip #{$followUp->id}: "
                    .$exception->getMessage()
                );
            }
        }

        $this->info(
            'Otomatik takip kontrolü tamamlandı.'
        );

        return self::SUCCESS;
    }
}