<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\WhatsAppService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('aiBot.name')
                    ->label('Yapay Zekâ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Müşteri')
                    ->searchable()
                    ->placeholder('İsim alınmadı'),

                TextColumn::make('whatsapp_number')
                    ->label('WhatsApp')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('products')
                    ->label('Ürünler')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('-'),

                TextColumn::make('quantity')
                    ->label('Miktar')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('total_amount')
                    ->label('Toplam')
                    ->formatStateUsing(
                        fn ($state): string =>
                            $state === null
                                ? '-'
                                : '₺'.number_format(
                                    (float) $state,
                                    2,
                                    ',',
                                    '.'
                                )
                    )
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Ödeme')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'cash_on_delivery' => 'Kapıda Nakit',
                            'card_on_delivery' => 'Kapıda Kart',
                            'bank_transfer' => 'Havale / EFT',
                            'credit_card' => 'Kredi Kartı',
                            'other' => 'Diğer',
                            default => $state ?? '-',
                        }
                    )
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Sipariş Durumu')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'draft' => 'Bilgiler Toplanıyor',
                            'pending' => 'Sipariş Alındı',
                            'confirmed' => 'Onaylandı',
                            'preparing' => 'Hazırlanıyor',
                            'shipped' => 'Kargoya Verildi',
                            'delivered' => 'Teslim Edildi',
                            'cancelled' => 'İptal Edildi',
                            default => $state ?? '-',
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'draft' => 'gray',
                            'pending' => 'warning',
                            'confirmed' => 'info',
                            'preparing' => 'primary',
                            'shipped' => 'info',
                            'delivered' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray',
                        }
                    ),

                TextColumn::make('cancellation_reason')
                    ->label('İptal Nedeni')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Sipariş Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('city')
                    ->label('İl')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('district')
                    ->label('İlçe')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Son Güncelleme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            /*
            |--------------------------------------------------------------------------
            | SİPARİŞ FİLTRELERİ
            |--------------------------------------------------------------------------
            */

            ->filters([
                SelectFilter::make('status')
                    ->label('Sipariş Durumu')
                    ->options([
                        'draft' => 'Bilgiler Toplanıyor',
                        'pending' => 'Yeni Siparişler',
                        'confirmed' => 'Onaylananlar',
                        'preparing' => 'Hazırlanıyor',
                        'shipped' => 'Kargoda',
                        'delivered' => 'Teslim Edildi',
                        'cancelled' => 'İptal Edildi',
                    ])
                    ->placeholder('Tüm Siparişler'),
            ])

            ->recordActions([

                /*
                |--------------------------------------------------------------------------
                | ONAYLA
                |--------------------------------------------------------------------------
                */

                Action::make('onayla')
                    ->label('Onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(
                        fn (Order $record): bool =>
                            $record->status === 'pending'
                    )
                    ->action(function (Order $record): void {
                        try {
                            $record->update([
                                'status' => 'confirmed',
                            ]);

                            if (! $record->confirmed_notification_sent_at) {
                                self::whatsappGonder(
                                    $record,
                                    "✅ Siparişiniz onaylandı.\n\n".
                                    "Siparişiniz firma tarafından onaylandı ve işleme alındı."
                                );

                                $record->update([
                                    'confirmed_notification_sent_at' => now(),
                                ]);
                            }

                            self::basari(
                                'Sipariş onaylandı',
                                'Müşteriye WhatsApp bildirimi gönderildi.'
                            );

                        } catch (Throwable $exception) {
                            self::hata($exception);
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | HAZIRLANIYOR
                |--------------------------------------------------------------------------
                */

                Action::make('hazirlaniyor')
                    ->label('Hazırlanıyor')
                    ->icon('heroicon-o-cube')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(
                        fn (Order $record): bool =>
                            $record->status === 'confirmed'
                    )
                    ->action(function (Order $record): void {
                        try {
                            $record->update([
                                'status' => 'preparing',
                            ]);

                            if (! $record->preparing_notification_sent_at) {
                                self::whatsappGonder(
                                    $record,
                                    "📦 Siparişiniz hazırlanıyor.\n\n".
                                    "Siparişiniz özenle hazırlanıyor. ".
                                    "Kargoya verildiğinde takip bilgileri sizinle paylaşılacaktır."
                                );

                                $record->update([
                                    'preparing_notification_sent_at' => now(),
                                ]);
                            }

                            self::basari(
                                'Sipariş hazırlanıyor',
                                'Müşteriye WhatsApp bildirimi gönderildi.'
                            );

                        } catch (Throwable $exception) {
                            self::hata($exception);
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | KARGOYA VER
                |--------------------------------------------------------------------------
                */

                Action::make('kargoyaVer')
                    ->label('Kargoya Ver')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(
                        fn (Order $record): bool =>
                            $record->status === 'preparing'
                    )
                    ->modalHeading('Siparişi Kargoya Ver')
                    ->modalDescription(
                        'Kargo bilgilerini girin. Müşteriye otomatik WhatsApp mesajı gönderilecektir.'
                    )
                    ->modalSubmitActionLabel('Kargoya Ver')
                    ->schema([

                        TextInput::make('shipping_company')
                            ->label('Kargo Firması')
                            ->placeholder('Örn: Aras Kargo')
                            ->required(),

                        TextInput::make('tracking_number')
                            ->label('Takip Numarası')
                            ->required(),

                        TextInput::make('tracking_url')
                            ->label('Takip Linki')
                            ->url()
                            ->placeholder('https://...')
                            ->nullable(),
                    ])
                    ->action(function (
                        Order $record,
                        array $data
                    ): void {
                        try {
                            $record->update([
                                'shipping_company' => $data['shipping_company'],
                                'tracking_number' => $data['tracking_number'],
                                'tracking_url' => $data['tracking_url'] ?? null,
                                'status' => 'shipped',
                            ]);

                            $record->refresh();

                            if (! $record->shipping_notification_sent_at) {
                                $message =
                                    "🚚 Siparişiniz kargoya verildi.\n\n".
                                    "Kargo Firması: {$record->shipping_company}\n".
                                    "Takip Numarası: {$record->tracking_number}";

                                if (! blank($record->tracking_url)) {
                                    $message .=
                                        "\nTakip Linki: {$record->tracking_url}";
                                }

                                $message .=
                                    "\n\nSiparişinizin durumunu kargo firması üzerinden takip edebilirsiniz.";

                                self::whatsappGonder(
                                    $record,
                                    $message
                                );

                                $record->update([
                                    'shipping_notification_sent_at' => now(),
                                ]);
                            }

                            self::basari(
                                'Sipariş kargoya verildi',
                                'Kargo bilgileri müşteriye WhatsApp üzerinden gönderildi.'
                            );

                        } catch (Throwable $exception) {
                            self::hata($exception);
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | TESLİM EDİLDİ
                |--------------------------------------------------------------------------
                */

                Action::make('teslimEdildi')
                    ->label('Teslim Edildi')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(
                        fn (Order $record): bool =>
                            $record->status === 'shipped'
                    )
                    ->action(function (Order $record): void {
                        try {
                            $record->update([
                                'status' => 'delivered',
                            ]);

                            if (! $record->delivered_notification_sent_at) {
                                self::whatsappGonder(
                                    $record,
                                    "✅ Siparişiniz teslim edildi.\n\n".
                                    "Bizi tercih ettiğiniz için teşekkür ederiz. ".
                                    "Siparişinizle ilgili herhangi bir konuda bize tekrar yazabilirsiniz."
                                );

                                $record->update([
                                    'delivered_notification_sent_at' => now(),
                                ]);
                            }

                            self::basari(
                                'Sipariş teslim edildi',
                                'Müşteriye WhatsApp bildirimi gönderildi.'
                            );

                        } catch (Throwable $exception) {
                            self::hata($exception);
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | İPTAL ET
                |--------------------------------------------------------------------------
                */

                Action::make('iptalEt')
                    ->label('İptal Et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(
                        fn (Order $record): bool =>
                            ! in_array(
                                $record->status,
                                ['delivered', 'cancelled'],
                                true
                            )
                    )
                    ->modalHeading('Siparişi İptal Et')
                    ->modalDescription(
                        'İptal nedenini yazın. Bu bilgi kaydedilecek ve müşteriye WhatsApp ile bildirilecektir.'
                    )
                    ->modalSubmitActionLabel('Siparişi İptal Et')
                    ->schema([

                        Textarea::make('cancellation_reason')
                            ->label('İptal Nedeni')
                            ->placeholder(
                                'Örn: Müşteri talebi üzerine iptal edildi.'
                            )
                            ->rows(4)
                            ->required()
                            ->minLength(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (
                        Order $record,
                        array $data
                    ): void {
                        try {
                            $reason = trim(
                                (string) $data['cancellation_reason']
                            );

                            $record->update([
                                'status' => 'cancelled',
                                'cancellation_reason' => $reason,
                            ]);

                            $record->refresh();

                            if (! $record->cancelled_notification_sent_at) {
                                $message =
                                    "❌ Siparişiniz iptal edildi.\n\n".
                                    "İptal Nedeni: {$reason}\n\n".
                                    "Siparişinizle ilgili bilgi almak veya yeniden sipariş oluşturmak isterseniz bize yazabilirsiniz.";

                                self::whatsappGonder(
                                    $record,
                                    $message
                                );

                                $record->update([
                                    'cancelled_notification_sent_at' => now(),
                                ]);
                            }

                            self::basari(
                                'Sipariş iptal edildi',
                                'İptal nedeni kaydedildi ve müşteriye WhatsApp bildirimi gönderildi.'
                            );

                        } catch (Throwable $exception) {
                            self::hata($exception);
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | DÜZENLE
                |--------------------------------------------------------------------------
                */

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

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP MESAJ GÖNDER
    |--------------------------------------------------------------------------
    */

    private static function whatsappGonder(
        Order $order,
        string $message
    ): void {
        $aiBot = $order->aiBot;

        if (
            blank($order->whatsapp_number)
            || ! $aiBot
            || blank($aiBot->whatsapp_instance)
        ) {
            throw new \Exception(
                'WhatsApp numarası veya bağlı WhatsApp instance bulunamadı.'
            );
        }

        app(WhatsAppService::class)->sendText(
            instanceName: $aiBot->whatsapp_instance,
            number: $order->whatsapp_number,
            text: $message,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BAŞARI BİLDİRİMİ
    |--------------------------------------------------------------------------
    */

    private static function basari(
        string $title,
        string $body
    ): void {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    /*
    |--------------------------------------------------------------------------
    | HATA BİLDİRİMİ
    |--------------------------------------------------------------------------
    */

    private static function hata(
        Throwable $exception
    ): void {
        report($exception);

        Notification::make()
            ->title('İşlem tamamlanamadı')
            ->body($exception->getMessage())
            ->danger()
            ->send();
    }
}