<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Müşteriler';

    protected static ?string $modelLabel = 'Müşteri';

    protected static ?string $pluralModelLabel = 'Müşteriler';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 50;

    /*
    |--------------------------------------------------------------------------
    | SADECE ADMIN MENÜDE GÖRSÜN
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    /*
    |--------------------------------------------------------------------------
    | SADECE ADMIN RESOURCE'A ERİŞSİN
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    public static function canCreate(): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    public static function canEdit($record): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    public static function canDelete($record): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    public static function canDeleteAny(): bool
    {
        $user = Filament::auth()->user();

        return $user
            ? (bool) $user->is_admin
            : false;
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN İSTERSE TÜM KULLANICILARI GÖRÜR
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    /*
    |--------------------------------------------------------------------------
    | TABLO
    |--------------------------------------------------------------------------
    */

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}