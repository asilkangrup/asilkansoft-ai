<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
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
    | user      = OpenAI tarafÄ±nda kullanÄ±cÄ± mesajÄ±
    | assistant = OpenAI tarafÄ±nda asistan mesajÄ±
    |
    | senderType:
    | customer  = WhatsApp mÃ¼ÅŸterisi
    | ai        = Yapay zekÃ¢
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
        | SENDER TYPE OTOMATÄ°K BELÄ°RLE
        |--------------------------------------------------------------------------
        |
        | Eski kodlardan senderType gÃ¶nderilmezse:
        |
        | user      => customer
        | assistant => ai
        |
        | BÃ¶ylece mevcut sistem bozulmadan Ã§alÄ±ÅŸmaya devam eder.
        |
        */

        if ($senderType === null) {
            $senderType =
                $role === 'assistant'
                    ? 'ai'
                    : 'customer';
        }

        $organizationId =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'session_id',
                    $sessionId
                )
                ->value(
                    'organization_id'
                );

        if ($organizationId === null) {
            $organizationId =
                Organization::query()
                    ->where(
                        'owner_user_id',
                        $userId
                    )
                    ->value(
                        'id'
                    );
        }

        return ChatMessage::create([
            'user_id' =>
                $userId,

            'organization_id' =>
                $organizationId,

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
    | KONUÅMA GEÃ‡MÄ°ÅÄ°
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
    | OPENAI MESAJ FORMATINA Ã‡EVÄ°R
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
    | SOHBETÄ° TEMÄ°ZLE
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