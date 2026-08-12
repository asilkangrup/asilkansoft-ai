<?php

namespace App\Filament\Resources\ConversationControls\Tables;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConversationControlsTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table

            /*
            |--------------------------------------------------------------------------
            | KOLONLAR
            |--------------------------------------------------------------------------
            */

            ->columns([

                /*
                |--------------------------------------------------------------------------
                | MÜŞTERİ
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'whatsapp_number'
                )
                    ->label('Müşteri')
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),

                /*
                |--------------------------------------------------------------------------
                | YAPAY ZEKÂ BOTU
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'aiBot.name'
                )
                    ->label('Yapay Zekâ')
                    ->placeholder('-')
                    ->searchable(),

                /*
                |--------------------------------------------------------------------------
                | FİRMA
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'aiBot.company_name'
                )
                    ->label('Firma')
                    ->placeholder('-')
                    ->toggleable(),

                /*
                |--------------------------------------------------------------------------
                | SON MESAJ
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'last_message'
                )
                    ->label('Son Mesaj')
                    ->state(
                        function (
                            ConversationControl $record
                        ): string {
                            $message =
                                ChatMessage::query()
                                    ->where(
                                        'ai_bot_id',
                                        $record->ai_bot_id
                                    )
                                    ->where(
                                        'session_id',
                                        $record->session_id
                                    )
                                    ->latest('id')
                                    ->value('message');

                            $message = trim(
                                (string) $message
                            );

                            return $message !== ''
                                ? $message
                                : 'Henüz mesaj yok';
                        }
                    )
                    ->limit(55)
                    ->wrap(),

                /*
                |--------------------------------------------------------------------------
                | AI / İNSAN DURUMU
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'human_takeover'
                )
                    ->label('Durum')
                    ->formatStateUsing(
                        fn (bool $state): string =>
                            $state
                                ? '👤 İnsan Yönetiyor'
                                : '🤖 AI Aktif'
                    )
                    ->badge()
                    ->color(
                        fn (bool $state): string =>
                            $state
                                ? 'warning'
                                : 'success'
                    ),

                /*
                |--------------------------------------------------------------------------
                | SON AKTİVİTE
                |--------------------------------------------------------------------------
                */

                TextColumn::make(
                    'updated_at'
                )
                    ->label('Son Aktivite')
                    ->since()
                    ->sortable(),
            ])

            /*
            |--------------------------------------------------------------------------
            | FİLTRELER
            |--------------------------------------------------------------------------
            */

            ->filters([
                //
            ])

            /*
            |--------------------------------------------------------------------------
            | SATIR AKSİYONLARI
            |--------------------------------------------------------------------------
            */

            ->recordActions([

                /*
                |--------------------------------------------------------------------------
                | İNSAN DEVRAL
                |--------------------------------------------------------------------------
                */

                Action::make(
                    'human_takeover'
                )
                    ->label('İnsan Devral')
                    ->icon(
                        'heroicon-o-hand-raised'
                    )
                    ->color('warning')
                    ->button()
                    ->visible(
                        fn (
                            ConversationControl $record
                        ): bool =>
                            ! $record->human_takeover
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Konuşmayı insan devralsın mı?'
                    )
                    ->modalDescription(
                        'Bu müşteriye yapay zekâ cevap vermeyi durduracak. Diğer müşterilerde yapay zekâ çalışmaya devam eder.'
                    )
                    ->modalSubmitActionLabel(
                        'Evet, İnsan Devral'
                    )
                    ->action(
                        function (
                            ConversationControl $record
                        ): void {
                            $record->insanDevral();
                        }
                    )
                    ->successNotificationTitle(
                        'Konuşma insan tarafından devralındı.'
                    ),

                /*
                |--------------------------------------------------------------------------
                | AI'YE GERİ VER
                |--------------------------------------------------------------------------
                */

                Action::make(
                    'release_to_ai'
                )
                    ->label('AI’ye Geri Ver')
                    ->icon(
                        'heroicon-o-cpu-chip'
                    )
                    ->color('success')
                    ->button()
                    ->visible(
                        fn (
                            ConversationControl $record
                        ): bool =>
                            (bool) $record->human_takeover
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Konuşma yapay zekâya geri verilsin mi?'
                    )
                    ->modalDescription(
                        'Bu müşterinin sonraki mesajlarına yapay zekâ yeniden otomatik cevap verecek.'
                    )
                    ->modalSubmitActionLabel(
                        'Evet, AI’ye Geri Ver'
                    )
                    ->action(
                        function (
                            ConversationControl $record
                        ): void {
                            $record
                                ->yapayZekayaGeriVer();
                        }
                    )
                    ->successNotificationTitle(
                        'Konuşma yapay zekâya geri verildi.'
                    ),
            ])

            /*
            |--------------------------------------------------------------------------
            | TOOLBAR
            |--------------------------------------------------------------------------
            |
            | Create / Delete / Bulk Delete yok.
            |
            */

            ->toolbarActions([])

            /*
            |--------------------------------------------------------------------------
            | SIRALAMA
            |--------------------------------------------------------------------------
            */

            ->defaultSort(
                'updated_at',
                'desc'
            );
    }
}