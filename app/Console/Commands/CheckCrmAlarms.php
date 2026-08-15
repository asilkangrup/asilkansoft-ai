<?php

namespace App\Console\Commands;

use App\Models\ConversationControl;
use App\Models\CrmAlarm;
use App\Services\CrmAlarmService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('wai:check-crm-alarms')]
#[Description('WAI CRM kritik satış alarmlarını kontrol eder, önceliklendirir, kaydeder ve yöneticilere bildirir.')]
class CheckCrmAlarms extends Command
{
    private const CRITICAL_SCORE = 95;

    private const RISK_MIN_SCORE = 70;

    private const HOT_FOLLOW_UP_DELAY_HOURS = 3;

    private const PROPOSAL_SILENCE_HOURS = 24;

    public function handle(
        CrmAlarmService $alarmService
    ): int {
        $resolved =
            $this->reconcileActiveAlarms(
                $alarmService
            );

        $sent =
            0;

        $sent +=
            $this->criticalLeadAlarms(
                $alarmService
            );

        $sent +=
            $this->overdueHotLeadAlarms(
                $alarmService
            );

        $sent +=
            $this->riskLeadAlarms(
                $alarmService
            );

        $sent +=
            $this->silentProposalAlarms(
                $alarmService
            );

        $this->info(
            $sent
            .' WAI CRM alarmı gönderildi.'
        );

        $this->info(
            $resolved
            .' CRM alarmı otomatik çözüldü.'
        );

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | AKTİF ALARMLARI SENKRONLA
    |--------------------------------------------------------------------------
    */

    private function reconcileActiveAlarms(
        CrmAlarmService $alarmService
    ): int {
        $conversationIds =
            CrmAlarm::query()
                ->where(
                    'is_resolved',
                    false
                )
                ->distinct()
                ->pluck(
                    'conversation_control_id'
                );

        if ($conversationIds->isEmpty()) {
            return 0;
        }

        $resolved =
            0;

        ConversationControl::query()
            ->whereIn(
                'id',
                $conversationIds
            )
            ->get()
            ->each(
                function (
                    ConversationControl $conversation
                ) use (
                    $alarmService,
                    &$resolved
                ): void {
                    $resolved +=
                        $alarmService
                            ->reconcileConversation(
                                $conversation
                            );
                }
            );

        return $resolved;
    }

    /*
    |--------------------------------------------------------------------------
    | KRİTİK LEAD
    |--------------------------------------------------------------------------
    */

    private function criticalLeadAlarms(
        CrmAlarmService $alarmService
    ): int {
        $customers =
            ConversationControl::query()
                ->with('user')
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
            $leadScore =
                max(
                    0,
                    min(
                        100,
                        (int) $customer->lead_score
                    )
                );

            $priorityScore =
                max(
                    95,
                    $leadScore
                );

            $title =
                'Kritik satış fırsatı';

            $message =
                $this->customerName(
                    $customer
                )
                .' '
                .$leadScore
                .'/100 puana ulaştı. Satış ekibinin hızlı aksiyon alması önerilir.';

            $recommendedAction =
                'Müşteriye mümkün olan en kısa sürede dönüş yapın. '
                .'Satın alma niyetini, fiyat/ödeme itirazlarını ve kapanış için eksik kalan bilgiyi netleştirin.';

            $fingerprint =
                'critical_lead:'
                .$customer->id
                .':'
                .$leadScore;

            $sent +=
                $this->persistAndNotify(
                    alarmService:
                        $alarmService,

                    customer:
                        $customer,

                    type:
                        'critical_lead',

                    severity:
                        'critical',

                    priorityScore:
                        $priorityScore,

                    title:
                        $title,

                    message:
                        $message,

                    recommendedAction:
                        $recommendedAction,

                    fingerprint:
                        $fingerprint,
                );
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | GECİKMİŞ SICAK LEAD
    |--------------------------------------------------------------------------
    */

    private function overdueHotLeadAlarms(
        CrmAlarmService $alarmService
    ): int {
        $deadline =
            now()->subHours(
                self::HOT_FOLLOW_UP_DELAY_HOURS
            );

        $customers =
            ConversationControl::query()
                ->with('user')
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
            $hoursOverdue =
                max(
                    3,
                    (int) floor(
                        $customer
                            ->next_follow_up_at
                            ->diffInMinutes(
                                now()
                            )
                        / 60
                    )
                );

            $priorityScore =
                min(
                    100,
                    85
                    + min(
                        15,
                        $hoursOverdue
                    )
                );

            $title =
                'Sıcak lead takibi gecikti';

            $message =
                $this->customerName(
                    $customer
                )
                .' sıcak lead durumunda ve takip zamanı yaklaşık '
                .$hoursOverdue
                .' saat gecikti.';

            $recommendedAction =
                'Bu müşteriyi öncelikli takip listesine alın ve bugün yeniden temas kurun. '
                .'Önceki konuşmadaki satın alma sinyaline göre tek ve net bir sonraki adım önerin.';

            $fingerprint =
                'hot_follow_up_overdue:'
                .$customer->id
                .':'
                .(
                    $customer->next_follow_up_at
                        ?->timestamp
                    ?? 0
                );

            $sent +=
                $this->persistAndNotify(
                    alarmService:
                        $alarmService,

                    customer:
                        $customer,

                    type:
                        'hot_follow_up_overdue',

                    severity:
                        'critical',

                    priorityScore:
                        $priorityScore,

                    title:
                        $title,

                    message:
                        $message,

                    recommendedAction:
                        $recommendedAction,

                    fingerprint:
                        $fingerprint,
                );
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | RİSKLİ LEAD
    |--------------------------------------------------------------------------
    */

    private function riskLeadAlarms(
        CrmAlarmService $alarmService
    ): int {
        $customers =
            ConversationControl::query()
                ->with('user')
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
            $leadScore =
                max(
                    0,
                    min(
                        100,
                        (int) $customer->lead_score
                    )
                );

            $priorityScore =
                min(
                    95,
                    max(
                        75,
                        $leadScore + 8
                    )
                );

            $title =
                'Değerli lead kayıp riski taşıyor';

            $message =
                $this->customerName(
                    $customer
                )
                .' Riskli Lead olarak işaretlendi ve puanı '
                .$leadScore
                .'/100.';

            $recommendedAction =
                'Müşterinin neden ilerlemediğini belirleyin. '
                .'Fiyat, güven, teslimat, ödeme veya karar erteleme itirazlarından hangisinin geçerli olduğunu netleştirip buna göre takip yapın.';

            $fingerprint =
                'risk_lead:'
                .$customer->id
                .':'
                .$leadScore;

            $sent +=
                $this->persistAndNotify(
                    alarmService:
                        $alarmService,

                    customer:
                        $customer,

                    type:
                        'risk_lead',

                    severity:
                        'warning',

                    priorityScore:
                        $priorityScore,

                    title:
                        $title,

                    message:
                        $message,

                    recommendedAction:
                        $recommendedAction,

                    fingerprint:
                        $fingerprint,
                );
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | SESSİZ TEKLİF
    |--------------------------------------------------------------------------
    */

    private function silentProposalAlarms(
        CrmAlarmService $alarmService
    ): int {
        $deadline =
            now()->subHours(
                self::PROPOSAL_SILENCE_HOURS
            );

        $customers =
            ConversationControl::query()
                ->with('user')
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
            $silentHours =
                max(
                    24,
                    (int) floor(
                        $customer
                            ->last_contact_at
                            ->diffInMinutes(
                                now()
                            )
                        / 60
                    )
                );

            $priorityScore =
                min(
                    94,
                    78
                    + min(
                        16,
                        (int) floor(
                            (
                                $silentHours
                                - 24
                            )
                            / 6
                        )
                    )
                );

            $title =
                'Teklif sonrası müşteri sessiz';

            $message =
                $this->customerName(
                    $customer
                )
                .' teklif aşamasında ve yaklaşık '
                .$silentHours
                .' saattir yeni temas yok.';

            $recommendedAction =
                'Teklifin ulaşıp ulaşmadığını kısa bir mesajla doğrulayın. '
                .'Müşteriye tek bir karar sorusu sorun ve gerekiyorsa teklifin önündeki itirazı netleştirin.';

            $fingerprint =
                'proposal_silent:'
                .$customer->id
                .':'
                .(
                    $customer->last_contact_at
                        ?->timestamp
                    ?? 0
                );

            $sent +=
                $this->persistAndNotify(
                    alarmService:
                        $alarmService,

                    customer:
                        $customer,

                    type:
                        'proposal_silent',

                    severity:
                        'warning',

                    priorityScore:
                        $priorityScore,

                    title:
                        $title,

                    message:
                        $message,

                    recommendedAction:
                        $recommendedAction,

                    fingerprint:
                        $fingerprint,
                );
        }

        return $sent;
    }

    /*
    |--------------------------------------------------------------------------
    | KAYDET + BİLDİR
    |--------------------------------------------------------------------------
    */

    private function persistAndNotify(
        CrmAlarmService $alarmService,
        ConversationControl $customer,
        string $type,
        string $severity,
        int $priorityScore,
        string $title,
        string $message,
        ?string $recommendedAction,
        string $fingerprint
    ): int {
        $recipient =
            $customer->user;

        if (! $recipient) {
            return 0;
        }

        try {
            $alarm =
                $alarmService
                    ->createOrGet(
                        conversation:
                            $customer,

                        type:
                            $type,

                        severity:
                            $severity,

                        priorityScore:
                            $priorityScore,

                        title:
                            $title,

                        message:
                            $message,

                        recommendedAction:
                            $recommendedAction,

                        fingerprint:
                            $fingerprint,
                    );

            if (
                $alarm->snoozed_until
                && $alarm->snoozed_until->isFuture()
            ) {
                return 0;
            }

            if ($alarm->notified_at) {
                return 0;
            }

            $notification =
                Notification::make()
                    ->title(
                        $title
                    )
                    ->body(
                        'Öncelik '
                        .$priorityScore
                        .'/100 · '
                        .$message
                    )
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

                        Action::make(
                            'alarms'
                        )
                            ->label(
                                'Alarm Merkezi'
                            )
                            ->url(
                                url(
                                    '/admin/alarm-merkezi'
                                )
                            ),
                    ]);

            if (
                $severity
                === 'critical'
            ) {
                $notification
                    ->danger();
            } else {
                $notification
                    ->warning();
            }

            $notification
                ->sendToDatabase(
                    $recipient
                );

            $alarmService
                ->markNotified(
                    $alarm
                );

            Log::info(
                'WAI CRM ALARM SENT',
                [
                    'crm_alarm_id' =>
                        $alarm->id,

                    'type' =>
                        $type,

                    'priority_score' =>
                        $priorityScore,

                    'conversation_control_id' =>
                        $customer->id,

                    'recipient_user_id' =>
                        $recipient->id,
                ]
            );

            return 1;

        } catch (Throwable $exception) {
            Log::error(
                'WAI CRM ALARM FAILED',
                [
                    'type' =>
                        $type,

                    'conversation_control_id' =>
                        $customer->id,

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            return 0;
        }
    }

    private function customerName(
        ConversationControl $customer
    ): string {
        $name =
            trim(
                (string) $customer->customer_name
            );

        return
            $name !== ''
                ? $name
                : (
                    $customer->whatsapp_number
                    ?: 'Müşteri'
                );
    }

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