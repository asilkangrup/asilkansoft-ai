<?php

namespace App\Services\Printing;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class PrintingAssistantService
{
    public function __construct(
        private readonly PrintingConversationService $conversation,
        private readonly PrintingOpenAiService $openAi,
    ) {
    }

    public function handle(
        string $sessionId,
        string $message,
        array $attachments = [],
        array $recentMessages = [],
    ): array {
        $cacheKey = $this->cacheKey($sessionId);
        $cache = $this->store();
        $state = $cache->get($cacheKey, []);
        if (! is_array($state)) {
            $state = [];
        }

        $attachments = $this->normalizeAttachments($attachments);
        $result = $this->conversation->process($message, $state, $attachments);

        $cache->put($cacheKey, $result['state'], now()->addDays(30));

        $result['reply'] = $this->openAi->naturalize(
            draft: $result['reply'],
            state: $result['state'],
            recentMessages: $recentMessages,
        );

        return $result;
    }

    public function reset(string $sessionId): void
    {
        $this->store()->forget($this->cacheKey($sessionId));
    }

    public function state(string $sessionId): array
    {
        $state = $this->store()->get($this->cacheKey($sessionId), []);
        return is_array($state) ? $state : [];
    }

    public function mergeState(string $sessionId, array $patch): array
    {
        $state = $this->state($sessionId);
        $merged = array_replace_recursive($state, $patch);
        $this->store()->put($this->cacheKey($sessionId), $merged, now()->addDays(30));
        return $merged;
    }

    public function readiness(): array
    {
        return [
            'enabled' => (bool) config('matbaa.enabled'),
            'dedicated_api_key_configured' => filled(config('matbaa.api_key')),
            'model' => (string) config('matbaa.model'),
            'memory_messages' => (int) config('matbaa.memory_messages', 50),
            'debounce_seconds' => (int) config('matbaa.debounce_seconds', 10),
            'max_questions_per_turn' => (int) config('matbaa.max_questions_per_turn', 2),
            'state_store' => 'database',
            'ready_for_live_traffic' => (bool) config('matbaa.enabled') && filled(config('matbaa.api_key')),
        ];
    }

    private function normalizeAttachments(array $attachments): array
    {
        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png',
        ];

        return array_values(array_filter(array_map(
            static function ($attachment) use ($allowedMimes): ?array {
                if (! is_array($attachment)) {
                    return null;
                }

                $mime = mb_strtolower(trim((string) ($attachment['mime'] ?? $attachment['mimetype'] ?? '')));
                $name = trim((string) ($attachment['name'] ?? $attachment['filename'] ?? ''));

                if (! in_array($mime, $allowedMimes, true)) {
                    return null;
                }

                return array_filter([
                    'name' => $name !== '' ? $name : 'tasarim',
                    'mime' => $mime,
                    'url' => $attachment['url'] ?? null,
                    'role' => $attachment['role'] ?? null,
                ], static fn ($value) => $value !== null && $value !== '');
            },
            $attachments
        )));
    }

    private function cacheKey(string $sessionId): string
    {
        return 'matbaa_ai:conversation:'.sha1($sessionId);
    }

    private function store(): Repository
    {
        return Cache::store('database');
    }
}
