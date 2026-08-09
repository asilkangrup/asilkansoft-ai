<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\KurulumMerkezi;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class Register extends BaseRegister
{
    /*
    |--------------------------------------------------------------------------
    | SAYFA BAŞLIĞI
    |--------------------------------------------------------------------------
    */

    public function getHeading(): string
    {
        return 'WhatsApp Yapay Zekânı Ücretsiz Dene';
    }

    /*
    |--------------------------------------------------------------------------
    | ALT BAŞLIK
    |--------------------------------------------------------------------------
    */

    public function getSubheading(): string|HtmlString|null
    {
        return new HtmlString(
            '
            <div style="
                margin-top: 8px;
                text-align: center;
                line-height: 1.7;
                color: #6b7280;
                font-size: 14px;
            ">
                <strong style="color:#111827;">
                    30 WhatsApp AI cevabı ücretsiz.
                </strong>
                <br>
                Kredi kartı gerekmez. Hesabını oluştur, yapay zekânı kur,
                canlı test et ve WhatsApp numaranı bağla.
            </div>
            '
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KAYIT FORMU
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | KAYIT OLAN HERKES MÜŞTERİ
    |--------------------------------------------------------------------------
    */

    protected function handleRegistration(
        array $data
    ): Model {
        $data['is_admin'] = false;

        return $this->getUserModel()::create(
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KAYIT SONRASI
    |--------------------------------------------------------------------------
    */

    protected function getRedirectUrl(): string
    {
        return KurulumMerkezi::getUrl();
    }
}