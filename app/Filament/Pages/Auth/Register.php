<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\KurulumMerkezi;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Ad Soyad')
                    ->placeholder('Adınızı ve soyadınızı yazın')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),

                TextInput::make('email')
                    ->label('E-posta Adresi')
                    ->placeholder('ornek@firma.com')
                    ->email()
                    ->required()
                    ->unique(
                        table: 'users',
                        column: 'email'
                    )
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Şifre')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->same('passwordConfirmation')
                    ->helperText(
                        'En az 8 karakterlik güçlü bir şifre belirleyin.'
                    ),

                TextInput::make('passwordConfirmation')
                    ->label('Şifre Tekrar')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->dehydrated(false),
            ]);
    }

    protected function handleRegistration(
        array $data
    ): Model {
        /*
        |--------------------------------------------------------------------------
        | KAYIT OLAN HERKES MÜŞTERİ
        |--------------------------------------------------------------------------
        */

        $data['is_admin'] = false;

        return $this->getUserModel()::create(
            $data
        );
    }

    protected function getRedirectUrl(): string
    {
        return KurulumMerkezi::getUrl();
    }
}