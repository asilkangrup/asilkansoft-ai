<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Services\BusinessSectorService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;

class CreateAiBot extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = AiBotResource::class;

    protected static bool $canCreateAnother = false;

    protected function getSteps(): array
    {
        return [
            Step::make('1. Temel Bilgiler')
                ->description('Kurulum %25')
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

                    Select::make('business_sector')
                        ->label('Sektörünüz Nedir?')
                        ->placeholder('Sektörünüzü yazın veya listeden seçin')
                        ->helperText(
                            'Yazmaya başladığınızda uygun sektörler listelenir. WAI, lead puanlama sistemini seçiminize göre otomatik ayarlar.'
                        )
                        ->options(BusinessSectorService::options())
                        ->searchable()
                        ->native(false)
                        ->required(),

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

            Step::make('2. Firma Bilgileri')
                ->description('Kurulum %50')
                ->icon('heroicon-o-information-circle')
                ->schema([
                    Textarea::make('company_description')
                        ->label('Firma Hakkında')
                        ->placeholder('Firmanız ne yapıyor? Hangi ürün veya hizmetleri sunuyorsunuz?')
                        ->helperText('Yapay zekâ müşterilere firmanızı anlatırken bu bilgileri kullanacaktır.')
                        ->rows(7)
                        ->required()
                        ->columnSpanFull(),

                    Textarea::make('working_hours')
                        ->label('Çalışma Saatleri')
                        ->placeholder('Örn: Pazartesi - Cumartesi 09:00 - 18:00')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Step::make('3. Satış ve Hizmet')
                ->description('Kurulum %75')
                ->icon('heroicon-o-shopping-cart')
                ->schema([
                    Textarea::make('cargo_information')
                        ->label('Kargo ve Teslimat Bilgileri')
                        ->placeholder('Kargo firması, teslimat süresi, ücretsiz kargo şartları veya hizmet bölgesini yazın.')
                        ->helperText('Hizmet sektöründeyseniz servis bölgesi ve randevu bilgilerini yazabilirsiniz.')
                        ->rows(6),

                    Textarea::make('payment_information')
                        ->label('Ödeme Bilgileri')
                        ->placeholder('Kapıda nakit, kapıda kart, havale, kredi kartı vb.')
                        ->rows(6),

                    Textarea::make('return_policy')
                        ->label('İade / Değişim / İptal Politikası')
                        ->placeholder('Varsa iade, değişim ve iptal koşullarınızı yazın.')
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Step::make('4. Yapay Zekâ Eğitimi')
                ->description('Kurulum %100')
                ->icon('heroicon-o-academic-cap')
                ->schema([
                    Textarea::make('company_rules')
                        ->label('Özel Firma Kuralları')
                        ->placeholder('Örn: Bilmediğin fiyatı uydurma. Kesin teslimat sözü verme. Müşteriyi uygun şekilde satışa yönlendir.')
                        ->helperText('Yapay zekânın kesinlikle uyması gereken kuralları buraya yazın.')
                        ->rows(7)
                        ->columnSpanFull(),

                    Textarea::make('system_prompt')
                        ->label('Konuşma ve Satış Talimatları')
                        ->placeholder('Örn: Samimi ve profesyonel konuş. Önce müşterinin ihtiyacını öğren. Kısa cevaplar ver.')
                        ->helperText('Yapay zekânın müşterilerle nasıl konuşacağını buradan belirleyebilirsiniz.')
                        ->rows(8)
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['openai_model'] = 'gpt-5-mini';
        $data['lead_scoring_profile'] = BusinessSectorService::profileForSector(
            $data['business_sector'] ?? null
        );

        return $data;
    }

    public function hasSkippableSteps(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.pages.test-sohbeti');
    }
}
