<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateAiBot extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = AiBotResource::class;

    /*
    |--------------------------------------------------------------------------
    | "OLUŞTUR & YENİ OLUŞTUR" BUTONUNU KALDIR
    |--------------------------------------------------------------------------
    |
    | Müşteri onboarding sırasında tek bot oluştursun.
    | Gereksiz ikinci buton kafa karıştırmasın.
    |
    */

    protected static bool $canCreateAnother = false;

    /*
    |--------------------------------------------------------------------------
    | WIZARD ADIMLARI
    |--------------------------------------------------------------------------
    */

    protected function getSteps(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | ADIM 1 - %20
            |--------------------------------------------------------------------------
            */

            Step::make('1. Temel Bilgiler')
                ->description('Kurulum %20')
                ->icon('heroicon-o-building-office')
                ->schema([

                    TextInput::make('name')
                        ->label('Yapay Zekâ Adı')
                        ->placeholder('Örn: Satış Asistanım')
                        ->helperText('Panelde göreceğiniz yapay zekâ adıdır.')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('company_name')
                        ->label('Firma Adı')
                        ->placeholder('Örn: ABC Klima')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('whatsapp_number')
                        ->label('WhatsApp Numarası')
                        ->placeholder('905xxxxxxxxx')
                        ->helperText('Ülke koduyla birlikte yazabilirsiniz.')
                        ->tel()
                        ->maxLength(30),

                    FileUpload::make('logo_path')
                        ->label('Firma Logosu')
                        ->image()
                        ->directory('ai-bot-logos'),

                    Select::make('role')
                        ->label('Yapay Zekânın Görevi')
                        ->options([
                            'sales' => 'Satış Uzmanı',
                            'support' => 'Müşteri Temsilcisi',
                            'technical' => 'Teknik Destek',
                            'assistant' => 'Sekreter / Asistan',
                        ])
                        ->default('sales')
                        ->required(),
                ])
                ->columns(2),

            /*
            |--------------------------------------------------------------------------
            | ADIM 2 - %40
            |--------------------------------------------------------------------------
            */

            Step::make('2. Firma Bilgileri')
                ->description('Kurulum %40')
                ->icon('heroicon-o-information-circle')
                ->schema([

                    Textarea::make('company_description')
                        ->label('Firma Hakkında')
                        ->placeholder(
                            'Firmanız ne yapıyor? Hangi ürün veya hizmetleri sunuyorsunuz?'
                        )
                        ->helperText(
                            'Yapay zekâ müşterilere firmanızı anlatırken bu bilgileri kullanacaktır.'
                        )
                        ->rows(7)
                        ->required()
                        ->columnSpanFull(),

                    Textarea::make('working_hours')
                        ->label('Çalışma Saatleri')
                        ->placeholder(
                            'Örn: Pazartesi - Cumartesi 09:00 - 18:00'
                        )
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            /*
            |--------------------------------------------------------------------------
            | ADIM 3 - %60
            |--------------------------------------------------------------------------
            */

            Step::make('3. Satış ve Hizmet')
                ->description('Kurulum %60')
                ->icon('heroicon-o-shopping-cart')
                ->schema([

                    Textarea::make('cargo_information')
                        ->label('Kargo ve Teslimat Bilgileri')
                        ->placeholder(
                            'Kargo firması, teslimat süresi, ücretsiz kargo şartları veya hizmet bölgesini yazın.'
                        )
                        ->helperText(
                            'Hizmet sektöründeyseniz servis bölgesi ve randevu bilgilerini yazabilirsiniz.'
                        )
                        ->rows(6),

                    Textarea::make('payment_information')
                        ->label('Ödeme Bilgileri')
                        ->placeholder(
                            'Kapıda nakit, kapıda kart, havale, kredi kartı vb.'
                        )
                        ->rows(6),

                    Textarea::make('return_policy')
                        ->label('İade / Değişim / İptal Politikası')
                        ->placeholder(
                            'Varsa iade, değişim ve iptal koşullarınızı yazın.'
                        )
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            /*
            |--------------------------------------------------------------------------
            | ADIM 4 - %80
            |--------------------------------------------------------------------------
            */

            Step::make('4. Yapay Zekâ Eğitimi')
                ->description('Kurulum %80')
                ->icon('heroicon-o-academic-cap')
                ->schema([

                    Textarea::make('company_rules')
                        ->label('Özel Firma Kuralları')
                        ->placeholder(
                            'Örn: Bilmediğin fiyatı uydurma. Kesin teslimat sözü verme. Müşteriyi uygun şekilde satışa yönlendir.'
                        )
                        ->helperText(
                            'Yapay zekânın kesinlikle uyması gereken kuralları buraya yazın.'
                        )
                        ->rows(7)
                        ->columnSpanFull(),

                    Textarea::make('system_prompt')
                        ->label('Konuşma ve Satış Talimatları')
                        ->placeholder(
                            'Örn: Samimi ve profesyonel konuş. Önce müşterinin ihtiyacını öğren. Kısa cevaplar ver.'
                        )
                        ->helperText(
                            'Yapay zekânın müşterilerle nasıl konuşacağını buradan belirleyebilirsiniz.'
                        )
                        ->rows(8)
                        ->columnSpanFull(),
                ]),

            /*
            |--------------------------------------------------------------------------
            | ADIM 5 - %100
            |--------------------------------------------------------------------------
            */

            Step::make('5. Otomatik Takip')
                ->description('Kurulum %100')
                ->icon('heroicon-o-check-circle')
                ->schema([

                    Toggle::make('follow_up_enabled')
                        ->label('Cevap Vermeyen Müşterileri Otomatik Takip Et')
                        ->helperText(
                            'Müşteri görüşmeyi yarıda bırakırsa belirlediğiniz süre sonunda otomatik hatırlatma gönderilir.'
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
                        ->label('2. ve Son Hatırlatma Gönder')
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
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | KAYIT ÖNCESİ OTOMATİK AYARLAR
    |--------------------------------------------------------------------------
    */

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Teknik OpenAI model seçimini müşteriye göstermiyoruz.
        $data['openai_model'] = 'gpt-5-mini';

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | ADIM ATLAMAYI KAPAT
    |--------------------------------------------------------------------------
    */

    public function hasSkippableSteps(): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | KAYIT SONRASI
    |--------------------------------------------------------------------------
    |
    | Bot oluşturulduğunda müşteriyi direkt düzenleme ekranına değil,
    | Kurulum Merkezi'ne geri gönderiyoruz.
    |
    */

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.pages.test-sohbeti');
    }
}