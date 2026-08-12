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
    | Manuel konuşma oluşturma / düzenleme kullanmıyoruz.
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
    |
    | Mevcut tablo sınıfımızı koruyoruz.
    | Ana sayfa artık WhatsApp Web görünümü olacak.
    |
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
    | KULLANICI YETKİLENDİRMESİ
    |--------------------------------------------------------------------------
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
        | ADMIN
        |--------------------------------------------------------------------------
        |
        | Eğer is_admin alanı varsa admin bütün konuşmaları görebilir.
        |
        */

        if (
            (bool) ($user->is_admin ?? false)
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
    | Konuşmalar menüsüne basıldığında direkt WhatsApp Web ekranı açılır.
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