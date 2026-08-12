<?php

namespace App\Filament\Resources\ConversationControls;

use App\Filament\Resources\ConversationControls\Pages\ConversationInbox;
use App\Filament\Resources\ConversationControls\Tables\ConversationControlsTable;
use App\Models\ConversationControl;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationControlResource extends Resource
{
    protected static ?string $model =
        ConversationControl::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel =
        'Konuşmalar';

    protected static ?string $modelLabel =
        'Konuşma';

    protected static ?string $pluralModelLabel =
        'Konuşmalar';

    protected static ?string $slug =
        'konusmalar';

    protected static ?string $recordTitleAttribute =
        'whatsapp_number';

    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    |
    | Manuel konuşma oluşturma veya düzenleme kullanmıyoruz.
    |
    */

    public static function form(
        Schema $schema
    ): Schema {
        return $schema;
    }

    /*
    |--------------------------------------------------------------------------
    | TABLO
    |--------------------------------------------------------------------------
    */

    public static function table(
        Table $table
    ): Table {
        return ConversationControlsTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KONUŞMA YETKİLENDİRMESİ
    |--------------------------------------------------------------------------
    |
    | Asilkan ana yönetici hesabı bütün müşterilerin konuşmalarını görür.
    |
    | Normal müşteriler yalnızca kendi user_id değerlerine bağlı
    | konuşmaları görebilir.
    |
    */

    public static function getEloquentQuery(): Builder
    {
        $query =
            parent::getEloquentQuery()
                ->with([
                    'aiBot',
                ]);

        $user = auth()->user();

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
        | NORMAL MÜŞTERİ
        |--------------------------------------------------------------------------
        */

        return $query->where(
            'user_id',
            $user->id
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | SAYFALAR
    |--------------------------------------------------------------------------
    |
    | Konuşmalar menüsü doğrudan WhatsApp Web tarzı Inbox ekranını açar.
    |
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ConversationInbox::route('/'),
        ];
    }
}