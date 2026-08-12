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

    /*
    |--------------------------------------------------------------------------
    | MESAJ KAYDET
    |--------------------------------------------------------------------------
    |
    | role:
    | user      = OpenAI tarafında kullanıcı mesajı
    | assistant = OpenAI tarafında asistan mesajı
    |
    | senderType:
    | customer  = WhatsApp müşterisi
    | ai        = Yapay zekâ
    | human     = Paneldeki personel
    |
    */

    public function mesajKaydet(
        int $userId,
        ?int $aiBotId,
        string $sessionId,
        string $role,
        string $message,
        ?string $senderType = null,
        ?int $sentByUserId = null
    ): ChatMessage {
        /*
        |--------------------------------------------------------------------------
        | SENDER TYPE OTOMATİK BELİRLE
        |--------------------------------------------------------------------------
        |
        | Eski kodlardan senderType gönderilmezse:
        |
        | user      => customer
        | assistant => ai
        |
        | Böylece mevcut sistem bozulmadan çalışmaya devam eder.
        |
        */

        if ($senderType === null) {
            $senderType =
                $role === 'assistant'
                    ? 'ai'
                    : 'customer';
        }

        return ChatMessage::create([
            'user_id' =>
                $userId,

            'ai_bot_id' =>
                $aiBotId,

            'session_id' =>
                $sessionId,

            'role' =>
                $role,

            'sender_type' =>
                $senderType,

            'sent_by_user_id' =>
                $sentByUserId,

            'message' =>
                trim($message),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMA GEÇMİŞİ
    |--------------------------------------------------------------------------
    */

    public function gecmisiGetir(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): Collection {
        return ChatMessage::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'session_id',
                $sessionId
            )
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | OPENAI MESAJ FORMATINA ÇEVİR
    |--------------------------------------------------------------------------
    */

    public function openAIMesajlariHazirla(
        int $userId,
        string $sessionId,
        int $limit = 20
    ): array {
        return $this
            ->gecmisiGetir(
                $userId,
                $sessionId,
                $limit
            )
            ->map(
                fn (
                    ChatMessage $mesaj
                ): array => [
                    'role' =>
                        $mesaj->role,

                    'content' =>
                        $mesaj->message,
                ]
            )
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | SOHBETİ TEMİZLE
    |--------------------------------------------------------------------------
    */

    public function sohbetiTemizle(
        int $userId,
        string $sessionId
    ): void {
        ChatMessage::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'session_id',
                $sessionId
            )
            ->delete();
    }
}