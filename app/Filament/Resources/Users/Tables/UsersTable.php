<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('Ad Soyad')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                BadgeColumn::make('is_admin')
                    ->label('Hesap Türü')
                    ->formatStateUsing(
                        fn (bool $state): string =>
                            $state
                                ? 'Admin'
                                : 'Müşteri'
                    )
                    ->colors([
                        'danger' => true,
                        'success' => false,
                    ]),

                TextColumn::make('ai_bots_count')
                    ->label('Yapay Zekâ Sayısı')
                    ->counts('aiBots')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Kayıt Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])

            ->filters([])

            ->recordActions([
                EditAction::make()
                    ->label('Düzenle'),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Seçilenleri Sil'),
                ]),
            ])

            ->defaultSort(
                'created_at',
                'desc'
            );
    }
}