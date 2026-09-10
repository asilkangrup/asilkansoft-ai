<?php

namespace App\Jobs;

use App\Http\Controllers\WaiSalesOutreachWebhookController;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\OutreachLead;
use App\Services\MemoryService;
use App\Services\OpenAIService;
use App\Services\WaiLeadDemoService;
use App\Services\WaiSalesHotLeadNotifier;
use App\Services\WaiSalesOutreachService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessWaiSalesOutreachMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct(
        public string $instance,
        public string $remoteJid,
        public string $debounceKey,
        public string $tokenKey,
        public string $token,
    ) {
        $this->onConnection('database');
    }

    public function handle(
        MemoryService $memoryService,
        OpenAIService $openAIService,
        WhatsAppService $whatsAppService,
        WaiSalesOutreachService $salesService,
        WaiSalesHotLeadNotifier $hotLeadNotifier,
        WaiLeadDemoService $demoService,
    ): void {
        if ($this->instance !== WaiSalesOutreachWebhookController::INSTANCE) {
            return;
        }

        $latestToken = Cache::get($this->tokenKey);
        if (! is_string($latestToken) || $latestToken !== $this->token) {
            return;
        }

        $buffer = Cache::pull($this->debounceKey, []);
        Cache::forget($this->tokenKey);

        if (! is_array($buffer) || $buffer === []) {
            return;
        }

        $messages = collect($buffer)
            ->pluck('text')
            ->filter(fn ($text): bool => is_string($text) && trim($text) !== '')
            ->map(fn (string $text): string => trim($text))
            ->values();

        if ($messages->isEmpty()) {
            return;
        }

        $combinedMessage = $messages->implode("\n");
        $phoneDigits = preg_replace('/\D+/', '', explode('@', $this->remoteJid)[0] ?? '') ?: '';
        if ($phoneDigits === '') {
            return;
        }

        $phoneE164 = '+'.$phoneDigits;
        $bot = AiBot::query()
            ->whereKey(48)
            ->where('user_id', 44)
            ->where('whatsapp_instance', $this->instance)
            ->where('ai_enabled', true)
            ->first();

        if (! $bot) {
            Log::warning('WAI SALES BOT NOT FOUND', ['instance' => $this->instance]);
            return;
        }

        $lead = OutreachLead::query()
            ->where('user_id', 44)
            ->where('phone_e164', $phoneE164)
            ->first();

        if (! $lead) {
            Log::info('WAI SALES OUTREACH IGNORED NON-PANEL NUMBER', ['phone' => $phoneE164]);
            return;
        }

        if (! $salesService->isTextileSector($salesService->sectorFor($lead))) {
            Log::info('WAI TEXTILE SALES IGNORED NON-TEXTILE LEAD', [
                'lead_id' => $lead->id,
                'sector' => $salesService->sectorFor($lead),
            ]);
            return;
        }

        $sessionId = 'whatsapp:'.$bot->id.':'.$phoneDigits;
        $conversation = ConversationControl::query()->firstOrCreate(
            ['ai_bot_id' => $bot->id, 'session_id' => $sessionId],
            ['user_id' => $bot->user_id, 'whatsapp_number' => $phoneDigits, 'unread_count' => 0, 'human_takeover' => false]
        );

        if ($conversation->human_takeover) {
            return;
        }

        $memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $sessionId,
            role: 'user',
            message: $combinedMessage,
        );

        $conversation->forceFill([
            'unread_count' => (int) $conversation->unread_count + $messages->count(),
            'last_contact_at' => now(),
        ])->save();

        try {
            if ($this->isCallRequest($combinedMessage)) {
                $lead->forceFill([
                    'status' => 'hot',
                    'replied_at' => $lead->replied_at ?: now(),
                    'ai_activated_at' => $lead->ai_activated_at ?: now(),
                ])->save();

                $dedupeKey = 'wai-sales-call-request:'.$lead->id.':'.sha1(Str::lower(trim($combinedMessage)));
                if (Cache::add($dedupeKey, true, now()->addDay())) {
                    $hotLeadNotifier->notifyCallRequest(
                        lead: $lead->fresh(),
                        sector: $salesService->sectorFor($lead),
                        sessionId: $sessionId,
                        requestText: $combinedMessage,
                    );
                }
            }

            if (
                in_array((string) $lead->status, ['replied', 'ready', 'pain_asked', 'solution_asked'], true)
                && $this->isPositiveInterest($combinedMessage)
            ) {
                $lead->forceFill([
                    'status' => 'demo_offered',
                    'replied_at' => $lead->replied_at ?: now(),
                    'ai_activated_at' => $lead->ai_activated_at ?: now(),
                ])->save();

                $answer = "Tabii. Sistem işletmenizin çalışma şekline göre hazırlanıyor. WhatsApp’tan gelen müşterileri 7/24 karşılıyor; ürün, adet, renk, beden ve baskı detaylarını topluyor, sık sorulan soruları yanıtlıyor ve müşteriyi sipariş/satış aşamasına kadar yönlendiriyor.\n\nMüşteri logosunu veya baskı tasarımını gönderdiğinde, yapay zeka bunu seçilen ürün üzerine uygulayıp yaklaşık 5 saniye içinde profesyonel bir baskı ön izlemesi hazırlayarak WhatsApp’tan geri sunabiliyor.\n\nİsterseniz hazır kurulu WhatsApp demo hattımızdan müşteri gibi yazarak doğrudan deneyebilirsiniz. Demo hattını göndereyim mi?";

                $this->sendAnswer(
                    memoryService: $memoryService,
                    whatsAppService: $whatsAppService,
                    bot: $bot,
                    sessionId: $sessionId,
                    phoneDigits: $phoneDigits,
                    answer: $answer,
                );

                return;
            }

            $statusBeforeDecision = (string) $lead->status;
            $decision = $salesService->decide($lead, $combinedMessage);
            $action = (string) ($decision['action'] ?? 'ignore');

            if ($action === 'ignore') {
                return;
            }

            if (in_array($action, ['reply', 'reply_hot'], true)) {
                $answer = trim((string) ($decision['answer'] ?? ''));

                if ($statusBeforeDecision === 'demo_offered' && $action === 'reply_hot') {
                    $sector = (string) ($decision['sector'] ?? $salesService->sectorFor($lead));
                    if ($salesService->isTextileSector($sector)) {
                        $answer = "Tabii. Hazır kurulu WhatsApp demo hattımıza müşteri gibi “Merhaba” yazın, baskı yaptırmak istediğiniz ürünü belirtin ve logonuzu gönderin.\n\nYapay zeka logonuzu ürün üzerine uygulayıp yaklaşık 5 saniye içinde baskı ön izlemesini WhatsApp’tan size geri sunacaktır. Aynı sistem 7/24 müşterilerinize cevap verir, gerekli bilgileri toplar ve müşteriyi satış aşamasına kadar yönlendirir; böylece gelen talepler cevapsız kalmaz.\n\nDemo WhatsApp: +90 536 475 00 98\nhttps://wa.me/905364750098";
                    } else {
                        $demo = $demoService->create([
                            'company_name' => $lead->company_name,
                            'sector' => $sector,
                            'role' => 'sales',
                        ]);

                        if (($demo['status'] ?? null) === 'created' && filled($demo['url'] ?? null)) {
                            $answer = "Hazır ✅ Size özel test yapay zekasını oluşturdum:\n".(string) $demo['url'].
                                "\n\nŞu an yalnızca işletme adınızı ve sektörünüzü biliyor; buna rağmen sektörünüze uygun gerçek bir müşteri temsilcisi gibi konuşacak. Canlı kurulumda fiyatlarınızı, ürün/hizmetlerinizi, çalışma saatlerinizi, şirket kurallarınızı, kampanyalarınızı ve istediğiniz tüm yönlendirme akışlarını tamamen size özel tanımlıyoruz.\n\nTest edin; beğenirseniz 3 gün ücretsiz canlı kullanım ve kurulum desteği sağlayabiliriz.";
                        }
                    }
                }

                if ($answer === '') {
                    return;
                }

                $this->sendAnswer(
                    memoryService: $memoryService,
                    whatsAppService: $whatsAppService,
                    bot: $bot,
                    sessionId: $sessionId,
                    phoneDigits: $phoneDigits,
                    answer: $answer,
                );

                if ($action === 'reply_hot' && (bool) ($decision['became_hot'] ?? false)) {
                    $hotLeadNotifier->notify(
                        lead: $lead->fresh(),
                        sector: (string) ($decision['sector'] ?? $salesService->sectorFor($lead)),
                        sessionId: $sessionId,
                    );
                }

                return;
            }

            $history = $memoryService->openAIMesajlariHazirla(
                userId: $bot->user_id,
                sessionId: $sessionId,
                limit: 20,
            );

            array_unshift($history, [
                'role' => 'system',
                'content' => (string) ($decision['context'] ?? $salesService->context(
                    $lead->company_name,
                    (string) ($decision['sector'] ?? $salesService->sectorFor($lead)),
                )),
            ]);

            $answer = trim((string) $openAIService->cevapVer(
                mesajlar: $history,
                aiBot: $bot,
            ));

            if ($answer !== '') {
                $this->sendAnswer(
                    memoryService: $memoryService,
                    whatsAppService: $whatsAppService,
                    bot: $bot,
                    sessionId: $sessionId,
                    phoneDigits: $phoneDigits,
                    answer: $answer,
                );
            }
        } catch (Throwable $exception) {
            Log::error('WAI SALES OUTREACH PROCESSING FAILED', [
                'lead_id' => $lead->id,
                'phone' => $phoneE164,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendAnswer(
        MemoryService $memoryService,
        WhatsAppService $whatsAppService,
        AiBot $bot,
        string $sessionId,
        string $phoneDigits,
        string $answer,
    ): void {
        $memoryService->mesajKaydet(
            userId: $bot->user_id,
            aiBotId: $bot->id,
            sessionId: $sessionId,
            role: 'assistant',
            message: $answer,
        );

        $whatsAppService->sendText($this->instance, $phoneDigits, $answer);
    }

    private function isPositiveInterest(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        foreach ([
            'evet', 'ebet', 'ewet', 'evt', 'evett', 'buyurun', 'buyrun', 'olur', 'olabilir',
            'tamam', 'dinliyorum', 'anlatın', 'anlatin', 'bilgi alabilirim', 'bilgi almak',
            'isterim', 'istiyorum', 'demo', 'deneyelim', 'nasıl çalışıyor', 'nasil calisiyor',
            'tabii', 'tabi', 'elbette', 'gönder', 'gonder',
        ] as $positive) {
            if (str_contains($normalized, $positive)) {
                return true;
            }
        }

        return false;
    }

    private function isCallRequest(string $message): bool
    {
        $normalized = Str::lower(trim($message));

        foreach (['aramayın', 'aramayin', 'arama istemiyorum', 'aramaya gerek yok'] as $negative) {
            if (str_contains($normalized, $negative)) {
                return false;
            }
        }

        foreach ([
            'arayın', 'arayin', 'arayabilirsiniz', 'arayabilirsin', 'beni ara', 'beni arayın',
            'şimdi ara', 'simdi ara', 'hemen ara', 'görüşelim', 'goruselim', 'telefonla görüş',
            'telefonla gorus', 'müsaitim', 'musaitim', 'uygunum', 'bugün ara', 'bugun ara',
            'yarın ara', 'yarin ara', 'saatte ara', 'saatinde ara',
        ] as $positive) {
            if (str_contains($normalized, $positive)) {
                return true;
            }
        }

        return (bool) preg_match('/\b(?:0?\d|1\d|2[0-3])[:\.][0-5]\d\b/u', $normalized)
            && $this->containsCallContext($normalized);
    }

    private function containsCallContext(string $message): bool
    {
        foreach (['ara', 'arama', 'telefon', 'görüş', 'gorus', 'müsait', 'musait', 'uygun'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
