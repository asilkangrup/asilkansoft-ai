<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Sipariş Durumu')
                    ->description('Siparişin mevcut aşamasını buradan yönetin.')
                    ->schema([

                        Select::make('status')
                            ->label('Sipariş Durumu')
                            ->options([
                                'draft' => 'Bilgiler Toplanıyor',
                                'pending' => 'Sipariş Alındı',
                                'confirmed' => 'Onaylandı',
                                'preparing' => 'Hazırlanıyor',
                                'shipped' => 'Kargoya Verildi',
                                'delivered' => 'Teslim Edildi',
                                'cancelled' => 'İptal Edildi',
                            ])
                            ->default('draft')
                            ->required(),

                        Select::make('ai_bot_id')
                            ->label('Yapay Zekâ')
                            ->relationship('aiBot', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Sipariş Bilgileri')
                    ->description('Sipariş edilen ürün ve tutar bilgileri.')
                    ->schema([

                        Textarea::make('products')
                            ->label('Ürünler')
                            ->placeholder('Örn: 5 kg Mega Siyah Zeytin')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('quantity')
                            ->label('Miktar')
                            ->placeholder('Örn: 5 kg'),

                        TextInput::make('total_amount')
                            ->label('Toplam Tutar')
                            ->numeric()
                            ->prefix('₺')
                            ->placeholder('0,00'),
                    ])
                    ->columns(2),

                Section::make('Müşteri Bilgileri')
                    ->description('WhatsApp üzerinden alınan müşteri bilgileri.')
                    ->schema([

                        TextInput::make('customer_name')
                            ->label('Ad Soyad')
                            ->maxLength(255),

                        TextInput::make('whatsapp_number')
                            ->label('WhatsApp Numarası')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('city')
                            ->label('İl')
                            ->maxLength(255),

                        TextInput::make('district')
                            ->label('İlçe')
                            ->maxLength(255),

                        Textarea::make('address')
                            ->label('Teslimat Adresi')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('payment_method')
                            ->label('Ödeme Yöntemi')
                            ->options([
                                'cash_on_delivery' => 'Kapıda Nakit',
                                'card_on_delivery' => 'Kapıda Kart',
                                'bank_transfer' => 'Havale / EFT',
                                'credit_card' => 'Kredi Kartı',
                                'other' => 'Diğer',
                            ]),
                    ])
                    ->columns(2),

                Section::make('Kargo Bilgileri')
                    ->description('Sipariş kargoya verildiğinde bu alanları doldurabilirsiniz.')
                    ->schema([

                        TextInput::make('shipping_company')
                            ->label('Kargo Firması')
                            ->placeholder('Örn: Aras Kargo'),

                        TextInput::make('tracking_number')
                            ->label('Takip Numarası')
                            ->placeholder('Kargo takip numarası'),

                        TextInput::make('tracking_url')
                            ->label('Takip Linki')
                            ->url()
                            ->placeholder('https://...'),
                    ])
                    ->columns(2),

                Section::make('Notlar')
                    ->schema([

                        Textarea::make('customer_note')
                            ->label('Müşteri Notu')
                            ->placeholder('Müşterinin siparişle ilgili özel notu.')
                            ->rows(3),

                        Textarea::make('internal_note')
                            ->label('Firma İçi Not')
                            ->placeholder('Bu alan müşteriye gösterilmez.')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Sistem Bilgileri')
                    ->description('Bu alan sistem tarafından otomatik kullanılır.')
                    ->schema([

                        TextInput::make('session_id')
                            ->label('WhatsApp Oturum ID')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->collapsed(),
            ]);
    }
}