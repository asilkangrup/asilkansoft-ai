<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\OutreachLead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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

class SupplierResource extends Resource
{
    protected static ?string $model = OutreachLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static ?string $navigationLabel = 'Tedarikçiler';
    protected static ?string $modelLabel = 'Tedarikçi';
    protected static ?string $pluralModelLabel = 'Tedarikçiler';
    protected static ?string $slug = 'tedarikciler';
    protected static ?int $navigationSort = 5;

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
        $query = parent::getEloquentQuery()->where('source', 'supplier');
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
                ->label('Firma Adı')
                ->required()
                ->maxLength(255),

            TextInput::make('phone_e164')
                ->label('WhatsApp / Mobil Numara')
                ->helperText('Türkiye mobil hattı: +905XXXXXXXXX')
                ->required()
                ->regex('/^\+?905\d{9}$/')
                ->maxLength(16),

            TextInput::make('sector')
                ->label('Ürün Grubu')
                ->placeholder('Örn: Boş oversize tişört, kupa, şapka')
                ->maxLength(255),

            TextInput::make('source_url')
                ->label('Web / Instagram Linki')
                ->url()
                ->maxLength(2048),

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
                ->placeholder('Boş bırakırsanız tedarikçi için hazır mesaj kullanılır.')
                ->rows(6),

            Textarea::make('notes')
                ->label('Tedarikçi Notları')
                ->placeholder('Min sipariş: 50 | Fiyat: ... | Kör sevkiyat: Var/Yok | Numune: Var/Yok | Renkler: ...')
                ->rows(5),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone_e164')
                    ->label('Mobil')
                    ->searchable(),

                TextColumn::make('sector')
                    ->label('Ürün Grubu')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Not')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),

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

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ready' => 'Yeni',
                        'opened' => 'WhatsApp Açıldı',
                        'replied' => 'Cevap Verdi',
                        'ai_active' => 'Aktif',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'info',
                        'opened' => 'warning',
                        'replied', 'ai_active' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('contact_opened_at')
                    ->label('Son Temas')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sector')
                    ->label('Ürün Grubu')
                    ->options(fn (): array => static::getEloquentQuery()
                        ->reorder()
                        ->whereNotNull('sector')
                        ->whereRaw("TRIM(sector) <> ''")
                        ->distinct()
                        ->orderBy('sector')
                        ->pluck('sector', 'sector')
                        ->all()),
                SelectFilter::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->options([
                        'verified' => 'Doğrulandı',
                        'unknown' => 'Bilinmiyor',
                        'unavailable' => 'Yok / Kullanılamıyor',
                    ]),
            ])
            ->recordActions([
                Action::make('openWhatsapp')
                    ->label('WhatsApp’ta Aç')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->disabled(fn (OutreachLead $record): bool => $record->whatsapp_status === 'unavailable')
                    ->action(function (OutreachLead $record) {
                        $record->forceFill([
                            'contact_opened_at' => now(),
                            'status' => $record->status === 'ready' ? 'opened' : $record->status,
                        ])->save();

                        return redirect()->away($record->whatsappUrl());
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
        ];
    }
}
