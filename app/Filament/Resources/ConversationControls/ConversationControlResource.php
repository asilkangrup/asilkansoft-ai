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

    /*
    |--------------------------------------------------------------------------
    | MENÜ İKONU
    |--------------------------------------------------------------------------
    |
    | Test Sohbeti ile karışmaması için Gelen Kutusu ikonu kullanıyoruz.
    |
    */

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedInbox;

    /*
    |--------------------------------------------------------------------------
    | MENÜ İSMİ
    |--------------------------------------------------------------------------
    */

    protected static ?string $navigationLabel =
        'Gelen Kutusu';

    protected static ?string $modelLabel =
        'Gelen Kutusu';

    protected static ?string $pluralModelLabel =
        'Gelen Kutusu';

    protected static ?string $slug =
        'gelen-kutusu';

    protected static ?string $recordTitleAttribute =
        'whatsapp_number';

    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
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
    | Ana yönetici bütün müşterilerin konuşmalarını görebilir.
    | Normal kullanıcı yalnızca kendi konuşmalarını görebilir.
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
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | SAYFALAR
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ConversationInbox::route('/'),
        ];
    }
}