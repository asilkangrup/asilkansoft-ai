<?php

namespace App\Filament\Resources\ConversationControls\Pages;

use App\Filament\Resources\ConversationControls\ConversationControlResource;
use Filament\Resources\Pages\ListRecords;

class ListConversationControls extends ListRecords
{
    protected static string $resource =
        ConversationControlResource::class;

    /*
    |--------------------------------------------------------------------------
    | SAYFA BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function getTitle(): string
    {
        return 'Konuşmalar';
    }

    /*
    |--------------------------------------------------------------------------
    | HEADER ACTIONS
    |--------------------------------------------------------------------------
    |
    | Manuel konuşma oluşturulamaz.
    | Konuşmalar WhatsApp mesajlarından otomatik oluşacak.
    |
    */

    protected function getHeaderActions(): array
    {
        return [];
    }
}