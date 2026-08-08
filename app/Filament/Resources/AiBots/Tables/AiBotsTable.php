<?php

namespace App\Filament\Resources\AiBots\Tables;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Models\AiBot;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

class AiBotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Yapay Zekâ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('whatsapp_number')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Numara eklenmedi'),

                BadgeColumn::make('whatsapp_status')
                    ->label('WhatsApp Durumu')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'connected' => 'Bağlı',
                        'connecting' => 'QR Bekleniyor',
                        default => 'Bağlı Değil',
                    })
                    ->colors([
                        'success' => 'connected',
                        'warning' => 'connecting',
                        'danger' => 'disconnected',
                    ]),

                BadgeColumn::make('status')
                    ->label('Bot Durumu')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'passive' => 'Pasif',
                        default => 'Taslak',
                    })
                    ->colors([
                        'success' => 'active',
                        'danger' => 'passive',
                        'warning' => 'draft',
                    ]),

                TextColumn::make('openai_model')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'gpt-5' => 'GPT-5',
                        'gpt-5-mini' => 'GPT-5 Mini',
                        default => $state ?? 'GPT-5 Mini',
                    }),

                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('whatsappBagla')
                    ->label(fn (AiBot $record): string =>
                        $record->whatsapp_status === 'connected'
                            ? 'WhatsApp Durumu'
                            : 'WhatsApp Bağla'
                    )
                    ->icon('heroicon-o-qr-code')
                    ->color('success')
                    ->action(function (AiBot $record): mixed {
                        try {
                            /*
                             * Daha önce instance oluşturulmadıysa
                             * Evolution API üzerinde oluşturuyoruz.
                             */
                            if (blank($record->whatsapp_instance)) {
                                $firmaAdi = Str::slug(
                                    $record->company_name ?: $record->name
                                );

                                $instanceName = trim(
                                    $firmaAdi . '-' . $record->id,
                                    '-'
                                );

                                $response = app(WhatsAppService::class)
                                    ->createInstance($instanceName);

                                $qrCode = data_get($response, 'qrcode.base64')
                                    ?? data_get($response, 'qrcode.code')
                                    ?? data_get($response, 'base64')
                                    ?? data_get($response, 'code');

                                $record->update([
                                    'whatsapp_instance' => $instanceName,
                                    'whatsapp_status' => 'connecting',
                                    'whatsapp_qr' => is_string($qrCode)
                                        ? $qrCode
                                        : null,
                                ]);
                            }

                            /*
                             * Instance zaten varsa tekrar oluşturmuyoruz.
                             * Doğrudan WhatsApp QR sayfasına gidiyoruz.
                             */
                            return redirect(
                                AiBotResource::getUrl('whatsapp', [
                                    'record' => $record,
                                ])
                            );
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('WhatsApp bağlantısı açılamadı')
                                ->body($exception->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return null;
                        }
                    }),

                EditAction::make()
                    ->label('Düzenle'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Seçilenleri Sil'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}