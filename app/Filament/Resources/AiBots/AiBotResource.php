<?php

namespace App\Filament\Resources\AiBots;

use App\Filament\Resources\AiBots\Pages\CreateAiBot;
use App\Filament\Resources\AiBots\Pages\EditAiBot;
use App\Filament\Resources\AiBots\Pages\ListAiBots;
use App\Filament\Resources\AiBots\Pages\WhatsAppBagla;
use App\Filament\Resources\AiBots\Schemas\AiBotForm;
use App\Filament\Resources\AiBots\Tables\AiBotsTable;
use App\Models\AiBot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AiBotResource extends Resource
{
    protected static ?string $model = AiBot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Yapay Zekalar';

    protected static ?string $modelLabel = 'Yapay Zeka';

    protected static ?string $pluralModelLabel = 'Yapay Zekalar';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AiBotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiBotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiBots::route('/'),
            'create' => CreateAiBot::route('/create'),

            'whatsapp' => WhatsAppBagla::route('/{record}/whatsapp'),

            'edit' => EditAiBot::route('/{record}/edit'),
        ];
    }
}