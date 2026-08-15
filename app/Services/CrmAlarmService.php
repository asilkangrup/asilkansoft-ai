<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmAlarm;
use Illuminate\Support\Collection;

class CrmAlarmService
{
    /*
    |--------------------------------------------------------------------------
    | ALARM OLUŞTUR / GÜNCELLE
    |--------------------------------------------------------------------------
    */

    public function createOrGet(
        ConversationControl $conversation,
        string $type,
        string $severity,
        int $priorityScore,
        string $title,
        string $message,
        ?string $recommendedAction,
        string $fingerprint
    ): CrmAlarm {
        $priorityScore =
            max(
                0,
                min(
                    100,
                    $priorityScore
                )
            );

        $alarm =
            CrmAlarm::query()
                ->where(
                    'fingerprint',
                    $fingerprint
                )
                ->first();

        if ($alarm) {
            $wasResolved =
                (bool) $alarm->is_resolved;

            if (
                $alarm->snoozed_until
                && $alarm->snoozed_until->isPast()
            ) {
                $alarm->snoozed_until =
                    null;
            }

            $alarm->fill([
                'severity' =>
                    $severity,

                'priority_score' =>
                    $priorityScore,

                'title' =>
                    $title,

                'message' =>
                    $message,

                'recommended_action' =>
                    $recommendedAction,
            ]);

            /*
            |--------------------------------------------------------------------------
            | AYNI KOŞUL TEKRAR OLUŞTUYSA YENİDEN AÇ
            |--------------------------------------------------------------------------
            */

            if ($wasResolved) {
                $alarm->is_resolved =
                    false;

                $alarm->resolved_at =
                    null;

                $alarm->notified_at =
                    null;

                $alarm->snoozed_until =
                    null;
            }

            $alarm->save();
            $alarm->refresh();

            if ($wasResolved) {
                app(
                    CrmAlarmAuditService::class
                )->record(
                    alarm: $alarm,
                    eventType: 'reopened',
                    description: 'Alarm koşulu yeniden oluştuğu için sistem tarafından yeniden açıldı.',
                );
            }

            return $alarm;
        }

        /*
        |--------------------------------------------------------------------------
        | YENİ ALARM
        |--------------------------------------------------------------------------
        */

        $alarm =
            CrmAlarm::query()
                ->create([
                    'user_id' =>
                        $conversation->user_id,

                    'conversation_control_id' =>
                        $conversation->id,

                    'type' =>
                        $type,

                    'severity' =>
                        $severity,

                    'priority_score' =>
                        $priorityScore,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'recommended_action' =>
                        $recommendedAction,

                    'fingerprint' =>
                        $fingerprint,

                    'is_resolved' =>
                        false,

                    'resolved_at' =>
                        null,

                    'notified_at' =>
                        null,

                    'snoozed_until' =>
                        null,
                ]);

        app(
            CrmAlarmAuditService::class
        )->record(
            alarm: $alarm,
            eventType: 'created',
            description: 'Alarm otomatik olarak oluşturuldu.',
            meta: [
                'priority_score' =>
                    $priorityScore,

                'type' =>
                    $type,

                'severity' =>
                    $severity,
            ],
        );

        return $alarm;
    }

    /*
    |--------------------------------------------------------------------------
    | BİLDİRİLDİ
    |--------------------------------------------------------------------------
    */

