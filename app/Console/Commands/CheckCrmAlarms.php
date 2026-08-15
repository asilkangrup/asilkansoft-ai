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
#[Description('WAI CRM kritik satış alarmlarını kontrol eder, kaydeder ve yöneticilere panel bildirimi gönderir.')]
class CheckCrmAlarms extends Command
{
    private const CRITICAL_SCORE = 95;

    private const RISK_MIN_SCORE = 70;

    private const HOT_FOLLOW_UP_DELAY_HOURS = 3;

    private const PROPOSAL_SILENCE_HOURS = 24;

    public function handle(
        CrmAlarmService $alarmService
    ): int {
        $sent = 0;

        $sent += $this->criticalLeadAlarms(
            $alarmService
        );

        $sent += $this->overdueHotLeadAlarms(
            $alarmService
        );

        $sent += $this->riskLeadAlarms(
            $alarmService
        );

        $sent += $this->silentProposalAlarms(
            $alarmService
        );

        $this->resolveClosedAlarms(
            $alarmService
        );

        $this->info(
            $sent
            .' WAI CRM alarmı gönderildi.'
        );

        return self::SUCCESS;
    }

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
                    ['won', 'lost']
                )
                ->orderByDesc('lead_score')
                ->limit(500)
                ->get();

        $sent = 0;

        foreach ($customers as $customer) {
            $title = 'Kritik satış fırsatı';

            $message =
                $this->customerName($customer)
                .' '
                .(int) $customer->lead_score
                .'/100 puana ulaştı. Satış ekibinin hızlı aksiyon alması önerilir.';

            $fingerprint =
                'critical_lead:'
                .$customer->id
                .':'
                .(int) $customer->lead_score;

            $sent += $this->persistAndNotify(
                alarmService: $alarmService,
                customer: $customer,
                type: 'critical_lead',
                severity: 'critical',
                title: $title,
                message: $message,
                fingerprint: $fingerprint,
            );
        }

        return $sent;
    }

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
                    ['won', 'lost']
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

        $sent = 0;

        foreach ($customers as $customer) {
            $title =
                'Sıcak lead takibi gecikti';

            $message =
                $this->customerName($customer)
                .' sıcak lead durumunda ve takip zamanı '
                .self::HOT_FOLLOW_UP_DELAY_HOURS
                .'+ saat gecikti.';

            $fingerprint =
                'hot_follow_up_overdue:'
                .$customer->id
                .':'
                .(
                    $customer->next_follow_up_at
                        ?->timestamp
                    ?? 0
                );

            $sent += $this->persistAndNotify(
                alarmService: $alarmService,
                customer: $customer,
                type: 'hot_follow_up_overdue',
                severity: 'critical',
                title: $title,
                message: $message,
                fingerprint: $fingerprint,
            );
        }

        return $sent;
    }

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
                    ['won', 'lost']
                )
                ->orderByDesc(
                    'lead_score'
                )
                ->limit(500)
                ->get();

        $sent = 0;

        foreach ($customers as $customer) {
            $title =
                'Değerli lead kayıp riski taşıyor';

            $message =
                $this->customerName($customer)
                .' Riskli Lead olarak işaretlendi ve puanı '
                .(int) $customer->lead_score
                .'/100. Müşterinin yeniden ele alınması önerilir.';

            $fingerprint =
                'risk_lead:'
                .$customer->id
                .':'
                .(int) $customer->lead_score;

            $sent += $this->persistAndNotify(
                alarmService: $alarmService,
                customer: $customer,
                type: 'risk_lead',
                severity: 'warning',
                title: $title,
                message: $message,
                fingerprint: $fingerprint,
            );
        }

        return $sent;
    }

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

        $sent = 0;

        foreach ($customers as $customer) {
            $title =
                'Teklif sonrası müşteri sessiz';

            $message =
                $this->customerName($customer)
                .' teklif aşamasında ve '
                .self::PROPOSAL_SILENCE_HOURS
                .'+ saattir yeni temas yok.';

            $fingerprint =
                'proposal_silent:'
                .$customer->id
                .':'
                .(
                    $customer->last_contact_at
                        ?->timestamp
                    ?? 0
                );

            $sent += $this->persistAndNotify(
                alarmService: $alarmService,
                customer: $customer,
                type: 'proposal_silent',
                severity: 'warning',
                title: $title,
                message: $message,
                fingerprint: $fingerprint,
            );
        }

        return $sent;
    }

    private function persistAndNotify(
        CrmAlarmService $alarmService,
        ConversationControl $customer,
        string $type,
        string $severity,
        string $title,
        string $message,
        string $fingerprint
    ): int {
        $recipient =
            $customer->user;

        if (! $recipient) {
            return 0;
        }

        try {
            $alarm =
                $alarmService->createOrGet(
                    conversation: $customer,
                    type: $type,
                    severity: $severity,
                    title: $title,
                    message: $message,
                    fingerprint: $fingerprint,
                );

            /*
            |--------------------------------------------------------------------------
            | DAHA ÖNCE BİLDİRİLDİYSE TEKRAR GÖNDERME
            |--------------------------------------------------------------------------
            */

            if ($alarm->notified_at) {
                return 0;
            }

            $notification =
                Notification::make()
                    ->title(
                        $title
                    )
                    ->body(
                        $message
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

            if ($severity === 'critical') {
                $notification->danger();
            } else {
                $notification->warning();
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

    private function resolveClosedAlarms(
        CrmAlarmService $alarmService
    ): void {
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
            return;
        }

        ConversationControl::query()
            ->whereIn(
                'id',
                $conversationIds
            )
            ->whereIn(
                'lead_status',
                ['won', 'lost']
            )
            ->get()
            ->each(
                fn (
                    ConversationControl $conversation
                ) =>
                    $alarmService
                        ->resolveClosedConversationAlarms(
                            $conversation
                        )
            );
    }

    private function customerName(
        ConversationControl $customer
    ): string {
        $name =
            trim(
                (string) $customer->customer_name
            );

        return $name !== ''
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