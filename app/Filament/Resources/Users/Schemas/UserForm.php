<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Ad Soyad')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-posta')
                    ->email()
                    ->required()
                    ->unique(
                        ignoreRecord: true
                    )
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Şifre')
                    ->password()
                    ->revealable()
                    ->required(
                        fn (string $operation): bool =>
                            $operation === 'create'
                    )
                    ->dehydrated(
                        fn (?string $state): bool =>
                            filled($state)
                    )
                    ->dehydrateStateUsing(
                        fn (string $state): string =>
                            Hash::make($state)
                    )
                    ->helperText(
                        'Düzenleme ekranında şifreyi değiştirmek istemiyorsanız boş bırakın.'
                    )
                    ->maxLength(255),

                Toggle::make('is_admin')
                    ->label('Admin Yetkisi')
                    ->helperText(
                        'Açık olursa bu kullanıcı yönetici yetkilerine sahip olur.'
                    )
                    ->default(false),
            ])
            ->columns(2);
    }
}