    public function markNotified(
        CrmAlarm $alarm
    ): void {
        if ($alarm->notified_at) {
            return;
        }

        $alarm->update([
            'notified_at' =>
                now(),
        ]);

        app(
            CrmAlarmAuditService::class
        )->record(
            alarm: $alarm,
            eventType: 'notified',
            description: 'Panel bildirimi gönderildi.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ALARM ÇÖZ
    |--------------------------------------------------------------------------
    */

    public function resolve(
        CrmAlarm $alarm
    ): void {
        if ($alarm->is_resolved) {
            return;
        }

        $alarm->update([
            'is_resolved' =>
                true,

            'resolved_at' =>
                now(),
        ]);

        app(
            CrmAlarmAuditService::class
        )->record(
            alarm: $alarm,
            eventType: 'resolved',
            description: 'Alarm CRM koşulu ortadan kalktığı için otomatik çözüldü.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİNİN AKTİF ALARMLARI
    |--------------------------------------------------------------------------
    */

    public function activeAlarmsForConversation(
        ConversationControl $conversation
    ): Collection {
        return CrmAlarm::query()
            ->where(
                'conversation_control_id',
                $conversation->id
            )
            ->where(
                'is_resolved',
                false
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | KAPANMIŞ SATIŞ ALARMLARI
    |--------------------------------------------------------------------------
    */

    public function resolveClosedConversationAlarms(
        ConversationControl $conversation
    ): void {
        if (
            ! in_array(
                $conversation->lead_status,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            return;
        }

        $alarms =
            $this->activeAlarmsForConversation(
                $conversation
            );

        foreach ($alarms as $alarm) {
            $this->resolve(
                $alarm
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GERÇEK CRM DURUMUYLA SENKRONLA
    |--------------------------------------------------------------------------
    */

    public function reconcileConversation(
        ConversationControl $conversation
    ): int {
        $activeAlarms =
            $this->activeAlarmsForConversation(
                $conversation
            );

        if ($activeAlarms->isEmpty()) {
            return 0;
        }

        if (
            in_array(
                $conversation->lead_status,
                [
                    'won',
                    'lost',
                ],
                true
            )
        ) {
            $count =
                $activeAlarms->count();

            foreach ($activeAlarms as $alarm) {
                $this->resolve(
                    $alarm
                );
            }

            return $count;
        }

        $resolvedCount =
            0;

        foreach ($activeAlarms as $alarm) {
            $shouldResolve =
                match (
                    $alarm->type
                ) {
                    'critical_lead' =>
                        $this->criticalLeadResolved(
                            $conversation
                        ),

                    'hot_follow_up_overdue' =>
                        $this->hotFollowUpResolved(
                            $conversation
                        ),

                    'risk_lead' =>
                        $this->riskLeadResolved(
                            $conversation
                        ),

                    'proposal_silent' =>
                        $this->silentProposalResolved(
                            $conversation
                        ),

                    default =>
                        false,
                };

            if (! $shouldResolve) {
                continue;
            }

            $this->resolve(
                $alarm
            );

            $resolvedCount++;
        }

        return $resolvedCount;
    }

    /*
    |--------------------------------------------------------------------------
    | KRİTİK LEAD ÇÖZÜLDÜ MÜ?
    |--------------------------------------------------------------------------
    */

    private function criticalLeadResolved(
        ConversationControl $conversation
    ): bool {
        return
            (int) $conversation->lead_score
            < 95;
    }

    /*
    |--------------------------------------------------------------------------
    | SICAK LEAD TAKİBİ ÇÖZÜLDÜ MÜ?
    |--------------------------------------------------------------------------
    */

    private function hotFollowUpResolved(
        ConversationControl $conversation
    ): bool {
        if (
            $conversation->lead_temperature
            !== 'hot'
        ) {
            return true;
        }

        if (
            ! $conversation->next_follow_up_at
        ) {
            return true;
        }

        return
            $conversation->next_follow_up_at
            >
            now()->subHours(3);
    }

    /*
    |--------------------------------------------------------------------------
    | RİSKLİ LEAD ÇÖZÜLDÜ MÜ?
    |--------------------------------------------------------------------------
    */

    private function riskLeadResolved(
        ConversationControl $conversation
    ): bool {
        $tags =
            is_array(
                $conversation->tags
            )
                ? $conversation->tags
                : [];

        $hasRiskTag =
            in_array(
                'Riskli Lead',
                $tags,
                true
            );

        if (! $hasRiskTag) {
            return true;
        }

        return
            (int) $conversation->lead_score
            < 70;
    }

    /*
    |--------------------------------------------------------------------------
    | SESSİZ TEKLİF ÇÖZÜLDÜ MÜ?
    |--------------------------------------------------------------------------
    */

    private function silentProposalResolved(
        ConversationControl $conversation
    ): bool {
        if (
            $conversation->lead_status
            !== 'proposal'
        ) {
            return true;
        }

        if (
            ! $conversation->last_contact_at
        ) {
            return true;
        }

        return
            $conversation->last_contact_at
            >
            now()->subHours(24);
    }
}