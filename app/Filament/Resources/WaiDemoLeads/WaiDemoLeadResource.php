<?php

namespace App\Filament\Resources\WaiDemoLeads;

use App\Filament\Resources\WaiDemoLeads\Pages\ListWaiDemoLeads;
use App\Models\WaiDemoLead;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WaiDemoLeadResource extends Resource
{
    protected static ?string $model = WaiDemoLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $navigationLabel = 'Demo Takip';

    protected static ?string $modelLabel = 'Demo';

    protected static ?string $pluralModelLabel = 'Demo Takip';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user
            && ((bool) $user->is_admin || (int) $user->id === 43);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return null;
        }

        return (string) WaiDemoLead::query()->count();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('test_message_count')
                    ->label('Test Mesajı')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('first_opened_at')
                    ->label('Test Açıldı')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Açılmadı')
                    ->sortable(),

                TextColumn::make('whatsapp_instance')
                    ->label('WhatsApp')
                    ->badge()
                    ->formatStateUsing(
                        fn ($state, WaiDemoLead $record): string =>
                            $record->whatsapp_connected_at
                                ? 'Bağlandı'
                                : ($record->whatsapp_connect_started_at ? 'QR Açıldı' : 'Bağlanmadı')
                    )
                    ->color(
                        fn ($state, WaiDemoLead $record): string =>
                            $record->whatsapp_connected_at
                                ? 'success'
                                : ($record->whatsapp_connect_started_at ? 'warning' : 'gray')
                    ),

                TextColumn::make('whatsapp_connected_at')
                    ->label('Bağlanma Zamanı')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('temporary_bot_id')
                    ->label('Demo Bot')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Oluşturuldu')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaiDemoLeads::route('/'),
        ];
    }
}
