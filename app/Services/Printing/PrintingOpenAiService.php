<?php

namespace App\Services\Printing;

use RuntimeException;
use Throwable;

final class PrintingOpenAiService
{
    public function __construct(private readonly PrintingConversationService $conversation)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('matbaa.enabled') && filled(config('matbaa.api_key'));
    }

    /**
     * Natural-language refinement layer. Core state collection remains deterministic;
     * this layer can make the final WhatsApp wording more human without being allowed
     * to invent commercial facts.
     */
    public function naturalize(string $draft, array $state, array $recentMessages = []): string
    {
        if (! $this->enabled()) {
            return $draft;
        }

        $apiKey = (string) config('matbaa.api_key');
        if ($apiKey === '') {
            return $draft;
        }

        try {
            $client = \OpenAI::client($apiKey);
            $messages = [
                ['role' => 'system', 'content' => $this->conversation->systemPrompt()],
            ];

            foreach (array_slice($recentMessages, -1 * max(0, (int) config('matbaa.memory_messages', 50))) as $message) {
                if (! is_array($message) || ! isset($message['role'], $message['content'])) {
                    continue;
                }
                if (! in_array($message['role'], ['user', 'assistant'], true)) {
                    continue;
                }
                $messages[] = [
                    'role' => $message['role'],
                    'content' => (string) $message['content'],
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => "Aşağıdaki taslak cevabı daha doğal bir WhatsApp mesajına dönüştür. Yeni ürün bilgisi, fiyat, süre veya teknik özellik UYDURMA. Sorulmaması gereken alanları tekrar sorma. Yalnızca nihai mesajı yaz.\n\nMEVCUT DURUM:\n".
                    json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).
                    "\n\nTASLAK:\n{$draft}",
            ];

            $response = $client->chat()->create([
                'model' => (string) config('matbaa.model', 'gpt-5.6'),
                'messages' => $messages,
            ]);

            $content = trim((string) ($response->choices[0]->message->content ?? ''));

            return $content !== '' ? $content : $draft;
        } catch (Throwable $e) {
            report($e);
            return $draft;
        }
    }

    public function assertConfigured(): void
    {
        if (! filled(config('matbaa.api_key'))) {
            throw new RuntimeException('OPENAI_MATBAA_API_KEY tanımlı değil.');
        }
    }
}
