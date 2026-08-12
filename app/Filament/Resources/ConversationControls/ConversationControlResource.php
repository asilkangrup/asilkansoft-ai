<?php

namespace App\Filament\Resources\ConversationControls;

use App\Filament\Resources\ConversationControls\Pages\ListConversationControls;
use App\Filament\Resources\ConversationControls\Schemas\ConversationControlForm;
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
    protected static ?string $model = ConversationControl::class;

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
    | Manuel create/edit kullanmıyoruz.
    | Resource yapısının bozulmaması için form şeması bağlı kalabilir.
    |
    */

    public static function form(Schema $schema): Schema
    {
        return ConversationControlForm::configure(
            $schema
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TABLO
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return ConversationControlsTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KULLANICIYA GÖRE KONUŞMALARI SINIRLA
    |--------------------------------------------------------------------------
    |
    | Admin bütün müşterilerin konuşmalarını görebilir.
    | Normal kullanıcı sadece kendi konuşmalarını görür.
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

        if (
            (bool) ($user->is_admin ?? false)
        ) {
            return $query;
        }

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
    |
    | Create ve Edit özellikle kaldırıldı.
    |
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ListConversationControls::route('/'),
        ];
    }
}