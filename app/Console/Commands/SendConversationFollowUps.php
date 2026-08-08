<?php

namespace App\Console\Commands;

use App\Models\ConversationFollowUp;
use App\Services\MemoryService;
use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('app:send-conversation-follow-ups')]
#[Description('Cevap vermeyen WhatsApp müşterilerine otomatik takip mesajları gönderir.')]
class SendConversationFollowUps extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(
        WhatsAppService $whatsAppService,
        MemoryService $memoryService
    ): int {
        $this->info('Otomatik takip mesajları kontrol ediliyor...');

        /*
        |--------------------------------------------------------------------------
        | AKTİF TAKİP KAYITLARINI GETİR
        |--------------------------------------------------------------------------
        */

        $followUps = ConversationFollowUp::query()
            ->with('aiBot')
            ->where('is_active', true)
            ->whereNotNull('last_customer_message_at')
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
                    $this->warn(
                        "Takip #{$followUp->id}: AiBot bulunamadı."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | TAKİP SİSTEMİ HÂLÂ AÇIK MI?
                |--------------------------------------------------------------------------
                */

                if (! $aiBot->follow_up_enabled) {
                    $followUp->update([
                        'is_active' => false,
                    ]);

                    $this->info(
                        "Takip #{$followUp->id}: Takip sistemi kapalı."
                    );

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP BAĞLANTISI VAR MI?
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
                | SON MÜŞTERİ MESAJI
                |--------------------------------------------------------------------------
                */

                $lastCustomerMessageAt =
                    $followUp->last_customer_message_at;

                if (! $lastCustomerMessageAt) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 1. TAKİP MESAJI
                |--------------------------------------------------------------------------
                */

                $firstFollowUpMinutes =
                    max(
                        1,
                        (int) ($aiBot->first_follow_up_minutes ?: 1440)
                    );

                $firstFollowUpTime =
                    $lastCustomerMessageAt
                        ->copy()
                        ->addMinutes($firstFollowUpMinutes);

                if (
                    ! $followUp->first_follow_up_sent_at
                    && now()->greaterThanOrEqualTo($firstFollowUpTime)
                ) {
                    $firstMessage = trim(
                        (string) $aiBot->first_follow_up_message
                    );

                    if ($firstMessage === '') {
                        $firstMessage =
                            'Merhaba 👋 Daha önce görüştüğümüz ürünle hâlâ ilgileniyor musunuz? Size yardımcı olabilirim.';
                    }

                    /*
                     * WhatsApp mesajını gönder.
                     */
                    $whatsAppService->sendText(
                        instanceName: $aiBot->whatsapp_instance,
                        number: $followUp->whatsapp_number,
                        text: $firstMessage,
                    );

                    /*
                     * Mesajı konuşma hafızasına da kaydet.
                     */
                    $memoryService->mesajKaydet(
                        userId: $followUp->user_id,
                        aiBotId: $followUp->ai_bot_id,
                        sessionId: $followUp->session_id,
                        role: 'assistant',
                        message: $firstMessage,
                    );

                    /*
                     * İlk takip gönderildi olarak işaretle.
                     */
                    $followUp->update([
                        'first_follow_up_sent_at' => now(),
                        'follow_up_sent_at' => now(),
                        'last_bot_message_at' => now(),
                    ]);

                    $this->info(
                        "Takip #{$followUp->id}: 1. takip mesajı gönderildi."
                    );

                    /*
                     * Aynı komut çalışmasında ikinci mesajı da
                     * hemen göndermemek için bu kayıtta burada dur.
                     */
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | 2. VE SON TAKİP MESAJI
                |--------------------------------------------------------------------------
                */

                if (! $aiBot->second_follow_up_enabled) {
                    /*
                     * İlk takip gönderildiyse ve ikinci takip kapalıysa
                     * bu konuşma için otomatik takip tamamlanmıştır.
                     */
                    if ($followUp->first_follow_up_sent_at) {
                        $followUp->update([
                            'is_active' => false,
                        ]);
                    }

                    continue;
                }

                /*
                 * İlk takip henüz gönderilmediyse ikinci takip gönderilmez.
                 */
                if (! $followUp->first_follow_up_sent_at) {
                    continue;
                }

                $secondFollowUpMinutes =
                    max(
                        1,
                        (int) ($aiBot->second_follow_up_minutes ?: 4320)
                    );

                /*
                 * 2. süre de müşterinin SON mesajından itibaren hesaplanır.
                 *
                 * Örnek:
                 * 1. mesaj = 1 saat
                 * 2. mesaj = 1 gün
                 *
                 * Müşteri 10:00'da son mesajı attıysa:
                 * 1. takip = 11:00
                 * 2. takip = ertesi gün 10:00
                 */
                $secondFollowUpTime =
                    $lastCustomerMessageAt
                        ->copy()
                        ->addMinutes($secondFollowUpMinutes);

                if (
                    ! $followUp->second_follow_up_sent_at
                    && now()->greaterThanOrEqualTo($secondFollowUpTime)
                ) {
                    $secondMessage = trim(
                        (string) $aiBot->second_follow_up_message
                    );

                    if ($secondMessage === '') {
                        $secondMessage =
                            'Merhaba 👋 Daha önce görüştüğümüz ürünle ilgili yardımcı olabileceğimiz bir konu var mı? Dilerseniz siparişinizi birlikte oluşturabiliriz.';
                    }

                    /*
                     * WhatsApp mesajını gönder.
                     */
                    $whatsAppService->sendText(
                        instanceName: $aiBot->whatsapp_instance,
                        number: $followUp->whatsapp_number,
                        text: $secondMessage,
                    );

                    /*
                     * Hafızaya kaydet.
                     */
                    $memoryService->mesajKaydet(
                        userId: $followUp->user_id,
                        aiBotId: $followUp->ai_bot_id,
                        sessionId: $followUp->session_id,
                        role: 'assistant',
                        message: $secondMessage,
                    );

                    /*
                     * İkinci takip SON mesajdır.
                     * Bundan sonra sistem bu konuşmayı takip etmez.
                     */
                    $followUp->update([
                        'second_follow_up_sent_at' => now(),
                        'follow_up_sent_at' => now(),
                        'last_bot_message_at' => now(),
                        'is_active' => false,
                    ]);

                    $this->info(
                        "Takip #{$followUp->id}: 2. ve son takip mesajı gönderildi."
                    );
                }

            } catch (Throwable $exception) {
                Log::error('Otomatik takip mesajı gönderilemedi', [
                    'follow_up_id' => $followUp->id,
                    'ai_bot_id' => $followUp->ai_bot_id,
                    'session_id' => $followUp->session_id,
                    'message' => $exception->getMessage(),
                ]);

                report($exception);

                $this->error(
                    "Takip #{$followUp->id}: "
                    .$exception->getMessage()
                );
            }
        }

        $this->info('Otomatik takip kontrolü tamamlandı.');

        return self::SUCCESS;
    }
}