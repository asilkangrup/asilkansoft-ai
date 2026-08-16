<?php

namespace App\Filament\Resources\ConversationControls\Pages;

use App\Filament\Resources\ConversationControls\ConversationControlResource;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Services\WhatsAppService;
use App\Services\OrganizationAccessService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConversationInbox extends Page
{
    use WithFileUploads;
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

    public string $filter = 'all';
    public string $filterTag = '';
    public string $salesFilter = 'all';

    public $mediaUpload = null;
    public string $mediaCaption = '';
    public string $mediaType = '';

    /*
    |--------------------------------------------------------------------------
    | YENİ ETİKET
    |--------------------------------------------------------------------------
    */

    public string $newTag = '';

    /*
    |--------------------------------------------------------------------------
    | SAYFA BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function getTitle(): string
    {
        return 'Gelen Kutusu';
    }

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function currentOrganization()
    {
        return $this
            ->accessService()
            ->currentOrganization();
    }

    protected function currentRole(): ?string
    {
        return $this
            ->accessService()
            ->currentRole();
    }

    protected function canWriteInbox(): bool
    {
        return $this
            ->accessService()
            ->canWriteCrm();
    }

    protected function canManageAssignments(): bool
    {
        return $this
            ->accessService()
            ->canAssignCustomers();
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

        if (! $firstConversation) {
            return;
        }

        $this->selectedConversationId =
            $firstConversation->id;

        /*
        |--------------------------------------------------------------------------
        | AÇILAN KONUŞMAYI OKUNDU YAP
        |--------------------------------------------------------------------------
        */

        $firstConversation
            ->okunmamisMesajlariSifirla();
    }

    /*
    |--------------------------------------------------------------------------
    | YETKİLİ KONUŞMALAR
    |--------------------------------------------------------------------------
    */

    protected function conversationQuery(): Builder
    {
        $user = auth()->user();

        $query =
            ConversationControl::query()
                ->with([
                    'aiBot',
                    'assignedUser',
                ]);

        if (! $user) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SİSTEM ADMIN
        |--------------------------------------------------------------------------
        */

        if ((bool) ($user->is_admin ?? false)) {
            return $query;
        }

        $organization =
            $this->currentOrganization();

        if (! $organization) {
            return $query->where(
                'user_id',
                $user->id
            );
        }

        $role =
            $this->currentRole();

        $query->where(
            'organization_id',
            $organization->id
        );

        /*
        |--------------------------------------------------------------------------
        | SALES YALNIZCA KENDİ MÜŞTERİLERİNİ GÖRÜR
        |--------------------------------------------------------------------------
        */

        if ($role === 'sales') {
            $query->where(
                'assigned_user_id',
                $user->id
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMA LİSTESİ
    |--------------------------------------------------------------------------
    */

    public function getConversationsProperty(): Collection
    {
        $query = $this->conversationQuery();

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query
                    ->where('whatsapp_number', 'like', '%'.$search.'%')
                    ->orWhere('customer_name', 'like', '%'.$search.'%')
                    ->orWhereHas('aiBot', function (Builder $botQuery) use ($search): void {
                        $botQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('company_name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($this->filter === 'unread') {
            $query->where('unread_count', '>', 0);
        } elseif ($this->filter === 'human') {
            $query->where('human_takeover', true);
        } elseif ($this->filter === 'ai') {
            $query->where('human_takeover', false);
        }

        if ($this->filterTag !== '') {
            $query->whereJsonContains('tags', $this->filterTag);
        }

        match ($this->salesFilter) {
            'hot' => $query->where('lead_temperature', 'hot'),
            'warm' => $query->where('lead_temperature', 'warm'),
            'cold' => $query->where('lead_temperature', 'cold'),
            'new' => $query->where('lead_status', 'new'),
            'contacted' => $query->where('lead_status', 'contacted'),
            'qualified' => $query->where('lead_status', 'qualified'),
            'proposal' => $query->where('lead_status', 'proposal'),
            'won' => $query->where('lead_status', 'won'),
            default => null,
        };

        $conversations = $query
            ->latest('updated_at')
            ->limit(100)
            ->get();

        foreach ($conversations as $conversation) {
            $lastMessage = ChatMessage::query()
                ->when(
                    $conversation->organization_id !== null,
                    fn (Builder $query) =>
                        $query->where(
                            'organization_id',
                            $conversation->organization_id
                        )
                )
                ->where('ai_bot_id', $conversation->ai_bot_id)
                ->where('session_id', $conversation->session_id)
                ->latest('id')
                ->first();

            $conversation->setAttribute(
                'last_message_preview',
                $lastMessage
                    ? match ($lastMessage->message_type ?? 'text') {
                        'image' => '📷 Fotoğraf',
                        'video' => '🎥 Video',
                        'audio' => '🎤 Sesli mesaj',
                        'document' => '📎 '.($lastMessage->media_filename ?: 'Belge'),
                        default => $lastMessage->message ?: 'Mesaj',
                    }
                    : 'Henüz mesaj yok'
            );

            $conversation->setAttribute(
                'last_message_at',
                $lastMessage?->created_at
            );
        }

        return $conversations
            ->sortByDesc(
                function (ConversationControl $conversation): int {
                    $temperature = match ($conversation->lead_temperature) {
                        'hot' => 300,
                        'warm' => 200,
                        default => 100,
                    };

                    $stage = match ($conversation->lead_status) {
                        'proposal' => 80,
                        'qualified' => 70,
                        'contacted' => 60,
                        'new' => 50,
                        'won' => -150,
                        'lost' => -200,
                        default => 40,
                    };

                    $score = min(100, max(0, (int) $conversation->lead_score));
                    $lastAt = $conversation->last_message_at ?? $conversation->updated_at;

                    return
                        (($temperature + $stage + $score) * 10000000000)
                        + (int) ($lastAt?->timestamp ?? 0);
                }
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
            ->when(
                $conversation->organization_id !== null,
                fn (Builder $query) =>
                    $query->where(
                        'organization_id',
                        $conversation->organization_id
                    )
            )
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

        /*
        |--------------------------------------------------------------------------
        | KONUŞMAYI SEÇ
        |--------------------------------------------------------------------------
        */

        $this->selectedConversationId =
            $conversation->id;

        /*
        |--------------------------------------------------------------------------
        | OKUNMAMIŞ MESAJLARI SIFIRLA
        |--------------------------------------------------------------------------
        |
        | Kullanıcı konuşmayı açtığı anda unread_count sıfırlanır.
        |
        */

        $conversation
            ->okunmamisMesajlariSifirla();

        /*
        |--------------------------------------------------------------------------
        | MESAJ KUTUSUNU TEMİZLE
        |--------------------------------------------------------------------------
        */

        $this->messageText = '';
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKET EKLE
    |--------------------------------------------------------------------------
    */

    public function setFilter(string $filter): void
    {
        $allowed = ['all', 'unread', 'human', 'ai'];

        $this->filter = in_array($filter, $allowed, true)
            ? $filter
            : 'all';

        $this->filterTag = '';
        $this->salesFilter = 'all';
    }

    public function setTagFilter(string $tag): void
    {
        $this->filterTag = trim($tag);
        $this->filter = 'all';
        $this->salesFilter = 'all';
    }

    public function setSalesFilter(string $filter): void
    {
        $allowed = [
            'all', 'hot', 'warm', 'cold',
            'new', 'contacted', 'qualified', 'proposal', 'won',
        ];

        $this->salesFilter =
            in_array($filter, $allowed, true)
                ? $filter
                : 'all';

        $this->filter = 'all';
        $this->filterTag = '';
    }

    public function addTag(): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $tag = trim($this->newTag);

        if ($tag === '') {
            return;
        }

        if (mb_strlen($tag) > 40) {
            Notification::make()
                ->title('Etiket çok uzun.')
                ->body('Etiket en fazla 40 karakter olabilir.')
                ->warning()
                ->send();

            return;
        }

        $conversation->etiketEkle($tag);

        $this->newTag = '';

        Notification::make()
            ->title('Etiket eklendi.')
            ->body('“'.$tag.'” etiketi konuşmaya eklendi.')
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | ETİKET SİL
    |--------------------------------------------------------------------------
    */

    public function removeTag(string $tag): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $conversation->etiketSil($tag);

        Notification::make()
            ->title('Etiket kaldırıldı.')
            ->body('“'.$tag.'” etiketi konuşmadan kaldırıldı.')
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | HAZIR ETİKET EKLE
    |--------------------------------------------------------------------------
    */

    public function addTagFromPreset(string $tag): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $tag = trim($tag);

        if ($tag === '') {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $conversation->etiketEkle($tag);
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRAL
    |--------------------------------------------------------------------------
    */

    public function takeOver(): void
    {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

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
        if (! $this->canWriteInbox()) {
            abort(403);
        }

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
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $conversation = $this->selectedConversation;

        if (! $conversation) {
            return;
        }

        $text = trim($this->messageText);

        if ($text === '') {
            return;
        }

        $aiBot = $conversation->aiBot;

        if (! $aiBot || ! is_string($aiBot->whatsapp_instance) || trim($aiBot->whatsapp_instance) === '') {
            Notification::make()
                ->title('WhatsApp bağlantısı bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        try {
            if (! $conversation->human_takeover) {
                $conversation->insanDevral();
            }

            $result = $whatsAppService->sendText(
                instanceName: trim($aiBot->whatsapp_instance),
                number: $conversation->whatsapp_number,
                text: $text,
            );

            ChatMessage::create([
                'user_id' => $conversation->user_id,
                'ai_bot_id' => $conversation->ai_bot_id,
                'session_id' => $conversation->session_id,
                'role' => 'assistant',
                'sender_type' => 'human',
                'sent_by_user_id' => auth()->id(),
                'message' => $text,
                'message_type' => 'text',
                'whatsapp_message_id' => data_get($result, 'key.id')
                    ?? data_get($result, 'messageId')
                    ?? data_get($result, 'id'),
                'status' => 'sent',
            ]);

            $conversation->touch();
            $this->messageText = '';
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Mesaj gönderilemedi.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearMedia(): void
    {
        $this->mediaUpload = null;
        $this->mediaCaption = '';
        $this->mediaType = '';
    }

    public function sendMediaMessage(
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $conversation = $this->selectedConversation;

        if (! $conversation || ! $this->mediaUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $aiBot = $conversation->aiBot;

        if (! $aiBot || ! is_string($aiBot->whatsapp_instance) || trim($aiBot->whatsapp_instance) === '') {
            Notification::make()
                ->title('WhatsApp bağlantısı bulunamadı.')
                ->danger()
                ->send();

            return;
        }

        try {
            if (! $conversation->human_takeover) {
                $conversation->insanDevral();
            }

            $file = $this->mediaUpload;
            $mime = (string) $file->getMimeType();
            $size = (int) $file->getSize();
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: 'bin';

            $type = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                default => 'document',
            };

            $path = $file->storeAs(
                'whatsapp-media',
                uniqid('wai_', true).'.'.$extension,
                'public'
            );

            $url = Storage::disk('public')->url($path);
            $caption = trim($this->mediaCaption);

            $result = match ($type) {
                'image' => $whatsAppService->sendImage(
                    trim($aiBot->whatsapp_instance),
                    $conversation->whatsapp_number,
                    $url,
                    $originalName,
                    $caption,
                    $mime
                ),
                'video' => $whatsAppService->sendVideo(
                    trim($aiBot->whatsapp_instance),
                    $conversation->whatsapp_number,
                    $url,
                    $originalName,
                    $caption,
                    $mime
                ),
                default => $whatsAppService->sendDocument(
                    trim($aiBot->whatsapp_instance),
                    $conversation->whatsapp_number,
                    $url,
                    $originalName,
                    $caption,
                    $mime
                ),
            };

            ChatMessage::create([
                'user_id' => $conversation->user_id,
                'ai_bot_id' => $conversation->ai_bot_id,
                'session_id' => $conversation->session_id,
                'role' => 'assistant',
                'sender_type' => 'human',
                'sent_by_user_id' => auth()->id(),
                'message' => $caption !== '' ? $caption : '',
                'message_type' => $type,
                'media_url' => $url,
                'media_mime_type' => $mime,
                'media_filename' => $originalName,
                'media_caption' => $caption !== '' ? $caption : null,
                'media_size' => $size,
                'whatsapp_message_id' => data_get($result, 'key.id')
                    ?? data_get($result, 'messageId')
                    ?? data_get($result, 'id'),
                'status' => 'sent',
            ]);

            $conversation->touch();
            $this->clearMedia();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Medya gönderilemedi.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function sendRecordedAudio(
        string $base64Audio,
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canWriteInbox()) {
            abort(403);
        }

        $conversation = $this->selectedConversation;

        if (! $conversation || trim($base64Audio) === '') {
            return;
        }

        $aiBot = $conversation->aiBot;

        if (! $aiBot || ! is_string($aiBot->whatsapp_instance) || trim($aiBot->whatsapp_instance) === '') {
            return;
        }

        try {
            if (! $conversation->human_takeover) {
                $conversation->insanDevral();
            }

            $result = $whatsAppService->sendAudio(
                trim($aiBot->whatsapp_instance),
                $conversation->whatsapp_number,
                $base64Audio
            );

            ChatMessage::create([
                'user_id' => $conversation->user_id,
                'ai_bot_id' => $conversation->ai_bot_id,
                'session_id' => $conversation->session_id,
                'role' => 'assistant',
                'sender_type' => 'human',
                'sent_by_user_id' => auth()->id(),
                'message' => '',
                'message_type' => 'audio',
                'media_mime_type' => 'audio/webm',
                'whatsapp_message_id' => data_get($result, 'key.id')
                    ?? data_get($result, 'messageId')
                    ?? data_get($result, 'id'),
                'status' => 'sent',
            ]);

            $conversation->touch();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Ses gönderilemedi.')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

}