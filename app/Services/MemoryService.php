<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

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

        $chatMessage = ChatMessage::create([
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

        /*
        |--------------------------------------------------------------------------
        | ASİLKAN ANA WHATSAPP İŞ YÖNLENDİRMESİ
        |--------------------------------------------------------------------------
        |
        | Yalnızca ana WAI hesabında (user_id=1) müşteri mesajlarını WAI veya
        | gayrimenkul akışına sınıflandırır. SaaS müşterilerinin bot davranışı
        | bundan etkilenmez.
        |
        | Sınıflandırma ConversationControl.tags içinde kalıcı tutulur.
        |--------------------------------------------------------------------------
        */

        if (
            $userId === 1
            && $role === 'user'
            && $senderType === 'customer'
        ) {
            try {
                $conversation =
                    ConversationControl::query()
                        ->where('user_id', $userId)
                        ->where('session_id', $sessionId)
                        ->first();

                if ($conversation) {
                    app(BusinessContextRouterService::class)->route(
                        conversation: $conversation,
                        message: $message,
                    );
                }
            } catch (Throwable $exception) {
                Log::warning(
                    'WAI BUSINESS CONTEXT ROUTER FAILED',
                    [
                        'user_id' => $userId,
                        'ai_bot_id' => $aiBotId,
                        'session_id' => $sessionId,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $chatMessage;
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
        $messages = $this
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

        /*
        |--------------------------------------------------------------------------
        | KONUŞMAYA DAHİLİ İŞ BAĞLAMI EKLE
        |--------------------------------------------------------------------------
        |
        | OpenAIService yalnızca user/assistant rollerini kabul ettiği için rota
        | bağlamı son müşteri mesajından hemen önce assistant bağlamı olarak eklenir.
        | Böylece son mesaj müşteri mesajı olarak kalır ve ürün/bilgi aramasında
        | dahili rota metni kullanıcı arama verisine karışmaz.
        |--------------------------------------------------------------------------
        */

        if ($userId === 1) {
            try {
                $conversation =
                    ConversationControl::query()
                        ->where('user_id', $userId)
                        ->where('session_id', $sessionId)
                        ->first();

                if ($conversation) {
                    $routingPrompt = trim(
                        app(BusinessContextRouterService::class)
                            ->promptFor($conversation)
                    );

                    if ($routingPrompt !== '') {
                        $routingMessage = [
                            'role' => 'assistant',
                            'content' => $routingPrompt,
                        ];

                        $insertAt = max(
                            0,
                            count($messages) - 1
                        );

                        array_splice(
                            $messages,
                            $insertAt,
                            0,
                            [$routingMessage]
                        );
                    }
                }
            } catch (Throwable $exception) {
                Log::warning(
                    'WAI BUSINESS CONTEXT PROMPT FAILED',
                    [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'message' => $exception->getMessage(),
                    ]
                );
            }
        }

        return $messages;
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
