<?php

namespace App\Filament\Resources\OutreachLeads;

use App\Filament\Resources\OutreachLeads\Pages\CreateOutreachLead;
use App\Filament\Resources\OutreachLeads\Pages\ListOutreachLeads;
use App\Models\OutreachLead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
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

        return $user->is_admin
            ? $query
            : $query->where('user_id', $user->id);
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

                TextColumn::make('created_at')
                    ->label('Eklendi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('openWhatsapp')
                    ->label(fn (OutreachLead $record): string => $record->contact_opened_at ? 'Gönderildi' : 'WhatsApp’ta Aç')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color(fn (OutreachLead $record): string => $record->contact_opened_at ? 'gray' : 'success')
                    ->disabled(fn (OutreachLead $record): bool => (bool) $record->contact_opened_at)
                    ->url(fn (OutreachLead $record): string => route('outreach-leads.whatsapp', $record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutreachLeads::route('/'),
            'create' => CreateOutreachLead::route('/create'),
        ];
    }
}
