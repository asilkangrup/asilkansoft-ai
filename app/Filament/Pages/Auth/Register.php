<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\TestSohbeti;
use App\Services\DefaultTrialBotService;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Register extends BaseRegister
{
    public function getHeading(): string
    {
        return 'WhatsApp Yapay Zekânı Ücretsiz Dene';
    }

    public function getSubheading(): string|HtmlString|null
    {
        return new HtmlString(
            '\n            <div style="\n                margin-top: 8px;\n                text-align: center;\n                line-height: 1.7;\n                color: #6b7280;\n                font-size: 14px;\n            ">\n                <strong style="color:#111827;">\n                    30 AI cevabı ücretsiz.\n                </strong>\n                <br>\n                Kredi kartı gerekmez. Hesabını oluştur ve hazır demo yapay zekâyı hemen test et.\n            </div>\n            '
        );
    }

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
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $set(
                            'email',
                            Str::lower(trim((string) $state))
                        );
                    })
                    ->dehydrateStateUsing(
                        fn ($state): string =>
                            Str::lower(trim((string) $state))
                    )
                    ->unique(
                        table: 'users',
                        column: 'email'
                    ),

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

    protected function handleRegistration(array $data): Model
    {
        $data['name'] = trim($data['name']);
        $data['email'] = Str::lower(trim($data['email']));
        $data['is_admin'] = false;

        return DB::transaction(function () use ($data): Model {
            /** @var \App\Models\User $user */
            $user = $this->getUserModel()::create($data);

            app(DefaultTrialBotService::class)->provisionFor($user);

            return $user;
        });
    }

    protected function getRedirectUrl(): string
    {
        return TestSohbeti::getUrl();
    }
}
