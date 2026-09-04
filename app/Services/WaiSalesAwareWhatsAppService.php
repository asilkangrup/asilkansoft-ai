<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Illuminate\Support\Facades\Log;
use Throwable;

class WaiSalesAwareWhatsAppService extends WhatsAppService
{
    public function sendText(string $instanceName, string $number, string $text): array
    {
        if (str_contains($text, WaiSalesDemoOrchestrator::CREATE_MARKER)) {
            try {
                $text = $this->finalizeSalesDemoText(
                    instanceName: $instanceName,
                    number: $number,
                    text: $text,
                );
            } catch (Throwable $exception) {
                Log::warning('WAI SALES DEMO FINALIZE FAILED', [
                    'instance' => $instanceName,
                    'message' => $exception->getMessage(),
                ]);

                $text = trim(str_replace(
                    WaiSalesDemoOrchestrator::CREATE_MARKER,
                    '',
                    $text
                ));
            }
        }

        return parent::sendText($instanceName, $number, $text);
    }

    private function finalizeSalesDemoText(
        string $instanceName,
        string $number,
        string $text
    ): string {
        $bot = AiBot::query()
            ->where('whatsapp_instance', trim($instanceName))
            ->first();

        if (! $bot) {
            return trim(str_replace(WaiSalesDemoOrchestrator::CREATE_MARKER, '', $text));
        }

        $orchestrator = app(WaiSalesDemoOrchestrator::class);

        if (! $orchestrator->isSalesBot($bot)) {
            return trim(str_replace(WaiSalesDemoOrchestrator::CREATE_MARKER, '', $text));
        }

        $normalizedNumber = preg_replace('/\D+/', '', $number) ?? $number;

        $conversation = ConversationControl::query()
            ->where('ai_bot_id', $bot->id)
            ->where('whatsapp_number', $normalizedNumber)
            ->latest('id')
            ->first();

        if (! $conversation) {
            return trim(str_replace(WaiSalesDemoOrchestrator::CREATE_MARKER, '', $text));
        }

        $history = ChatMessage::query()
            ->where('ai_bot_id', $bot->id)
            ->where('session_id', $conversation->session_id)
            ->latest('id')
            ->limit(16)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->sender_type === 'customer' || $message->role === 'user'
                    ? 'user'
                    : 'assistant',
                'content' => (string) $message->message,
            ])
            ->values()
            ->all();

        return $orchestrator->finalize(
            bot: $bot,
            conversation: $conversation,
            answer: $text,
            history: $history,
        );
    }
}
