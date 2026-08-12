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
                | YAPAY ZEKÃ‚ KONTROLÃœ
                |--------------------------------------------------------------------------
                */

                Section::make('Yapay ZekÃ¢ KontrolÃ¼')
                    ->description(
                        'Yapay zekÃ¢nÄ±n WhatsApp mÃ¼ÅŸterilerine otomatik cevap verip vermeyeceÄŸini yÃ¶netin.'
                    )
                    ->icon('heroicon-o-power')
                    ->schema([

                        Toggle::make('ai_enabled')
                            ->label('Yapay ZekÃ¢ Aktif')
                            ->helperText(
                                'KapattÄ±ÄŸÄ±nÄ±zda WhatsApp baÄŸlantÄ±sÄ± ve QR kodu baÄŸlÄ± kalÄ±r, ancak yapay zekÃ¢ mÃ¼ÅŸterilere otomatik cevap vermez.'
                            )
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger')
                            ->live(),

                    ]),

                /*
                |--------------------------------------------------------------------------
                | WHATSAPP GRUP YÃ–NLENDÄ°RME
                |--------------------------------------------------------------------------
                */

                Section::make('WhatsApp Grup YÃ¶nlendirme')
                    ->description(
                        'Tamamlanan finans baÅŸvurularÄ±nÄ± baÄŸlÄ± WhatsApp hesabÄ±nÄ±zdaki doÄŸru gruplara otomatik yÃ¶nlendirin.'
                    )
                    ->icon('heroicon-o-user-group')
                    ->visible(
                        fn (?AiBot $record): bool =>
                            (int) ($record?->id ?? 0) === 30
                    )
                    ->collapsed()
                    ->schema([

                        Toggle::make('group_routing_enabled')
                            ->label('Grup YÃ¶nlendirmeyi Aktif Et')
                            ->helperText(
                                'AÃ§Ä±ldÄ±ÄŸÄ±nda tamamlanan baÅŸvurular aÅŸaÄŸÄ±da seÃ§tiÄŸiniz WhatsApp gruplarÄ±na otomatik gÃ¶nderilir.'
                            )
                            ->default(false)
                            ->onColor('success')
                            ->offColor('danger')
                            ->live(),

                        Select::make('vodafone_group_jid')
                            ->label('Vodafone BaÅŸvurularÄ± Grubu')
                            ->placeholder('WhatsApp grubu seÃ§in')
                            ->helperText(
                                'Vodafone baÅŸvurularÄ± bu gruba gÃ¶nderilir.'
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
                            ->label('TÃ¼rk Telekom BaÅŸvurularÄ± Grubu')
                            ->placeholder('WhatsApp grubu seÃ§in')
                            ->helperText(
                                'TÃ¼rk Telekom baÅŸvurularÄ± bu gruba gÃ¶nderilir.'
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
                            ->label('Turkcell BaÅŸvurularÄ± Grubu')
                            ->placeholder('WhatsApp grubu seÃ§in')
                            ->helperText(
                                'Turkcell baÅŸvurularÄ± bu gruba gÃ¶nderilir.'
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
                            ->label('Findeks BaÅŸvurularÄ± Grubu')
                            ->placeholder('WhatsApp grubu seÃ§in')
                            ->helperText(
                                'Findeks / banka kredi danÄ±ÅŸmanlÄ±ÄŸÄ± baÅŸvurularÄ± bu gruba gÃ¶nderilir.'
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
                            ->label('Elden Taksit BaÅŸvurularÄ± Grubu')
                            ->placeholder('WhatsApp grubu seÃ§in')
                            ->helperText(
                                'BankasÄ±z / kefilsiz elden taksit baÅŸvurularÄ± bu gruba gÃ¶nderilir.'
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
                | TEMEL BÄ°LGÄ°LER
                |--------------------------------------------------------------------------
                */

                Section::make('Temel Bilgiler')
                    ->description(
                        'Yapay zekÃ¢nÄ±zÄ±n ve firmanÄ±zÄ±n temel bilgilerini dÃ¼zenleyin.'
                    )
                    ->icon('heroicon-o-building-office')
                    ->schema([

                        TextInput::make('name')
                            ->label('Yapay ZekÃ¢ AdÄ±')
                            ->placeholder('Ã–rn: SatÄ±ÅŸ AsistanÄ±m')
                            ->helperText(
                                'Panelde gÃ¶receÄŸiniz yapay zekÃ¢ adÄ±dÄ±r.'
                            )
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company_name')
                            ->label('Firma AdÄ±')
                            ->placeholder('Ã–rn: ABC Klima')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('whatsapp_number')
                            ->label('WhatsApp NumarasÄ±')
                            ->placeholder('905xxxxxxxxx')
                            ->helperText(
                                'Ãœlke koduyla birlikte yazabilirsiniz.'
                            )
                            ->tel()
                            ->maxLength(30),

                        FileUpload::make('logo_path')
                            ->label('Firma Logosu')
                            ->image()
                            ->directory('ai-bot-logos'),

                        Select::make('role')
                            ->label('Yapay ZekÃ¢nÄ±n GÃ¶revi')
                            ->options([
                                'sales' =>
                                    'SatÄ±ÅŸ UzmanÄ±',

                                'support' =>
                                    'MÃ¼ÅŸteri Temsilcisi',

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
                | FÄ°RMA BÄ°LGÄ°LERÄ°
                |--------------------------------------------------------------------------
                */

                Section::make('Firma Bilgileri')
                    ->description(
                        'Yapay zekÃ¢nÄ±n firmanÄ±zÄ± doÄŸru tanÄ±tmasÄ± iÃ§in kullanÄ±lan bilgiler.'
                    )
                    ->icon('heroicon-o-information-circle')
                    ->schema([

                        Textarea::make('company_description')
                            ->label('Firma HakkÄ±nda')
                            ->placeholder(
                                'FirmanÄ±z ne yapÄ±yor? Hangi Ã¼rÃ¼n veya hizmetleri sunuyorsunuz?'
                            )
                            ->helperText(
                                'Yapay zekÃ¢ mÃ¼ÅŸterilere firmanÄ±zÄ± anlatÄ±rken bu bilgileri kullanÄ±r.'
                            )
                            ->rows(7)
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('working_hours')
                            ->label('Ã‡alÄ±ÅŸma Saatleri')
                            ->placeholder(
                                'Ã–rn: Pazartesi - Cumartesi 09:00 - 18:00'
                            )
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | SATIÅ VE HÄ°ZMET
                |--------------------------------------------------------------------------
                */

                Section::make('SatÄ±ÅŸ ve Hizmet Bilgileri')
                    ->description(
                        'Ã–deme, teslimat, kargo ve iade bilgilerini dÃ¼zenleyin.'
                    )
                    ->icon('heroicon-o-shopping-cart')
                    ->schema([

                        Textarea::make('cargo_information')
                            ->label('Kargo ve Teslimat Bilgileri')
                            ->placeholder(
                                'Kargo firmasÄ±, teslimat sÃ¼resi, Ã¼cretsiz kargo ÅŸartlarÄ± veya hizmet bÃ¶lgesini yazÄ±n.'
                            )
                            ->helperText(
                                'Hizmet sektÃ¶rÃ¼ndeyseniz servis bÃ¶lgesi ve randevu bilgilerini yazabilirsiniz.'
                            )
                            ->rows(6),

                        Textarea::make('payment_information')
                            ->label('Ã–deme Bilgileri')
                            ->placeholder(
                                'KapÄ±da nakit, kapÄ±da kart, havale, kredi kartÄ± vb.'
                            )
                            ->rows(6),

                        Textarea::make('return_policy')
                            ->label(
                                'Ä°ade / DeÄŸiÅŸim / Ä°ptal PolitikasÄ±'
                            )
                            ->placeholder(
                                'Varsa iade, deÄŸiÅŸim ve iptal koÅŸullarÄ±nÄ±zÄ± yazÄ±n.'
                            )
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | YAPAY ZEKA EÄÄ°TÄ°MÄ°
                |--------------------------------------------------------------------------
                */

                Section::make('Yapay ZekÃ¢ EÄŸitimi')
                    ->description(
                        'AsistanÄ±n konuÅŸma ÅŸeklini ve uymasÄ± gereken kurallarÄ± yÃ¶netin.'
                    )
                    ->icon('heroicon-o-academic-cap')
                    ->schema([

                        Textarea::make('company_rules')
                            ->label('Ã–zel Firma KurallarÄ±')
                            ->placeholder(
                                'Ã–rn: BilmediÄŸin fiyatÄ± uydurma. Kesin teslimat sÃ¶zÃ¼ verme. MÃ¼ÅŸteriyi uygun ÅŸekilde satÄ±ÅŸa yÃ¶nlendir.'
                            )
                            ->helperText(
                                'Yapay zekÃ¢nÄ±n kesinlikle uymasÄ± gereken firma kurallarÄ±nÄ± yazÄ±n.'
                            )
                            ->rows(7)
                            ->columnSpanFull(),

                        Textarea::make('system_prompt')
                            ->label(
                                'KonuÅŸma ve SatÄ±ÅŸ TalimatlarÄ±'
                            )
                            ->placeholder(
                                'Ã–rn: Samimi ve profesyonel konuÅŸ. Ã–nce mÃ¼ÅŸterinin ihtiyacÄ±nÄ± Ã¶ÄŸren. KÄ±sa cevaplar ver.'
                            )
                            ->helperText(
                                'Yapay zekÃ¢nÄ±n mÃ¼ÅŸterilerle nasÄ±l konuÅŸacaÄŸÄ±nÄ± buradan belirleyebilirsiniz.'
                            )
                            ->rows(8)
                            ->columnSpanFull(),
                    ]),

                /*
                |--------------------------------------------------------------------------
                | OTOMATÄ°K TAKÄ°P
                |--------------------------------------------------------------------------
                */

                Section::make('Otomatik Takip MesajlarÄ±')
                    ->description(
                        'Cevap vermeyen mÃ¼ÅŸterilere gÃ¶nderilecek otomatik mesajlarÄ± yÃ¶netin.'
                    )
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([

                        Toggle::make('follow_up_enabled')
                            ->label(
                                'Cevap Vermeyen MÃ¼ÅŸterileri Otomatik Takip Et'
                            )
                            ->helperText(
                                'MÃ¼ÅŸteri gÃ¶rÃ¼ÅŸmeyi yarÄ±da bÄ±rakÄ±rsa belirlediÄŸiniz sÃ¼re sonunda otomatik hatÄ±rlatma gÃ¶nderilir.'
                            )
                            ->default(false)
                            ->live(),

                        Select::make(
                            'first_follow_up_minutes'
                        )
                            ->label(
                                '1. HatÄ±rlatma Ne Zaman GÃ¶nderilsin?'
                            )
                            ->options([
                                60 => '1 Saat Sonra',
                                120 => '2 Saat Sonra',
                                180 => '3 Saat Sonra',
                                360 => '6 Saat Sonra',
                                720 => '12 Saat Sonra',
                                1440 => '24 Saat Sonra',
                                2880 => '2 GÃ¼n Sonra',
                                4320 => '3 GÃ¼n Sonra',
                                7200 => '5 GÃ¼n Sonra',
                                10080 => '7 GÃ¼n Sonra',
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
                                '1. HatÄ±rlatma MesajÄ±'
                            )
                            ->default(
                                'Merhaba ğŸ‘‹ Daha Ã¶nce gÃ¶rÃ¼ÅŸtÃ¼ÄŸÃ¼mÃ¼z Ã¼rÃ¼nle hÃ¢lÃ¢ ilgileniyor musunuz? Size yardÄ±mcÄ± olabilirim.'
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
                                '2. ve Son HatÄ±rlatma GÃ¶nder'
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
                                '2. HatÄ±rlatma Ne Zaman GÃ¶nderilsin?'
                            )
                            ->options([
                                1440 => '1 GÃ¼n Sonra',
                                2880 => '2 GÃ¼n Sonra',
                                4320 => '3 GÃ¼n Sonra',
                                5760 => '4 GÃ¼n Sonra',
                                7200 => '5 GÃ¼n Sonra',
                                10080 => '7 GÃ¼n Sonra',
                                14400 => '10 GÃ¼n Sonra',
                                20160 => '14 GÃ¼n Sonra',
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
                                '2. ve Son HatÄ±rlatma MesajÄ±'
                            )
                            ->default(
                                'Merhaba ğŸ‘‹ Daha Ã¶nce gÃ¶rÃ¼ÅŸtÃ¼ÄŸÃ¼mÃ¼z Ã¼rÃ¼nle ilgili yardÄ±mcÄ± olabileceÄŸimiz bir konu var mÄ±? Dilerseniz sipariÅŸinizi birlikte oluÅŸturabiliriz.'
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
    | BAÄLI WHATSAPP HESABININ GRUPLARI
    |--------------------------------------------------------------------------
    |
    | Her bot iÃ§in kendi whatsapp_instance deÄŸeri kullanÄ±lÄ±r.
    |
    | Ã–rneÄŸin:
    |
    | Bot A -> bot-a-10 -> sadece Bot A'nÄ±n gruplarÄ±
    | Bot B -> bot-b-20 -> sadece Bot B'nin gruplarÄ±
    |
    | BÃ¶ylece mÃ¼ÅŸterilerin gruplarÄ± birbirine karÄ±ÅŸmaz.
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