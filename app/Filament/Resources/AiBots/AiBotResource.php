<?php

namespace App\Filament\Resources\AiBots;

use App\Filament\Resources\AiBots\Pages\CreateAiBot;
use App\Filament\Resources\AiBots\Pages\EditAiBot;
use App\Filament\Resources\AiBots\Pages\ListAiBots;
use App\Filament\Resources\AiBots\Pages\WhatsAppBagla;
use App\Filament\Resources\AiBots\Schemas\AiBotForm;
use App\Filament\Resources\AiBots\Tables\AiBotsTable;
use App\Models\AiBot;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiBotResource extends Resource
{
    protected static ?string $model = AiBot::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Yapay Zekalar';

    protected static ?string $modelLabel = 'Yapay Zeka';

    protected static ?string $pluralModelLabel = 'Yapay Zekalar';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    /*
    |--------------------------------------------------------------------------
    | YETKİ SERVİSİ
    |--------------------------------------------------------------------------
    */

    protected static function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected static function currentRole(): ?string
    {
        return static::accessService()
            ->currentRole();
    }

    protected static function currentOrganization()
    {
        return static::accessService()
            ->currentOrganization();
    }

    /*
    |--------------------------------------------------------------------------
    | RESOURCE ERİŞİMİ
    |--------------------------------------------------------------------------
    |
    | Admin:
    | Tüm botları görebilir ve yönetebilir.
    |
    | Owner / Manager:
    | Kendi organizasyon sahibine ait botları görebilir ve yönetebilir.
    |
    | Sales / Support / Viewer:
    | Yapay zeka resource alanına erişemez.
    |
    */

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return in_array(
            static::currentRole(),
            [
                'owner',
                'manager',
            ],
            true
        );
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny()
            && static::recordIsAccessible($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /*
    |--------------------------------------------------------------------------
    | RECORD GÜVENLİĞİ
    |--------------------------------------------------------------------------
    */

    protected static function recordIsAccessible(
        Model $record
    ): bool {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $organization =
            static::currentOrganization();

        if (! $organization) {
            return false;
        }

        return (int) $record->getAttribute('user_id')
            ===
            (int) $organization->owner_user_id;
    }

    /*
    |--------------------------------------------------------------------------
    | ORGANİZASYON İZOLASYONU
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Filament::auth()->user();

        if (! $user) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        if ($user->is_admin) {
            return $query;
        }

        if (
            ! in_array(
                static::currentRole(),
                [
                    'owner',
                    'manager',
                ],
                true
            )
        ) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        $organization =
            static::currentOrganization();

        if (! $organization) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        return $query->where(
            'user_id',
            $organization->owner_user_id
        );
    }

    public static function form(Schema $schema): Schema
    {
        return AiBotForm::configure(
            $schema
        );
    }

    public static function table(Table $table): Table
    {
        return AiBotsTable::configure(
            $table
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListAiBots::route('/'),

            'create' =>
                CreateAiBot::route('/create'),

            'whatsapp' =>
                WhatsAppBagla::route(
                    '/{record}/whatsapp'
                ),

            'edit' =>
                EditAiBot::route(
                    '/{record}/edit'
                ),
        ];
    }
}