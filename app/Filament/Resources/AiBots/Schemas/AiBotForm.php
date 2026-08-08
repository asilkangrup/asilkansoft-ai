<?php

namespace App\Filament\Resources\AiBots\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

class AiBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Wizard::make([

                    /*
                    |--------------------------------------------------------------------------
                    | 1 - TEMEL BİLGİLER
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Temel Bilgiler')
                        ->description('Yapay zekânız ve firmanız için temel bilgileri girin.')
                        ->icon('heroicon-o-building-office')
                        ->schema([

                            TextInput::make('name')
                                ->label('Yapay Zekâ Adı')
                                ->placeholder('Örn: Asilkan Satış Asistanı')
                                ->helperText('Panelde göreceğiniz yapay zekâ adıdır.')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('company_name')
                                ->label('Firma Adı')
                                ->placeholder('Örn: AsilkanSoft')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('website')
                                ->label('Web Sitesi')
                                ->placeholder('https://firmaniz.com')
                                ->url()
                                ->maxLength(255),

                            TextInput::make('instagram')
                                ->label('Instagram')
                                ->placeholder('@firmaniz')
                                ->maxLength(255),

                            TextInput::make('whatsapp_number')
                                ->label('WhatsApp Numarası')
                                ->placeholder('905xxxxxxxxx')
                                ->helperText('Ülke koduyla birlikte girin.')
                                ->tel()
                                ->maxLength(30),

                            FileUpload::make('logo_path')
                                ->label('Firma Logosu')
                                ->image()
                                ->directory('ai-bot-logos'),

                            Hidden::make('openai_model')
                                ->default('gpt-5-mini'),
                        ])
                        ->columns(2),

                    /*
                    |--------------------------------------------------------------------------
                    | 2 - YAPAY ZEKA GÖREVİ
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Yapay Zekâ Görevi')
                        ->description('Asistanınızın müşterilere hangi amaçla hizmet edeceğini belirleyin.')
                        ->icon('heroicon-o-cpu-chip')
                        ->schema([

                            Select::make('role')
                                ->label('Yapay Zekâ Rolü')
                                ->options([
                                    'sales' => 'Satış Uzmanı',
                                    'support' => 'Müşteri Temsilcisi',
                                    'technical' => 'Teknik Destek',
                                    'assistant' => 'Sekreter / Asistan',
                                ])
                                ->default('sales')
                                ->required()
                                ->helperText(
                                    'Yapay zekânın konuşma tarzı ve öncelikleri bu role göre şekillenir.'
                                ),
                        ]),

                    /*
                    |--------------------------------------------------------------------------
                    | 3 - FİRMA BİLGİLERİ
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Firma Bilgileri')
                        ->description('Yapay zekânın firmanızı doğru anlatabilmesi için temel bilgileri verin.')
                        ->icon('heroicon-o-information-circle')
                        ->schema([

                            Textarea::make('company_description')
                                ->label('Firma Hakkında')
                                ->placeholder(
                                    'Firmanız ne yapıyor? Hangi ürün veya hizmetleri sunuyorsunuz?'
                                )
                                ->helperText(
                                    'Yapay zekâ müşterilere firmanızı anlatırken bu bilgiyi kullanır.'
                                )
                                ->rows(7)
                                ->required()
                                ->columnSpanFull(),

                            Textarea::make('working_hours')
                                ->label('Çalışma Saatleri')
                                ->placeholder(
                                    'Örn: Pazartesi-Cumartesi 09:00-18:00'
                                )
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),

                    /*
                    |--------------------------------------------------------------------------
                    | 4 - SATIŞ / TESLİMAT
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Satış ve Teslimat')
                        ->description('Müşterilere verilecek ödeme, kargo ve iade bilgilerini tanımlayın.')
                        ->icon('heroicon-o-truck')
                        ->schema([

                            Textarea::make('cargo_information')
                                ->label('Kargo ve Teslimat Bilgileri')
                                ->placeholder(
                                    'Kargo firmaları, teslimat süresi, ücretsiz kargo şartları vb.'
                                )
                                ->rows(6),

                            Textarea::make('payment_information')
                                ->label('Ödeme Bilgileri')
                                ->placeholder(
                                    'Kapıda nakit, kapıda kart, kredi kartı vb.'
                                )
                                ->rows(6),

                            Textarea::make('return_policy')
                                ->label('İade ve Değişim Politikası')
                                ->placeholder(
                                    'İade, değişim ve iptal koşullarınızı yazın.'
                                )
                                ->rows(6)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    /*
                    |--------------------------------------------------------------------------
                    | 5 - YAPAY ZEKA EĞİTİMİ
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Yapay Zekâ Eğitimi')
                        ->description('Asistanın müşterilerle nasıl konuşacağını ve hangi kurallara uyacağını belirleyin.')
                        ->icon('heroicon-o-academic-cap')
                        ->schema([

                            Textarea::make('company_rules')
                                ->label('Özel Firma Kuralları')
                                ->placeholder(
                                    'Örn: Bilmediğin fiyatı uydurma. Kargo süresini kesin söyleme. Müşteriyi siparişe yönlendir.'
                                )
                                ->helperText(
                                    'Yapay zekânın mutlaka uyması gereken işletme kurallarını yazın.'
                                )
                                ->rows(8)
                                ->columnSpanFull(),

                            Textarea::make('system_prompt')
                                ->label('Özel Yapay Zekâ Talimatları')
                                ->placeholder(
                                    'Örn: Samimi ve profesyonel konuş. Önce müşterinin ihtiyacını öğren. Kısa cevaplar ver.'
                                )
                                ->helperText(
                                    'Asistanın konuşma biçimini ve satış yaklaşımını buradan özelleştirebilirsiniz.'
                                )
                                ->rows(10)
                                ->columnSpanFull(),
                        ]),

                    /*
                    |--------------------------------------------------------------------------
                    | 6 - OTOMATİK TAKİP
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Otomatik Takip')
                        ->description('Cevap vermeyen müşterilere otomatik hatırlatma gönderin.')
                        ->icon('heroicon-o-clock')
                        ->schema([

                            Toggle::make('follow_up_enabled')
                                ->label('Otomatik Takip Sistemini Aç')
                                ->helperText(
                                    'Müşteri cevap vermediğinde sistem belirlediğiniz süre sonunda otomatik mesaj gönderir.'
                                )
                                ->default(false)
                                ->live(),

                            Select::make('first_follow_up_minutes')
                                ->label('1. Hatırlatma Ne Zaman Gönderilsin?')
                                ->options([
                                    60 => '1 Saat Sonra',
                                    120 => '2 Saat Sonra',
                                    180 => '3 Saat Sonra',
                                    360 => '6 Saat Sonra',
                                    720 => '12 Saat Sonra',
                                    1440 => '24 Saat Sonra',
                                    2880 => '2 Gün Sonra',
                                    4320 => '3 Gün Sonra',
                                    7200 => '5 Gün Sonra',
                                    10080 => '7 Gün Sonra',
                                ])
                                ->default(1440)
                                ->required()
                                ->visible(
                                    fn ($get): bool =>
                                        (bool) $get('follow_up_enabled')
                                ),

                            Textarea::make('first_follow_up_message')
                                ->label('1. Hatırlatma Mesajı')
                                ->default(
                                    'Merhaba 👋 Daha önce görüştüğümüz ürünle hâlâ ilgileniyor musunuz? Size yardımcı olabilirim.'
                                )
                                ->rows(4)
                                ->columnSpanFull()
                                ->visible(
                                    fn ($get): bool =>
                                        (bool) $get('follow_up_enabled')
                                ),

                            Toggle::make('second_follow_up_enabled')
                                ->label('2. Hatırlatma Mesajı Gönder')
                                ->default(true)
                                ->live()
                                ->visible(
                                    fn ($get): bool =>
                                        (bool) $get('follow_up_enabled')
                                ),

                            Select::make('second_follow_up_minutes')
                                ->label('2. Hatırlatma Ne Zaman Gönderilsin?')
                                ->options([
                                    1440 => '1 Gün Sonra',
                                    2880 => '2 Gün Sonra',
                                    4320 => '3 Gün Sonra',
                                    5760 => '4 Gün Sonra',
                                    7200 => '5 Gün Sonra',
                                    10080 => '7 Gün Sonra',
                                    14400 => '10 Gün Sonra',
                                    20160 => '14 Gün Sonra',
                                ])
                                ->default(4320)
                                ->required()
                                ->visible(
                                    fn ($get): bool =>
                                        (bool) $get('follow_up_enabled')
                                        && (bool) $get('second_follow_up_enabled')
                                ),

                            Textarea::make('second_follow_up_message')
                                ->label('2. ve Son Hatırlatma Mesajı')
                                ->default(
                                    'Merhaba 👋 Daha önce görüştüğümüz ürünle ilgili yardımcı olabileceğimiz bir konu var mı? Dilerseniz siparişinizi birlikte oluşturabiliriz.'
                                )
                                ->rows(4)
                                ->columnSpanFull()
                                ->visible(
                                    fn ($get): bool =>
                                        (bool) $get('follow_up_enabled')
                                        && (bool) $get('second_follow_up_enabled')
                                ),
                        ])
                        ->columns(2),

                    /*
                    |--------------------------------------------------------------------------
                    | 7 - SON KONTROL
                    |--------------------------------------------------------------------------
                    */

                    Step::make('Kurulumu Tamamla')
                        ->description('Bilgilerinizi kaydedin ve yapay zekânızı kullanıma hazırlayın.')
                        ->icon('heroicon-o-check-circle')
                        ->schema([

                            Toggle::make('status')
                                ->label('Yapay Zekâyı Aktif Et')
                                ->helperText(
                                    'Açık bırakırsanız yapay zekânız aktif olarak kaydedilir.'
                                )
                                ->default(true),
                        ]),
                ])
                    ->columnSpanFull(),
            ]);
    }
}