<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Ürün Bilgileri')
                    ->description('Yapay zekânın müşterilere sunacağı ürün bilgilerini girin.')
                    ->schema([

                        Select::make('ai_bot_id')
                            ->label('Yapay Zekâ')
                            ->relationship('aiBot', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Ürün Adı')
                            ->placeholder('Örn: Mega Siyah Zeytin')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('category')
                            ->label('Kategori')
                            ->placeholder('Örn: Siyah Zeytin')
                            ->maxLength(255),

                        TextInput::make('price')
                            ->label('Fiyat')
                            ->numeric()
                            ->prefix('₺')
                            ->placeholder('0.00'),

                        Select::make('stock_status')
                            ->label('Stok Durumu')
                            ->options([
                                'in_stock' => 'Stokta',
                                'out_of_stock' => 'Stokta Yok',
                                'pre_order' => 'Ön Sipariş',
                            ])
                            ->default('in_stock')
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Ürün Aktif')
                            ->helperText('Kapalı olduğunda yapay zekâ bu ürünü müşterilere sunmayacak.')
                            ->default(true),

                        Textarea::make('description')
                            ->label('Ürün Açıklaması')
                            ->placeholder(
                                'Ürünün özelliklerini, gramajını, paket bilgisini ve müşteriye söylenmesi gereken diğer bilgileri yazın.'
                            )
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}