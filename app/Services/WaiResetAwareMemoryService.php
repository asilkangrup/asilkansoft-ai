<?php

namespace App\Services;

use Illuminate\Support\Str;

class WaiResetAwareMemoryService extends MemoryService
{
    private const WAI_SALES_USER_ID = 43;
    private const WAI_SALES_BOT_ID = 39;

    public function openAIMesajlariHazirla(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): array {
        $messages = parent::openAIMesajlariHazirla(
            userId: $userId,
            sessionId: $sessionId,
            limit: $limit,
        );

        if (! $this->isWaiSalesSession($userId, $sessionId)) {
            return $messages;
        }

        $lastResetIndex = null;

        foreach ($messages as $index => $message) {
            if (($message['role'] ?? '') !== 'user') {
                continue;
            }

            $content = $this->normalize((string) ($message['content'] ?? ''));

            if (in_array($content, ['başa dön', 'basa don', 'sıfırla', 'sifirla'], true)) {
                $lastResetIndex = $index;
            }
        }

        if ($lastResetIndex === null) {
            return $messages;
        }

        return array_values(array_slice($messages, $lastResetIndex));
    }

    private function isWaiSalesSession(int $userId, string $sessionId): bool
    {
        return $userId === self::WAI_SALES_USER_ID
            && str_starts_with($sessionId, 'whatsapp:'.self::WAI_SALES_BOT_ID.':');
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(trim($text));

        return str_replace(["i̇", "ı̇"], ['i', 'ı'], $text);
    }
}
