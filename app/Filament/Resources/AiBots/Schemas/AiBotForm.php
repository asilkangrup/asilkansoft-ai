<?php

namespace App\Filament\Resources\AiBots\Schemas;

use App\Models\AiBot;
use App\Services\WhatsAppService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Throwable;

class AiBotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | YAPAY ZEKÂ KONTROLÜ
                |--------------------------------------------------------------------------
                */

                Section::make('Yapay Zekâ Kontrolü')
                    ->description(
                        'Yapay zekânın WhatsApp müşterilerine otomatik cevap verip vermeyeceğini yönetin.'
                    )
                    ->icon('heroicon-o-power')
                    ->schema([

                        Toggle::make('ai_enabled')
                            ->label('Yapay Zekâ Aktif')
                            ->helperText(
                                'Kapattığınızda WhatsApp bağlantısı ve QR kodu bağlı kalır, ancak yapay zekâ müşterilere otomatik cevap vermez.'
                            )
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->live(),

                    ]),

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP GRUP YÖNLENDİRME
                |--------------------------------------------------------------------------
                */

                Section::make('WhatsApp Grup Yönlendirme')
                    ->description(
                        'Tamamlanan finans başvurularını bağlı WhatsApp hesabınızdaki doğru gruplara otomatik yönlendirin.'
                    )
                    ->icon('heroicon-o-user-group')
                    ->collapsed()
                    ->schema([

                        Toggle::make('group_routing_enabled')
                            ->label('Grup Yönlendirmeyi Aktif Et')
                            ->helperText(
                                'Açıldığında tamamlanan başvurular aşağıda seçtiğiniz WhatsApp gruplarına otomatik gönderilir.'
                            )
                            ->default(false)
                            ->onColor('success')
                            ->offColor('danger')
                            ->live(),

                        Select::make('vodafone_group_jid')
                            ->label('Vodafone Başvuruları Grubu')
                            ->placeholder('WhatsApp grubu seçin')
                            ->helperText(
                                'Vodafone başvuruları bu gruba gönderilir.'
                            )
                            ->options(
                                fn (?AiBot $record): array =>
                                    self::whatsAppGruplari($record)
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('group_routing_enabled')
                            ),

                        Select::make('turktelekom_group_jid')
                            ->label('Türk Telekom Başvuruları Grubu')
                            ->placeholder('WhatsApp grubu seçin')
                            ->helperText(
                                'Türk Telekom başvuruları bu gruba gönderilir.'
                            )
                            ->options(
                                fn (?AiBot $record): array =>
                                    self::whatsAppGruplari($record)
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('group_routing_enabled')
                            ),

                        Select::make('turkcell_group_jid')
                            ->label('Turkcell Başvuruları Grubu')
                            ->placeholder('WhatsApp grubu seçin')
                            ->helperText(
                                'Turkcell başvuruları bu gruba gönderilir.'
                            )
                            ->options(
                                fn (?AiBot $record): array =>
                                    self::whatsAppGruplari($record)
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('group_routing_enabled')
                            ),

                        Select::make('findeks_group_jid')
                            ->label('Findeks Başvuruları Grubu')
                            ->placeholder('WhatsApp grubu seçin')
                            ->helperText(
                                'Findeks / banka kredi danışmanlığı başvuruları bu gruba gönderilir.'
                            )
                            ->options(
                                fn (?AiBot $record): array =>
                                    self::whatsAppGruplari($record)
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('group_routing_enabled')
                            ),

                        Select::make('elden_taksit_group_jid')
                            ->label('Elden Taksit Başvuruları Grubu')
                            ->placeholder('WhatsApp grubu seçin')
                            ->helperText(
                                'Bankasız / kefilsiz elden taksit başvuruları bu gruba gönderilir.'
                            )
                            ->options(
                                fn (?AiBot $record): array =>
                                    self::whatsAppGruplari($record)
                            )
                            ->searchable()
                            ->native(false)
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get('group_routing_enabled')
                            ),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | TEMEL BİLGİLER
                |--------------------------------------------------------------------------
                */

                Section::make('Temel Bilgiler')
                    ->description(
                        'Yapay zekânızın ve firmanızın temel bilgilerini düzenleyin.'
                    )
                    ->icon('heroicon-o-building-office')
                    ->schema([

                        TextInput::make('name')
                            ->label('Yapay Zekâ Adı')
                            ->placeholder('Örn: Satış Asistanım')
                            ->helperText(
                                'Panelde göreceğiniz yapay zekâ adıdır.'
                            )
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
                            ->helperText(
                                'Ülke koduyla birlikte yazabilirsiniz.'
                            )
                            ->tel()
                            ->maxLength(30),

                        FileUpload::make('logo_path')
                            ->label('Firma Logosu')
                            ->image()
                            ->directory('ai-bot-logos'),

                        Select::make('role')
                            ->label('Yapay Zekânın Görevi')
                            ->options([
                                'sales' =>
                                    'Satış Uzmanı',

                                'support' =>
                                    'Müşteri Temsilcisi',

                                'technical' =>
                                    'Teknik Destek',

                                'assistant' =>
                                    'Sekreter / Asistan',
                            ])
                            ->required(),

                        Hidden::make('openai_model')
                            ->default('gpt-5-mini'),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | FİRMA BİLGİLERİ
                |--------------------------------------------------------------------------
                */

                Section::make('Firma Bilgileri')
                    ->description(
                        'Yapay zekânın firmanızı doğru tanıtması için kullanılan bilgiler.'
                    )
                    ->icon('heroicon-o-information-circle')
                    ->schema([

                        Textarea::make('company_description')
                            ->label('Firma Hakkında')
                            ->placeholder(
                                'Firmanız ne yapıyor? Hangi ürün veya hizmetleri sunuyorsunuz?'
                            )
                            ->helperText(
                                'Yapay zekâ müşterilere firmanızı anlatırken bu bilgileri kullanır.'
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
                | SATIŞ VE HİZMET
                |--------------------------------------------------------------------------
                */

                Section::make('Satış ve Hizmet Bilgileri')
                    ->description(
                        'Ödeme, teslimat, kargo ve iade bilgilerini düzenleyin.'
                    )
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
                            ->label(
                                'İade / Değişim / İptal Politikası'
                            )
                            ->placeholder(
                                'Varsa iade, değişim ve iptal koşullarınızı yazın.'
                            )
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | YAPAY ZEKA EĞİTİMİ
                |--------------------------------------------------------------------------
                */

                Section::make('Yapay Zekâ Eğitimi')
                    ->description(
                        'Asistanın konuşma şeklini ve uyması gereken kuralları yönetin.'
                    )
                    ->icon('heroicon-o-academic-cap')
                    ->schema([

                        Textarea::make('company_rules')
                            ->label('Özel Firma Kuralları')
                            ->placeholder(
                                'Örn: Bilmediğin fiyatı uydurma. Kesin teslimat sözü verme. Müşteriyi uygun şekilde satışa yönlendir.'
                            )
                            ->helperText(
                                'Yapay zekânın kesinlikle uyması gereken firma kurallarını yazın.'
                            )
                            ->rows(7)
                            ->columnSpanFull(),

                        Textarea::make('system_prompt')
                            ->label(
                                'Konuşma ve Satış Talimatları'
                            )
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
                | OTOMATİK TAKİP
                |--------------------------------------------------------------------------
                */

                Section::make('Otomatik Takip Mesajları')
                    ->description(
                        'Cevap vermeyen müşterilere gönderilecek otomatik mesajları yönetin.'
                    )
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([

                        Toggle::make('follow_up_enabled')
                            ->label(
                                'Cevap Vermeyen Müşterileri Otomatik Takip Et'
                            )
                            ->helperText(
                                'Müşteri görüşmeyi yarıda bırakırsa belirlediğiniz süre sonunda otomatik hatırlatma gönderilir.'
                            )
                            ->default(false)
                            ->live(),

                        Select::make(
                            'first_follow_up_minutes'
                        )
                            ->label(
                                '1. Hatırlatma Ne Zaman Gönderilsin?'
                            )
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
                                    (bool) $get(
                                        'follow_up_enabled'
                                    )
                            ),

                        Textarea::make(
                            'first_follow_up_message'
                        )
                            ->label(
                                '1. Hatırlatma Mesajı'
                            )
                            ->default(
                                'Merhaba 👋 Daha önce görüştüğümüz ürünle hâlâ ilgileniyor musunuz? Size yardımcı olabilirim.'
                            )
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'follow_up_enabled'
                                    )
                            ),

                        Toggle::make(
                            'second_follow_up_enabled'
                        )
                            ->label(
                                '2. ve Son Hatırlatma Gönder'
                            )
                            ->default(true)
                            ->live()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'follow_up_enabled'
                                    )
                            ),

                        Select::make(
                            'second_follow_up_minutes'
                        )
                            ->label(
                                '2. Hatırlatma Ne Zaman Gönderilsin?'
                            )
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
                                    (bool) $get(
                                        'follow_up_enabled'
                                    )
                                    && (bool) $get(
                                        'second_follow_up_enabled'
                                    )
                            ),

                        Textarea::make(
                            'second_follow_up_message'
                        )
                            ->label(
                                '2. ve Son Hatırlatma Mesajı'
                            )
                            ->default(
                                'Merhaba 👋 Daha önce görüştüğümüz ürünle ilgili yardımcı olabileceğimiz bir konu var mı? Dilerseniz siparişinizi birlikte oluşturabiliriz.'
                            )
                            ->rows(4)
                            ->columnSpanFull()
                            ->visible(
                                fn ($get): bool =>
                                    (bool) $get(
                                        'follow_up_enabled'
                                    )
                                    && (bool) $get(
                                        'second_follow_up_enabled'
                                    )
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BAĞLI WHATSAPP HESABININ GRUPLARI
    |--------------------------------------------------------------------------
    |
    | Her bot için kendi whatsapp_instance değeri kullanılır.
    |
    | Örneğin:
    |
    | Bot A -> bot-a-10 -> sadece Bot A'nın grupları
    | Bot B -> bot-b-20 -> sadece Bot B'nin grupları
    |
    | Böylece müşterilerin grupları birbirine karışmaz.
    |
    */

    private static function whatsAppGruplari(
        ?AiBot $record
    ): array {
        if (! $record) {
            return [];
        }

        $instanceName = trim(
            (string) $record->whatsapp_instance
        );

        if ($instanceName === '') {
            return [];
        }

        try {
            $groups =
                app(WhatsAppService::class)
                    ->fetchGroups(
                        $instanceName
                    );

            $options = [];

            foreach ($groups as $group) {
                $id = trim(
                    (string) (
                        $group['id']
                        ?? ''
                    )
                );

                $name = trim(
                    (string) (
                        $group['name']
                        ?? ''
                    )
                );

                if (
                    $id === ''
                    || ! str_ends_with(
                        $id,
                        '@g.us'
                    )
                ) {
                    continue;
                }

                $options[$id] =
                    $name !== ''
                        ? $name
                        : $id;
            }

            return $options;

        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }
}