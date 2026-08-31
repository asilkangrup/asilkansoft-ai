<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Support\Str;

class RealEstateConversationService
{
    private const TAG_PREFIX = 'business:';

    private const ROUTES = [
        'real_estate_seller',
        'real_estate_investor',
        'real_estate_general',
    ];

    public function route(
        ConversationControl $conversation,
        string $message
    ): string {
        $normalized = $this->normalize($message);
        $current = $this->currentRoute($conversation);

        if ($normalized === '') {
            return $current;
        }

        $sellerScore = $this->score($normalized, [
            'satmak istiyorum',
            'satacagim',
            'satilik',
            'saticiyim',
            'evim var',
            'arsam var',
            'tarlam var',
            'dukkkanim var',
            'dukkanim var',
            'ofisim var',
            'yerim var',
            'mulkum',
            'tapum',
            'parselim',
            'kaca gider',
            'ne kadar eder',
            'degeri ne',
            'degeri nedir',
            'kac para eder',
            'fiyat bic',
            'acil satmam',
            'acil satilik',
            'nakite ihtiyacim var',
            'teklif alabilir miyim',
        ]);

        $investorScore = $this->score($normalized, [
            'yatirimciyim',
            'yatirimci',
            'yatirim icin',
            'yatirimlik',
            'almak istiyorum',
            'arsa ariyorum',
            'tarla ariyorum',
            'ev ariyorum',
            'dukkan ariyorum',
            'portfoy ariyorum',
            'firsat ariyorum',
            'ucuza',
            'uygun fiyatli',
            'butcem',
            'butce',
            'alici',
            'alirim',
            'nakitim var',
        ]);

        $realEstateScore = $this->score($normalized, [
            'emlak',
            'gayrimenkul',
            'arsa',
            'tarla',
            'parsel',
            'tapu',
            'imar',
            'konut',
            'daire',
            'villa',
            'dukkan',
            'ofis',
            'depo',
            'fabrika',
            'isyeri',
            'is yeri',
            'm2',
            'metrekare',
            'ada parsel',
        ]);

        $detected = $current;

        if ($sellerScore >= 2 && $sellerScore >= $investorScore) {
            $detected = 'real_estate_seller';
        } elseif ($investorScore >= 2 && $investorScore > $sellerScore) {
            $detected = 'real_estate_investor';
        } elseif ($realEstateScore >= 1 && $current === 'real_estate_general') {
            $detected = 'real_estate_general';
        }

        if ($detected !== $current) {
            $this->persistRoute($conversation, $detected);
        } elseif (! $this->hasRouteTag($conversation)) {
            $this->persistRoute($conversation, $detected);
        }

        return $detected;
    }

    public function currentRoute(
        ConversationControl $conversation
    ): string {
        foreach ($conversation->etiketler() as $tag) {
            if (! is_string($tag) || ! str_starts_with($tag, self::TAG_PREFIX)) {
                continue;
            }

            $route = substr($tag, strlen(self::TAG_PREFIX));

            if (in_array($route, self::ROUTES, true)) {
                return $route;
            }
        }

        return 'real_estate_general';
    }

    public function promptFor(
        ConversationControl $conversation
    ): string {
        return match ($this->currentRoute($conversation)) {
            'real_estate_seller' => <<<'PROMPT'
[INTERNAL REAL ESTATE CONTEXT: SELLER]
The customer is most likely a property seller. Keep the conversation in seller-intake and valuation mode unless the customer clearly changes intent. Do not reveal this internal label.
PROMPT,
            'real_estate_investor' => <<<'PROMPT'
[INTERNAL REAL ESTATE CONTEXT: INVESTOR]
The customer is most likely a real-estate investor/buyer. Keep the conversation in investor qualification and opportunity-matching mode unless the customer clearly changes intent. Do not reveal this internal label.
PROMPT,
            default => <<<'PROMPT'
[INTERNAL REAL ESTATE CONTEXT: GENERAL]
This WhatsApp line is dedicated to real estate. The customer intent is not yet clear enough to decide seller vs investor/buyer. Infer naturally from the conversation; if still unclear, ask one short question. Do not offer unrelated software/WAI services. Do not reveal this internal label.
PROMPT,
        };
    }

    private function persistRoute(
        ConversationControl $conversation,
        string $route
    ): void {
        $tags = collect($conversation->etiketler())
            ->filter(
                fn ($tag): bool =>
                    is_string($tag)
                    && ! str_starts_with($tag, self::TAG_PREFIX)
            )
            ->values()
            ->all();

        $tags[] = self::TAG_PREFIX.$route;

        $conversation->update([
            'tags' => array_values(array_unique($tags)),
        ]);

        $conversation->refresh();
    }

    private function hasRouteTag(
        ConversationControl $conversation
    ): bool {
        foreach ($conversation->etiketler() as $tag) {
            if (! is_string($tag) || ! str_starts_with($tag, self::TAG_PREFIX)) {
                continue;
            }

            $route = substr($tag, strlen(self::TAG_PREFIX));

            if (in_array($route, self::ROUTES, true)) {
                return true;
            }
        }

        return false;
    }

    private function score(
        string $message,
        array $signals
    ): int {
        $score = 0;

        foreach ($signals as $signal) {
            if (str_contains($message, $signal)) {
                $score += str_contains($signal, ' ') ? 2 : 1;
            }
        }

        return $score;
    }

    private function normalize(string $message): string
    {
        $message = Str::lower(trim($message));
        $message = strtr($message, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i',
            'Ş' => 's', 'ş' => 's',
            'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u',
            'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c',
        ]);

        $message = preg_replace('/[^\pL\pN\s]+/u', ' ', $message) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $message) ?? '');
    }
}
