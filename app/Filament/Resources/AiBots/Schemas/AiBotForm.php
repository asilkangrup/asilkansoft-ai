<?php

namespace App\Filament\Resources\AiBots\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | GENEL BİLGİLER
                |--------------------------------------------------------------------------
                */

                Section::make('Genel Bilgiler')
                    ->description('Yapay zekânın temel bilgilerini girin.')
                    ->schema([

                        TextInput::make('name')
                            ->label('Yapay Zekâ Adı')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company_name')
                            ->label('Firma Adı')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('website')
                            ->label('Web Sitesi')
                            ->url()
                            ->maxLength(255),

                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->maxLength(255),

                        TextInput::make('whatsapp_number')
                            ->label('WhatsApp Numarası')
                            ->tel()
                            ->maxLength(30),

                        FileUpload::make('logo_path')
                            ->label('Firma Logosu')
                            ->image()
                            ->directory('ai-bot-logos'),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | YAPAY ZEKA
                |--------------------------------------------------------------------------
                */

                Section::make('Yapay Zekâ')
                    ->description('Yapay zekânın görevini belirleyin.')
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
                            ->required(),

                        Select::make('openai_model')
                            ->label('Yapay Zekâ Modeli')
                            ->options([
                                'gpt-5-mini' => 'GPT-5 Mini',
                                'gpt-5' => 'GPT-5',
                            ])
                            ->default('gpt-5-mini')
                            ->required(),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | OTOMATİK TAKİP MESAJLARI
                |--------------------------------------------------------------------------
                */

                Section::make('Otomatik Takip Mesajları')
                    ->description(
                        'Müşteri cevap vermediğinde otomatik hatırlatma mesajları gönderin.'
                    )
                    ->schema([

                        Toggle::make('follow_up_enabled')
                            ->label('Otomatik Takip Sistemi')
                            ->helperText(
                                'Açık olduğunda müşteri cevap vermezse belirlediğiniz sürelerde otomatik mesaj gönderilir.'
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
                            ->helperText(
                                'Müşteri belirlenen süre boyunca cevap vermezse bu mesaj gönderilir.'
                            )
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('follow_up_enabled')
                            ),

                        Toggle::make('second_follow_up_enabled')
                            ->label('2. Hatırlatma Mesajı Gönder')
                            ->helperText(
                                'İlk hatırlatmadan sonra müşteri yine cevap vermezse ikinci ve son mesaj gönderilir.'
                            )
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
                            ->helperText(
                                'Bu mesaj ikinci ve son otomatik hatırlatma mesajıdır.'
                            )
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('follow_up_enabled')
                                    && (bool) $get('second_follow_up_enabled')
                            ),
                    ])
                    ->columns(2)
                    ->collapsed(),

                /*
                |--------------------------------------------------------------------------
                | FİRMA BİLGİLERİ / EĞİTİM
                |--------------------------------------------------------------------------
                */

                Section::make('Firma Bilgileri ve Yapay Zekâ Eğitimi')
                    ->description(
                        'Yapay zekânın müşterilere doğru cevap verebilmesi için firma bilgilerini girin.'
                    )
                    ->schema([

                        Textarea::make('company_description')
                            ->label('Firma Hakkında')
                            ->helperText(
                                'Firmanın ne yaptığını, ürünlerini ve hizmetlerini anlatın.'
                            )
                            ->rows(6)
                            ->columnSpanFull(),

                        Textarea::make('working_hours')
                            ->label('Çalışma Saatleri')
                            ->helperText(
                                'Örnek: Pazartesi-Cumartesi 09:00-18:00'
                            )
                            ->rows(4),

                        Textarea::make('cargo_information')
                            ->label('Kargo ve Teslimat Bilgileri')
                            ->helperText(
                                'Kargo firmaları, ücretsiz kargo şartları ve teslimat sürelerini yazın.'
                            )
                            ->rows(6),

                        Textarea::make('payment_information')
                            ->label('Ödeme Bilgileri')
                            ->helperText(
                                'Kapıda ödeme, kredi kartı ve diğer ödeme seçeneklerini yazın.'
                            )
                            ->rows(6),

                        Textarea::make('return_policy')
                            ->label('İade ve Değişim Politikası')
                            ->helperText(
                                'İade, değişim ve iptal koşullarını yazın.'
                            )
                            ->rows(6),

                        Textarea::make('company_rules')
                            ->label('Özel Firma Kuralları')
                            ->helperText(
                                'Yapay zekânın özellikle uyması gereken firma kurallarını yazın.'
                            )
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | ÖZEL YAPAY ZEKA TALİMATLARI
                |--------------------------------------------------------------------------
                */

                Section::make('Özel Yapay Zekâ Talimatları')
                    ->description(
                        'Bu botun müşterilerle nasıl konuşacağını belirleyen özel talimatları yazabilirsiniz.'
                    )
                    ->schema([

                        Textarea::make('system_prompt')
                            ->label('Özel Talimatlar')
                            ->helperText(
                                'Örnek: Samimi konuş. Önce müşterinin ihtiyacını öğren. Fiyat uydurma. Satışa yönlendir.'
                            )
                            ->rows(12)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}