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
use Throwable;

#[Signature('wai:send-follow-up-notifications')]
#[Description('Takip zamanı gelen ve öncelikli müşteriler için WAI panel bildirimi gönderir.')]
class SendFollowUpNotifications extends Command
{
    private const PRIORITY_SCORE = 85;

    public function handle(): int
    {
        $prioritySentCount =
            $this->sendPriorityLeadNotifications();

        $followUpSentCount =
            $this->sendFollowUpNotifications();

        $this->info(
            $prioritySentCount
            .' öncelikli lead bildirimi, '
            .$followUpSentCount
            .' takip bildirimi gönderildi.'
        );

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | ÖNCELİKLİ LEAD BİLDİRİMLERİ
    |--------------------------------------------------------------------------
    |
    | 85+ puanlı açık fırsatlar satış ekibine ayrı bir bildirim olarak gider.
    | Bu bildirim normal takip bildiriminden bağımsızdır.
    |
    */

    private function sendPriorityLeadNotifications(): int
    {
        $customers = ConversationControl::query()
            ->with([
                'user',
                'assignedUser',
                'aiBot',
            ])
            ->where(
                'lead_score',
                '>=',
                self::PRIORITY_SCORE
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            )
            ->orderByDesc(
                'lead_score'
            )
            ->limit(500)
            ->get();

        if ($customers->isEmpty()) {
            return 0;
        }

        $sentCount = 0;

        foreach ($customers as $customer) {
            $recipient =
                $customer->assignedUser
                ?: $customer->user;

            if (! $recipient) {
                Log::warning(
                    'WAI PRIORITY LEAD RECIPIENT NOT FOUND',
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
            | AYNI ÖNCELİKLİ LEAD İÇİN SÜREKLİ BİLDİRİM GÖNDERME
            |--------------------------------------------------------------------------
            |
            | Aynı müşteri için 7 gün içinde tekrar öncelikli lead bildirimi
            | göndermiyoruz.
            |
            | Takip bildirimi ayrı cache anahtarı kullandığı için yine çalışır.
            |
            */

            $cacheKey =
                'wai:priority-lead-notification:'
                .$customer->id
                .':'
                .$recipient->id;

            if (Cache::has($cacheKey)) {
                continue;
            }

            $customerName =
                $this->customerName(
                    $customer
                );

            $customerUrl =
                $this->customerUrl(
                    $customer
                );

            $score =
                max(
                    0,
                    min(
                        100,
                        (int) $customer->lead_score
                    )
                );

            $companyName =
                trim(
                    (string) (
                        $customer->company_name
                        ?: $customer->aiBot?->company_name
                    )
                );

            $body =
                $customerName
                .' şu anda '
                .$score
                .'/100 lead puanına sahip. '
                .'Satın alma niyeti çok yüksek görünüyor. '
                .'Hızlı şekilde ilgilenmeniz önerilir.';

            if ($companyName !== '') {
                $body .=
                    ' Firma: '
                    .$companyName
                    .'.';
            }

            try {
                Notification::make()
                    ->title(
                        '🔥 Öncelikli Lead: '
                        .$customerName
                    )
                    ->body(
                        $body
                    )
                    ->danger()
                    ->actions([
                        Action::make(
                            'customer'
                        )
                            ->label(
                                'Hemen Müşteriyi Aç'
                            )
                            ->url(
                                $customerUrl
                            ),
                    ])
                    ->sendToDatabase(
                        $recipient
                    );

                Cache::put(
                    $cacheKey,
                    true,
                    now()->addDays(7)
                );

                $sentCount++;

                Log::info(
                    'WAI PRIORITY LEAD NOTIFICATION SENT',
                    [
                        'conversation_control_id' =>
                            $customer->id,

                        'recipient_user_id' =>
                            $recipient->id,

                        'customer_name' =>
                            $customerName,

                        'lead_score' =>
                            $score,

                        'lead_status' =>
                            $customer->lead_status,

                        'lead_temperature' =>
                            $customer->lead_temperature,
                    ]
                );
            } catch (Throwable $exception) {
                Log::error(
                    'WAI PRIORITY LEAD NOTIFICATION FAILED',
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

        return $sentCount;
    }

    /*
    |--------------------------------------------------------------------------
    | NORMAL TAKİP BİLDİRİMLERİ
    |--------------------------------------------------------------------------
    */

    private function sendFollowUpNotifications(): int
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

        if ($customers->isEmpty()) {
            return 0;
        }

        $sentCount = 0;

        foreach ($customers as $customer) {
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

            if (Cache::has($cacheKey)) {
                continue;
            }

            $customerName =
                $this->customerName(
                    $customer
                );

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

            $followUpTime =
                $customer->next_follow_up_at
                    ? $customer
                        ->next_follow_up_at
                        ->format('d.m.Y H:i')
                    : '-';

            $customerUrl =
                $this->customerUrl(
                    $customer
                );

            $score =
                max(
                    0,
                    min(
                        100,
                        (int) $customer->lead_score
                    )
                );

            try {
                $notification =
                    Notification::make()
                        ->title(
                            $score >= self::PRIORITY_SCORE
                                ? '🔥 Öncelikli müşteri takip zamanı'
                                : 'Müşteri takip zamanı geldi'
                        )
                        ->body(
                            $customerName
                            .' için planlanan takip zamanı '
                            .$followUpTime
                            .'. '
                            .$leadTemperature
                            .' — '
                            .$score
                            .'/100.'
                        )
                        ->actions([
                            Action::make(
                                'customer'
                            )
                                ->label(
                                    $score >= self::PRIORITY_SCORE
                                        ? 'Hemen Müşteriyi Aç'
                                        : 'Müşteriyi Aç'
                                )
                                ->url(
                                    $customerUrl
                                ),
                        ]);

                if ($score >= self::PRIORITY_SCORE) {
                    $notification->danger();
                } else {
                    $notification->warning();
                }

                $notification
                    ->sendToDatabase(
                        $recipient
                    );

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

                        'lead_score' =>
                            $score,

                        'lead_temperature' =>
                            $customer->lead_temperature,

                        'next_follow_up_at' =>
                            $customer
                                ->next_follow_up_at
                                ?->toDateTimeString(),
                    ]
                );
            } catch (Throwable $exception) {
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

        return $sentCount;
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ ADI
    |--------------------------------------------------------------------------
    */

    private function customerName(
        ConversationControl $customer
    ): string {
        $customerName =
            trim(
                (string) $customer->customer_name
            );

        if ($customerName !== '') {
            return $customerName;
        }

        return
            $customer->whatsapp_number
            ?: 'Müşteri';
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ URL
    |--------------------------------------------------------------------------
    */

    private function customerUrl(
        ConversationControl $customer
    ): string {
        return url(
            '/admin/musteriler'
            .'?customer='
            .$customer->id
        );
    }
}