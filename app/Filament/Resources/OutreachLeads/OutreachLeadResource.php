<?php

namespace App\Filament\Resources\OutreachLeads;

use App\Filament\Resources\OutreachLeads\Pages\CreateOutreachLead;
use App\Filament\Resources\OutreachLeads\Pages\ListOutreachLeads;
use App\Models\OutreachLead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OutreachLeadResource extends Resource
{
    protected static ?string $model = OutreachLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;
    protected static ?string $navigationLabel = 'İşletme Leadleri';
    protected static ?string $modelLabel = 'İşletme Leadi';
    protected static ?string $pluralModelLabel = 'İşletme Leadleri';
    protected static ?string $slug = 'isletme-leadleri';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin
            || strtolower(trim((string) $user->email)) === 'soykan@gmail.com';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }

        return $query->freshFirst();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_name')
                ->label('İşletme Adı')
                ->required()
                ->maxLength(255),

            TextInput::make('phone_e164')
                ->label('Mobil Numara')
                ->helperText('Türkiye mobil hattı: +905XXXXXXXXX')
                ->required()
                ->regex('/^\+?905\d{9}$/')
                ->maxLength(16),

            TextInput::make('source')
                ->label('Kaynak')
                ->default('manual')
                ->maxLength(100),

            TextInput::make('source_url')
                ->label('Kaynak Linki')
                ->url()
                ->maxLength(2048),

            DateTimePicker::make('source_published_at')
                ->label('Kaynak Eklenme / Güncellenme Tarihi'),

            DateTimePicker::make('source_checked_at')
                ->label('Son Kontrol')
                ->default(now()),

            Select::make('whatsapp_status')
                ->label('WhatsApp')
                ->options([
                    'unknown' => 'Bilinmiyor',
                    'verified' => 'Doğrulandı',
                    'unavailable' => 'Yok / Kullanılamıyor',
                ])
                ->default('unknown'),

            Textarea::make('first_message_text')
                ->label('İlk Mesaj')
                ->placeholder('Boş bırakırsanız: Merhaba kolay gelsin, [İşletme Adı] doğru mudur?')
                ->rows(3),

            Textarea::make('notes')
                ->label('Not')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('İşletme')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_e164')
                    ->label('Mobil')
                    ->searchable(),

                TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'verified' => 'Doğrulandı',
                        'unavailable' => 'Yok',
                        default => 'Bilinmiyor',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'verified' => 'success',
                        'unavailable' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Kaynak')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('source_published_at')
                    ->label('Kaynak Tarihi')
                    ->date('d.m.Y')
                    ->badge()
                    ->color(fn (OutreachLead $record): string => $record->isFreshSource() ? 'success' : 'gray')
                    ->placeholder('Tarih yok')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ready' => 'Hazır',
                        'opened' => 'İlk Temas Açıldı',
                        'replied' => 'Cevap Verdi',
                        'ai_active' => 'Wai Aktif',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'info',
                        'opened' => 'warning',
                        'replied', 'ai_active' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('contact_opened_at')
                    ->label('İlk Temas')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->options([
                        'verified' => 'Doğrulandı',
                        'unknown' => 'Bilinmiyor',
                        'unavailable' => 'Yok / Kullanılamıyor',
                    ]),
                SelectFilter::make('status')
                    ->label('Temas Durumu')
                    ->options([
                        'ready' => 'Hazır',
                        'opened' => 'İlk Temas Açıldı',
                        'replied' => 'Cevap Verdi',
                        'ai_active' => 'Wai Aktif',
                    ]),
            ])
            ->recordActions([
                Action::make('openWhatsapp')
                    ->label(fn (OutreachLead $record): string => $record->contact_opened_at ? 'Gönderildi' : 'WhatsApp’ta Aç')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color(fn (OutreachLead $record): string => $record->contact_opened_at ? 'gray' : 'success')
                    ->disabled(fn (OutreachLead $record): bool => (bool) $record->contact_opened_at || $record->whatsapp_status === 'unavailable')
                    ->url(fn (OutreachLead $record): string => route('outreach-leads.whatsapp', $record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutreachLeads::route('/'),
            'create' => CreateOutreachLead::route('/create'),
        ];
    }
}
