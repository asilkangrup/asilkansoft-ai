<?php

namespace App\Console\Commands;

use App\Models\ConversationControl;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('wai:send-follow-up-notifications')]
#[Description('Takip zamanı gelen müşteriler için WAI panel bildirimi gönderir.')]
class SendFollowUpNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customers = ConversationControl::query()
            ->with([
                'user',
                'assignedUser',
                'aiBot',
            ])
            ->whereNotNull(
                'next_follow_up_at'
            )
            ->where(
                'next_follow_up_at',
                '<=',
                now()
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->orderBy(
                'next_follow_up_at'
            )
            ->limit(500)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | TAKİP YOK
        |--------------------------------------------------------------------------
        */

        if ($customers->isEmpty()) {
            $this->info(
                'Takip zamanı gelen müşteri bulunamadı.'
            );

            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($customers as $customer) {
            /*
            |--------------------------------------------------------------------------
            | BİLDİRİM ALICISI
            |--------------------------------------------------------------------------
            |
            | Müşteriye personel atanmışsa bildirim ona gider.
            | Personel atanmamışsa hesabın ana kullanıcısına gider.
            |
            */

            $recipient =
                $customer->assignedUser
                ?: $customer->user;

            if (! $recipient) {
                Log::warning(
                    'WAI FOLLOW-UP RECIPIENT NOT FOUND',
                    [
                        'conversation_control_id' =>
                            $customer->id,

                        'user_id' =>
                            $customer->user_id,

                        'assigned_user_id' =>
                            $customer->assigned_user_id,
                    ]
                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | AYNI TAKİP İÇİN TEKRAR BİLDİRİM GÖNDERME
            |--------------------------------------------------------------------------
            |
            | Cache anahtarına takip tarihini de ekliyoruz.
            |
            | Örneğin müşteri yarına ertelenirse next_follow_up_at değişeceği için
            | yeni takip zamanı geldiğinde yeniden bildirim gönderilebilir.
            |
            */

            $followUpTimestamp =
                $customer->next_follow_up_at
                    ? $customer->next_follow_up_at->timestamp
                    : 0;

            $cacheKey =
                'wai:follow-up-notification:'
                .$customer->id
                .':'
                .$followUpTimestamp
                .':'
                .$recipient->id;

            if (
                Cache::has(
                    $cacheKey
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ ADI
            |--------------------------------------------------------------------------
            */

            $customerName =
                trim(
                    (string) $customer->customer_name
                );

            if ($customerName === '') {
                $customerName =
                    $customer->whatsapp_number
                    ?: 'Müşteri';
            }

            /*
            |--------------------------------------------------------------------------
            | LEAD SICAKLIĞI
            |--------------------------------------------------------------------------
            */

            $leadTemperature =
                match (
                    $customer->lead_temperature
                ) {
                    'hot' =>
                        'Sıcak Lead',

                    'warm' =>
                        'Ilık Lead',

                    default =>
                        'Soğuk Lead',
                };

            /*
            |--------------------------------------------------------------------------
            | TAKİP TARİHİ
            |--------------------------------------------------------------------------
            */

            $followUpTime =
                $customer->next_follow_up_at
                    ? $customer
                        ->next_follow_up_at
                        ->format('d.m.Y H:i')
                    : '-';

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ URL
            |--------------------------------------------------------------------------
            */

            $customerUrl =
                url(
                    '/admin/musteriler'
                    .'?customer='
                    .$customer->id
                );

            /*
            |--------------------------------------------------------------------------
            | FİLAMENT DATABASE NOTIFICATION
            |--------------------------------------------------------------------------
            */

            try {
                Notification::make()
                    ->title(
                        'Müşteri takip zamanı geldi'
                    )
                    ->body(
                        $customerName
                        .' için planlanan takip zamanı '
                        .$followUpTime
                        .'. '
                        .$leadTemperature
                        .'.'
                    )
                    ->warning()
                    ->actions([
                        Action::make(
                            'customer'
                        )
                            ->label(
                                'Müşteriyi Aç'
                            )
                            ->url(
                                $customerUrl
                            ),
                    ])
                    ->sendToDatabase(
                        $recipient
                    );

                /*
                |--------------------------------------------------------------------------
                | CACHE
                |--------------------------------------------------------------------------
                |
                | Aynı takip tarihi için 7 gün boyunca ikinci bildirim gönderilmez.
                |
                */

                Cache::put(
                    $cacheKey,
                    true,
                    now()->addDays(7)
                );

                $sentCount++;

                Log::info(
                    'WAI FOLLOW-UP NOTIFICATION SENT',
                    [
                        'conversation_control_id' =>
                            $customer->id,

                        'recipient_user_id' =>
                            $recipient->id,

                        'customer_name' =>
                            $customerName,

                        'lead_temperature' =>
                            $customer->lead_temperature,

                        'next_follow_up_at' =>
                            $customer
                                ->next_follow_up_at
                                ?->toDateTimeString(),
                    ]
                );
            } catch (\Throwable $exception) {
                /*
                |--------------------------------------------------------------------------
                | TEK BİR BİLDİRİM TÜM COMMAND'İ DURDURMASIN
                |--------------------------------------------------------------------------
                */

                Log::error(
                    'WAI FOLLOW-UP NOTIFICATION FAILED',
                    [
                        'conversation_control_id' =>
                            $customer->id,

                        'recipient_user_id' =>
                            $recipient->id,

                        'message' =>
                            $exception->getMessage(),
                    ]
                );
            }
        }

        $this->info(
            $sentCount
            .' takip bildirimi gönderildi.'
        );

        return self::SUCCESS;
    }
}