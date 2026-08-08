<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('aiBot.name')
                    ->label('Yapay Zekâ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Ürün Adı')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('price')
                    ->label('Fiyat')
                    ->formatStateUsing(
                        fn ($state): string =>
                            $state === null
                                ? '-'
                                : '₺' . number_format(
                                    (float) $state,
                                    2,
                                    ',',
                                    '.'
                                )
                    )
                    ->sortable(),

                TextColumn::make('stock_status')
                    ->label('Stok Durumu')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'in_stock' => 'Stokta',
                            'out_of_stock' => 'Stokta Yok',
                            'pre_order' => 'Ön Sipariş',
                            default => $state ?? '-',
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'in_stock' => 'success',
                            'out_of_stock' => 'danger',
                            'pre_order' => 'warning',
                            default => 'gray',
                        }
                    ),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Güncellenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                //
            ])

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

            ->defaultSort('created_at', 'desc');
    }
}