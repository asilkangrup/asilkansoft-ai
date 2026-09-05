<?php

namespace App\Services;

use App\Models\AiBot;
use Illuminate\Support\Str;

class WaiPricingOpenAIService extends WaiLifecycleOpenAIService
{
    public function cevapVer(
        string|array $mesajlar,
        ?AiBot $aiBot = null
    ): string {
        if (
            $this->isMainSalesBot($aiBot)
            && is_array($mesajlar)
            && $this->isPriceQuestion($this->lastUserMessage($mesajlar))
        ) {
            $priceReply = '2.990 TL’den başlayan fiyatlarımız vardır. İhtiyaçlarınıza ve talep ettiğiniz özelliklere göre fiyat değişiklik gösterebilir.';

            if ($this->demoLinkAlreadyExists($mesajlar)) {
                return $priceReply.' Ekip arkadaşlarımız sizi arayacak.';
            }

            $historyWithoutPriceQuestion = $mesajlar;

            for ($i = count($historyWithoutPriceQuestion) - 1; $i >= 0; $i--) {
                if (is_array($historyWithoutPriceQuestion[$i]) && ($historyWithoutPriceQuestion[$i]['role'] ?? '') === 'user') {
                    array_splice($historyWithoutPriceQuestion, $i, 1);
                    break;
                }
            }

            $next = trim(parent::cevapVer($historyWithoutPriceQuestion, $aiBot));

            return $next !== ''
                ? $priceReply."\n\n".$this->normalizeEscapedNewlines($next)
                : $priceReply;
        }

        return $this->normalizeEscapedNewlines(
            parent::cevapVer($mesajlar, $aiBot)
        );
    }

    private function isMainSalesBot(?AiBot $bot): bool
    {
        return $bot instanceof AiBot
            && (int) $bot->id === 39
            && Str::lower(trim((string) $bot->business_sector)) === 'saas'
            && Str::lower(trim((string) $bot->role)) === 'sales';
    }

    private function isPriceQuestion(string $text): bool
    {
        $text = Str::lower(trim($text));

        foreach (['fiyat', 'ücret', 'kaç para', 'ne kadar', 'paket fiyat', 'aylık ne kadar', 'maliyeti'] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function lastUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if (is_array($message) && ($message['role'] ?? '') === 'user') {
                return (string) ($message['content'] ?? $message['message'] ?? '');
            }
        }

        return '';
    }

    private function demoLinkAlreadyExists(array $messages): bool
    {
        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            if (
                ($message['role'] ?? '') === 'assistant'
                && str_contains((string) ($message['content'] ?? $message['message'] ?? ''), '/demo/lead/')
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEscapedNewlines(string $text): string
    {
        return str_replace(['\\r\\n', '\\n'], ["\n", "\n"], $text);
    }
}
