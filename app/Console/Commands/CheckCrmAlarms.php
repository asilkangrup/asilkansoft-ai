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

#[Signature('wai:check-crm-alarms')]
#[Description('WAI CRM kritik satış alarmlarını kontrol eder ve yöneticilere panel bildirimi gönderir.')]
class CheckCrmAlarms extends Command
{
    private const CRITICAL_SCORE = 95;

    private const RISK_MIN_SCORE = 70;

    private const HOT_FOLLOW_UP_DELAY_HOURS = 3;

    private const PROPOSAL_SILENCE_HOURS = 24;

    public function handle(): int
    {
        $sent =
            0;

        $sent +=
            $this->sendCriticalLeadAlarms();

        $sent +=
            $this->sendOverdueHotLeadAlarms();

        $sent +=
            $this->sendRiskLeadAlarms();

        $sent +=
            $this->sendSilentProposalAlarms();

        $this->info(
            $sent
            .' WAI CRM alarmı gönderildi.'
        );

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | 95+ KRİTİK LEAD
    |--------------------------------------------------------------------------
    */

    private function sendCriticalLeadAlarms(): int
    {
        $customers =
            ConversationControl::query()
                ->with([
                    'user',
                    'assignedUser',
                    'aiBot',
                ])
                ->where(
                    'lead_score',
                    '>=',
                    self::CRITICAL_SCORE
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

        $sent =
            0;

        foreach ($customers as $customer) {
            $recipient =
                $customer->user;

            if (! $recipient) {
                continue;
            }

            $cacheKey =
                'wai:crm-alarm:critical:'
                .$customer->id
                .':'
                .$recipient->id;

            if (
                Cache::has(
                    $cacheKey
                )
            ) {
                continue;
            }

            try {
                Notification::make()
                    ->title(
                        'Kritik satış fırsatı'
                    )
                    ->body(
                        $this->customerName(
                            $customer
                        )
                        .' '
                        .(int) $customer->lead_score
                        .'/100 puana ulaştı. '
                        .'Satış ekibinin hızlı aksiyon alması önerilir.'
                    )
                    ->danger()
                    ->actions([
                        Action::make(
                            'customer'
                        )
                            ->label(
                                'Müşteriyi Aç'
                            )
                            ->url(
                                $this->customerUrl(
                                    $customer
                                )
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

                $sent++;

                $this->logSent(
                    type: 'critical_lead',
                    customer: $customer,
                    recipientId: $recipient->id,
                );
            } catch (Throwable $exception) {
                $this->logFailed(
                    type: 'critical_lead',
                    customer: $customer,
                    recipientId: $recipient->id,
                    exception: $exception,
                );
            }
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | 3+ SAAT GECİKMİŞ SICAK LEAD
    |--------------------------------------------------------------------------
    */

    private function sendOverdueHotLeadAlarms(): int
    {
        $deadline =
            now()->subHours(
                self::HOT_FOLLOW_UP_DELAY_HOURS
            );

        $customers =
            ConversationControl::query()
                ->with([
                    'user',
                    'assignedUser',
                    'aiBot',
                ])
                ->where(
                    'lead_temperature',
                    'hot'
                )
                ->whereNotIn(
                    'lead_status',
                    [
                        'won',
                        'lost',
                    ]
                )
                ->whereNotNull(
                    'next_follow_up_at'
                )
                ->where(
                    'next_follow_up_at',
                    '<=',
                    $deadline
                )
                ->orderBy(
                    'next_follow_up_at'
                )
                ->limit(500)
                ->get();

        $sent =
            0;

        foreach ($customers as $customer) {
            $recipient =
                $customer->user;

            if (! $recipient) {
                continue;
            }

            $followUpTimestamp =
                $customer->next_follow_up_at
                    ?->timestamp
                ?? 0;

            $cacheKey =
                'wai:crm-alarm:hot-overdue:'
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

            try {
                Notification::make()
                    ->title(
                        'Sıcak lead takibi gecikti'
                    )
                    ->body(
                        $this->customerName(
                            $customer
                        )
                        .' sıcak lead durumunda ve takip zamanı '
                        .self::HOT_FOLLOW_UP_DELAY_HOURS
                        .'+ saat gecikti.'
                    )
                    ->warning()
                    ->actions([
                        Action::make(
                            'customer'
                        )
                            ->label(
                                'Hemen Aç'
                            )
                            ->url(
                                $this->customerUrl(
                                    $customer
                                )
                            ),
                    ])
                    ->sendToDatabase(
                        $recipient
                    );

                Cache::put(
                    $cacheKey,
                    true,
                    now()->addDay()
                );

                $sent++;

                $this->logSent(
                    type: 'hot_follow_up_overdue',
                    customer: $customer,
                    recipientId: $recipient->id,
                );
            } catch (Throwable $exception) {
                $this->logFailed(
                    type: 'hot_follow_up_overdue',
                    customer: $customer,
                    recipientId: $recipient->id,
                    exception: $exception,
                );
            }
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | RİSKLİ LEAD
    |--------------------------------------------------------------------------
    |
    | Riskli Lead etiketi tek başına alarm üretmez.
    | Lead puanı da en az 70 olmalıdır.
    |
    */

    private function sendRiskLeadAlarms(): int
    {
        $customers =
            ConversationControl::query()
                ->with([
                    'user',
                    'assignedUser',
                    'aiBot',
                ])
                ->whereJsonContains(
                    'tags',
                    'Riskli Lead'
                )
                ->where(
                    'lead_score',
                    '>=',
                    self::RISK_MIN_SCORE
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

        $sent =
            0;

        foreach ($customers as $customer) {
            $recipient =
                $customer->user;

            if (! $recipient) {
                continue;
            }

            $cacheKey =
                'wai:crm-alarm:risk:'
                .$customer->id
                .':'
                .$recipient->id;

            if (
                Cache::has(
                    $cacheKey
                )
            ) {
                continue;
            }

            try {
                Notification::make()
                    ->title(
                        'Değerli lead kayıp riski taşıyor'
                    )
                    ->body(
                        $this->customerName(
                            $customer
                        )
                        .' Riskli Lead olarak işaretlendi ve puanı '
                        .(int) $customer->lead_score
                        .'/100. Müşterinin yeniden ele alınması önerilir.'
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
                                $this->customerUrl(
                                    $customer
                                )
                            ),
                    ])
                    ->sendToDatabase(
                        $recipient
                    );

                Cache::put(
                    $cacheKey,
                    true,
                    now()->addDays(3)
                );

                $sent++;

                $this->logSent(
                    type: 'risk_lead',
                    customer: $customer,
                    recipientId: $recipient->id,
                );
            } catch (Throwable $exception) {
                $this->logFailed(
                    type: 'risk_lead',
                    customer: $customer,
                    recipientId: $recipient->id,
                    exception: $exception,
                );
            }
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | TEKLİF AŞAMASINDA 24+ SAAT SESSİZ
    |--------------------------------------------------------------------------
    */

    private function sendSilentProposalAlarms(): int
    {
        $deadline =
            now()->subHours(
                self::PROPOSAL_SILENCE_HOURS
            );

        $customers =
            ConversationControl::query()
                ->with([
                    'user',
                    'assignedUser',
                    'aiBot',
                ])
                ->where(
                    'lead_status',
                    'proposal'
                )
                ->whereNotNull(
                    'last_contact_at'
                )
                ->where(
                    'last_contact_at',
                    '<=',
                    $deadline
                )
                ->orderByDesc(
                    'lead_score'
                )
                ->limit(500)
                ->get();

        $sent =
            0;

        foreach ($customers as $customer) {
            $recipient =
                $customer->user;

            if (! $recipient) {
                continue;
            }

            $lastContactTimestamp =
                $customer->last_contact_at
                    ?->timestamp
                ?? 0;

            $cacheKey =
                'wai:crm-alarm:proposal-silent:'
                .$customer->id
                .':'
                .$lastContactTimestamp
                .':'
                .$recipient->id;

            if (
                Cache::has(
                    $cacheKey
                )
            ) {
                continue;
            }

            try {
                Notification::make()
                    ->title(
                        'Teklif sonrası müşteri sessiz'
                    )
                    ->body(
                        $this->customerName(
                            $customer
                        )
                        .' teklif aşamasında ve '
                        .self::PROPOSAL_SILENCE_HOURS
                        .'+ saattir yeni temas yok.'
                    )
                    ->warning()
                    ->actions([
                        Action::make(
                            'customer'
                        )
                            ->label(
                                'Takip Et'
                            )
                            ->url(
                                $this->customerUrl(
                                    $customer
                                )
                            ),
                    ])
                    ->sendToDatabase(
                        $recipient
                    );

                Cache::put(
                    $cacheKey,
                    true,
                    now()->addDay()
                );

                $sent++;

                $this->logSent(
                    type: 'proposal_silent',
                    customer: $customer,
                    recipientId: $recipient->id,
                );
            } catch (Throwable $exception) {
                $this->logFailed(
                    type: 'proposal_silent',
                    customer: $customer,
                    recipientId: $recipient->id,
                    exception: $exception,
                );
            }
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ ADI
    |--------------------------------------------------------------------------
    */

    private function customerName(
        ConversationControl $customer
    ): string {
        $name =
            trim(
                (string) $customer->customer_name
            );

        if ($name !== '') {
            return $name;
        }

        return $customer->whatsapp_number
            ?: 'Müşteri';
    }

    /*
    |--------------------------------------------------------------------------
    | CRM URL
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

    /*
    |--------------------------------------------------------------------------
    | LOG
    |--------------------------------------------------------------------------
    */

    private function logSent(
        string $type,
        ConversationControl $customer,
        int $recipientId
    ): void {
        Log::info(
            'WAI CRM ALARM SENT',
            [
                'type' =>
                    $type,

                'conversation_control_id' =>
                    $customer->id,

                'user_id' =>
                    $customer->user_id,

                'recipient_user_id' =>
                    $recipientId,

                'lead_score' =>
                    $customer->lead_score,

                'lead_status' =>
                    $customer->lead_status,

                'lead_temperature' =>
                    $customer->lead_temperature,
            ]
        );
    }

    private function logFailed(
        string $type,
        ConversationControl $customer,
        int $recipientId,
        Throwable $exception
    ): void {
        Log::error(
            'WAI CRM ALARM FAILED',
            [
                'type' =>
                    $type,

                'conversation_control_id' =>
                    $customer->id,

                'recipient_user_id' =>
                    $recipientId,

                'message' =>
                    $exception->getMessage(),
            ]
        );
    }
}