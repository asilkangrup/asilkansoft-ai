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
use Illuminate\Support\Facades\Cache;
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
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'connected' => 'Bağlı',
                            'connecting' => 'QR Bekleniyor',
                            default => 'Bağlı Değil',
                        }
                    )
                    ->colors([
                        'success' => 'connected',
                        'warning' => 'connecting',
                        'danger' => 'disconnected',
                    ]),

                BadgeColumn::make('status')
                    ->label('Bot Durumu')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'active' => 'Aktif',
                            'passive' => 'Pasif',
                            default => 'Taslak',
                        }
                    )
                    ->colors([
                        'success' => 'active',
                        'danger' => 'passive',
                        'warning' => 'draft',
                    ]),

                TextColumn::make('openai_model')
                    ->label('Model')
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'gpt-5' => 'GPT-5',
                            'gpt-5-mini' => 'GPT-5 Mini',
                            default => $state ?? 'GPT-5 Mini',
                        }
                    ),

                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])

            ->filters([])

            ->recordActions([

                Action::make('whatsappBagla')
                    ->label(
                        fn (AiBot $record): string =>
                            $record->whatsapp_status === 'connected'
                                ? 'WhatsApp Durumu'
                                : 'WhatsApp Bağla'
                    )
                    ->icon(
                        fn (AiBot $record): string =>
                            $record->whatsapp_status === 'connected'
                                ? 'heroicon-o-check-circle'
                                : 'heroicon-o-qr-code'
                    )
                    ->color(
                        fn (AiBot $record): string =>
                            $record->whatsapp_status === 'connected'
                                ? 'success'
                                : 'warning'
                    )
                    ->action(function (AiBot $record): mixed {

                        $lock = Cache::lock(
                            'whatsapp-connect-ai-bot-'.$record->id,
                            20
                        );

                        if (! $lock->get()) {
                            Notification::make()
                                ->title('Bağlantı işlemi devam ediyor')
                                ->body(
                                    'WhatsApp bağlantısı hazırlanıyor. Lütfen birkaç saniye bekleyin.'
                                )
                                ->warning()
                                ->send();

                            return null;
                        }

                        try {
                            $record->refresh();

                            $whatsAppService = app(
                                WhatsAppService::class
                            );

                            /*
                            |--------------------------------------------------------------------------
                            | INSTANCE YOKSA OLUŞTUR
                            |--------------------------------------------------------------------------
                            */

                            if (blank($record->whatsapp_instance)) {

                                $firmaAdi = Str::slug(
                                    $record->company_name
                                    ?: $record->name
                                );

                                $instanceName = trim(
                                    $firmaAdi.'-'.$record->id,
                                    '-'
                                );

                                $response = $whatsAppService
                                    ->createInstance(
                                        $instanceName
                                    );

                                $qrCode =
                                    data_get(
                                        $response,
                                        'qrcode.base64'
                                    )
                                    ?? data_get(
                                        $response,
                                        'qrcode.code'
                                    )
                                    ?? data_get(
                                        $response,
                                        'base64'
                                    )
                                    ?? data_get(
                                        $response,
                                        'code'
                                    );

                                $record->update([
                                    'whatsapp_instance' =>
                                        $instanceName,

                                    'whatsapp_status' =>
                                        'connecting',

                                    'whatsapp_qr' =>
                                        is_string($qrCode)
                                            ? $qrCode
                                            : null,
                                ]);

                                /*
                                |--------------------------------------------------------------------------
                                | WEBHOOK'U OTOMATİK KUR
                                |--------------------------------------------------------------------------
                                */

                                $webhookPath = (int) $record->getKey() === 48
                                    ? '/api/wai-sales/whatsapp/webhook'
                                    : '/api/whatsapp/webhook';

                                $webhookUrl =
                                    rtrim(
                                        (string) config(
                                            'app.url'
                                        ),
                                        '/'
                                    )
                                    .$webhookPath;

                                $whatsAppService->setWebhook(
                                    $instanceName,
                                    $webhookUrl
                                );
                            }

                            /*
                            |--------------------------------------------------------------------------
                            | MEVCUT INSTANCE VARSA WEBHOOK'U YİNE KONTROL ET
                            |--------------------------------------------------------------------------
                            |
                            | Eski müşterilerde webhook boş kalmış olabilir.
                            | Bu nedenle WhatsApp Bağla ekranına her girişte webhook'u
                            | yeniden set ediyoruz.
                            |
                            */

                            if (
                                filled(
                                    $record->whatsapp_instance
                                )
                            ) {
                                $webhookPath = (int) $record->getKey() === 48
                                    ? '/api/wai-sales/whatsapp/webhook'
                                    : '/api/whatsapp/webhook';

                                $webhookUrl =
                                    rtrim(
                                        (string) config(
                                            'app.url'
                                        ),
                                        '/'
                                    )
                                    .$webhookPath;

                                $whatsAppService->setWebhook(
                                    $record->whatsapp_instance,
                                    $webhookUrl
                                );
                            }

                            return redirect(
                                AiBotResource::getUrl(
                                    'whatsapp',
                                    [
                                        'record' => $record,
                                    ]
                                )
                            );

                        } catch (Throwable $exception) {

                            report($exception);

                            Notification::make()
                                ->title(
                                    'WhatsApp bağlantısı açılamadı'
                                )
                                ->body(
                                    'Bağlantı sırasında bir sorun oluştu. Lütfen tekrar deneyin.'
                                )
                                ->danger()
                                ->persistent()
                                ->send();

                            return null;

                        } finally {
                            $lock->release();
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

            ->defaultSort(
                'created_at',
                'desc'
            );
    }
}