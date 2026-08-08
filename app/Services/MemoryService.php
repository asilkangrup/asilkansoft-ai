<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MemoryService
{
    public function yeniOturumId(): string
    {
        return (string) Str::uuid();
    }

    public function mesajKaydet(
        int $userId,
        ?int $aiBotId,
        string $sessionId,
        string $role,
        string $message
    ): ChatMessage {
        return ChatMessage::create([
            'user_id' => $userId,
            'ai_bot_id' => $aiBotId,
            'session_id' => $sessionId,
            'role' => $role,
            'message' => trim($message),
        ]);
    }

    public function gecmisiGetir(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): Collection {
        return ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function openAIMesajlariHazirla(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): array {
        return $this->gecmisiGetir($userId, $sessionId, $limit)
            ->map(fn (ChatMessage $mesaj): array => [
                'role' => $mesaj->role,
                'content' => $mesaj->message,
            ])
            ->all();
    }

    public function sohbetiTemizle(
        int $userId,
        string $sessionId
    ): void {
        ChatMessage::query()
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->delete();
    }
}