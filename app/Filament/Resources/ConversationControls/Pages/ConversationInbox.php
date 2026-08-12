<?php

namespace App\Filament\Resources\ConversationControls\Pages;

use App\Filament\Resources\ConversationControls\ConversationControlResource;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class ConversationInbox extends Page
{
    protected static string $resource =
        ConversationControlResource::class;

    protected string $view =
        'filament.resources.conversation-controls.pages.conversation-inbox';

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ KONUŞMA
    |--------------------------------------------------------------------------
    */

    public ?int $selectedConversationId = null;

    /*
    |--------------------------------------------------------------------------
    | MESAJ KUTUSU
    |--------------------------------------------------------------------------
    */

    public string $messageText = '';

    /*
    |--------------------------------------------------------------------------
    | ARAMA
    |--------------------------------------------------------------------------
    */

    public string $search = '';

    /*
    |--------------------------------------------------------------------------
    | SAYFA BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function getTitle(): string
    {
        return 'Gelen Kutusu';
    }

    /*
    |--------------------------------------------------------------------------
    | SAYFA AÇILIŞI
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $firstConversation =
            $this->conversationQuery()
                ->latest('updated_at')
                ->first();

        if ($firstConversation) {
            $this->selectedConversationId =
                $firstConversation->id;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | YETKİLİ KONUŞMALAR
    |--------------------------------------------------------------------------
    |
    | Ana yönetici bütün müşterileri görür.
    | Normal müşteriler sadece kendi konuşmalarını görür.
    |
    */

    protected function conversationQuery(): Builder
    {
        $user = auth()->user();

        $query =
            ConversationControl::query()
                ->with([
                    'aiBot',
                ]);

        if (! $user) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ANA YÖNETİCİ
        |--------------------------------------------------------------------------
        */

        if (
            (int) $user->id === 1
            || (string) $user->email === 'asilkangrup@gmail.com'
            || (bool) ($user->is_admin ?? false)
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | NORMAL KULLANICI
        |--------------------------------------------------------------------------
        */

        return $query->where(
            'user_id',
            $user->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMA LİSTESİ
    |--------------------------------------------------------------------------
    */

    public function getConversationsProperty(): Collection
    {
        $query =
            $this->conversationQuery();

        $search =
            trim(
                $this->search
            );

        if ($search !== '') {
            $query->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->where(
                            'whatsapp_number',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhereHas(
                            'aiBot',
                            function (Builder $botQuery) use ($search): void {
                                $botQuery
                                    ->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'company_name',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        );
                }
            );
        }

        $conversations =
            $query
                ->latest('updated_at')
                ->limit(100)
                ->get();

        /*
        |--------------------------------------------------------------------------
        | SON MESAJ BİLGİSİ
        |--------------------------------------------------------------------------
        */

        foreach ($conversations as $conversation) {
            $lastMessage =
                ChatMessage::query()
                    ->where(
                        'ai_bot_id',
                        $conversation->ai_bot_id
                    )
                    ->where(
                        'session_id',
                        $conversation->session_id
                    )
                    ->latest('id')
                    ->first();

            $conversation->setAttribute(
                'last_message_preview',
                $lastMessage
                    ? $lastMessage->message
                    : 'Henüz mesaj yok'
            );

            $conversation->setAttribute(
                'last_message_at',
                $lastMessage?->created_at
            );
        }

        return $conversations
            ->sortByDesc(
                fn (
                    ConversationControl $conversation
                ) =>
                    $conversation->last_message_at
                    ?? $conversation->updated_at
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ KONUŞMA
    |--------------------------------------------------------------------------
    */

    public function getSelectedConversationProperty():
        ?ConversationControl
    {
        if (! $this->selectedConversationId) {
            return null;
        }

        return $this
            ->conversationQuery()
            ->whereKey(
                $this->selectedConversationId
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | SEÇİLİ KONUŞMANIN MESAJLARI
    |--------------------------------------------------------------------------
    */

    public function getMessagesProperty(): Collection
    {
        $conversation =
            $this->selectedConversation;

        if (! $conversation) {
            return collect();
        }

        return ChatMessage::query()
            ->with([
                'sentByUser',
            ])
            ->where(
                'ai_bot_id',
                $conversation->ai_bot_id
            )
            ->where(
                'session_id',
                $conversation->session_id
            )
            ->oldest('id')
            ->limit(300)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMA SEÇ
    |--------------------------------------------------------------------------
    */

    public function selectConversation(
        int $conversationId
    ): void {
        $conversation =
            $this
                ->conversationQuery()
                ->whereKey(
                    $conversationId
                )
                ->first();

        if (! $conversation) {
            return;
        }

        $this->selectedConversationId =
            $conversation->id;

        $this->messageText = '';
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRAL
    |--------------------------------------------------------------------------
    */

    public function takeOver(): void
    {
        $conversation =
            $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $conversation->insanDevral();

        Notification::make()
            ->title(
                'Konuşmayı devraldınız.'
            )
            ->body(
                'Yapay zekâ bu müşteriye otomatik cevap vermeyecek.'
            )
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | YAPAY ZEKÂYA GERİ VER
    |--------------------------------------------------------------------------
    */

    public function releaseToAi(): void
    {
        $conversation =
            $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $conversation
            ->yapayZekayaGeriVer();

        Notification::make()
            ->title(
                'Konuşma yapay zekâya geri verildi.'
            )
            ->body(
                'Müşterinin sonraki mesajına yapay zekâ yeniden cevap verebilir.'
            )
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | PANELDEN WHATSAPP MESAJI GÖNDER
    |--------------------------------------------------------------------------
    */

    public function sendMessage(
        WhatsAppService $whatsAppService
    ): void {
        $conversation =
            $this->selectedConversation;

        if (! $conversation) {
            Notification::make()
                ->title(
                    'Konuşma seçilmedi.'
                )
                ->danger()
                ->send();

            return;
        }

        $text =
            trim(
                $this->messageText
            );

        if ($text === '') {
            return;
        }

        $aiBot =
            $conversation->aiBot;

        if (
            ! $aiBot
            || ! is_string(
                $aiBot->whatsapp_instance
            )
            || trim(
                $aiBot->whatsapp_instance
            ) === ''
        ) {
            Notification::make()
                ->title(
                    'WhatsApp bağlantısı bulunamadı.'
                )
                ->danger()
                ->send();

            return;
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | PERSONEL MESAJ YAZARSA AI'Yİ DURDUR
            |--------------------------------------------------------------------------
            */

            if (! $conversation->human_takeover) {
                $conversation->insanDevral();
            }

            /*
            |--------------------------------------------------------------------------
            | WHATSAPP MESAJINI GÖNDER
            |--------------------------------------------------------------------------
            */

            $whatsAppService->sendText(
                instanceName:
                    trim(
                        $aiBot->whatsapp_instance
                    ),

                number:
                    $conversation->whatsapp_number,

                text:
                    $text,
            );

            /*
            |--------------------------------------------------------------------------
            | PANEL MESAJINI VERİTABANINA KAYDET
            |--------------------------------------------------------------------------
            */

            ChatMessage::create([
                'user_id' =>
                    $conversation->user_id,

                'ai_bot_id' =>
                    $conversation->ai_bot_id,

                'session_id' =>
                    $conversation->session_id,

                'role' =>
                    'assistant',

                'sender_type' =>
                    'human',

                'sent_by_user_id' =>
                    auth()->id(),

                'message' =>
                    $text,
            ]);

            /*
            |--------------------------------------------------------------------------
            | SON AKTİVİTE
            |--------------------------------------------------------------------------
            */

            $conversation->touch();

            /*
            |--------------------------------------------------------------------------
            | MESAJ KUTUSUNU TEMİZLE
            |--------------------------------------------------------------------------
            */

            $this->messageText = '';

        } catch (Throwable $exception) {
            report(
                $exception
            );

            Notification::make()
                ->title(
                    'Mesaj gönderilemedi.'
                )
                ->body(
                    $exception->getMessage()
                )
                ->danger()
                ->send();
        }
    }
}