<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Müşteri Bilgileri')
                    ->description('Panele giriş yapacak müşterinin bilgilerini girin.')
                    ->schema([

                        TextInput::make('name')
                            ->label('Ad Soyad / Firma Yetkilisi')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-posta Adresi')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Şifre')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(
                                fn ($state): string => Hash::make($state)
                            )
                            ->minLength(8)
                            ->helperText(
                                'Yeni müşteride zorunludur. Düzenleme sırasında boş bırakırsanız mevcut şifre değişmez.'
                            ),

                        Toggle::make('is_admin')
                            ->label('Yönetici Yetkisi')
                            ->helperText(
                                'DİKKAT: Açılırsa bu kullanıcı tüm müşterilerin verilerine erişebilir.'
                            )
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }
}