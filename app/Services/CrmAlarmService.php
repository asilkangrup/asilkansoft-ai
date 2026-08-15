<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmAlarm;
use Illuminate\Support\Collection;

class CrmAlarmService
{
    /*
    |--------------------------------------------------------------------------
    | ALARM OLUŞTUR / BUL
    |--------------------------------------------------------------------------
    */

    public function createOrGet(
        ConversationControl $conversation,
        string $type,
        string $severity,
        string $title,
        string $message,
        string $fingerprint
    ): CrmAlarm {
        return CrmAlarm::query()
            ->firstOrCreate(
                [
                    'fingerprint' =>
                        $fingerprint,
                ],
                [
                    'user_id' =>
                        $conversation->user_id,

                    'conversation_control_id' =>
                        $conversation->id,

                    'type' =>
                        $type,

                    'severity' =>
                        $severity,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'is_resolved' =>
                        false,
                ]
            );
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
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİNİN TÜM AÇIK ALARMLARI
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

        CrmAlarm::query()
            ->where(
                'conversation_control_id',
                $conversation->id
            )
            ->where(
                'is_resolved',
                false
            )
            ->update([
                'is_resolved' =>
                    true,

                'resolved_at' =>
                    now(),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ALARM DURUMUNU GERÇEK CRM VERİSİYLE SENKRONLA
    |--------------------------------------------------------------------------
    |
    | Alarmı doğuran şart artık mevcut değilse alarm otomatik kapanır.
    |
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

        /*
        |--------------------------------------------------------------------------
        | WON / LOST
        |--------------------------------------------------------------------------
        */

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
    | KRİTİK LEAD ALARMI BİTTİ Mİ?
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
    | GECİKEN SICAK LEAD ALARMI BİTTİ Mİ?
    |--------------------------------------------------------------------------
    |
    | Şunlardan herhangi biri olursa alarm kapanır:
    | - Lead artık sıcak değilse
    | - Takip tarihi kaldırıldıysa
    | - Takip artık 3 saatten fazla gecikmiş değilse
    |
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
    | RİSKLİ LEAD ALARMI BİTTİ Mİ?
    |--------------------------------------------------------------------------
    |
    | Risk etiketi kalkarsa veya lead puanı 70 altına düşerse kapanır.
    |
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
    | SESSİZ TEKLİF ALARMI BİTTİ Mİ?
    |--------------------------------------------------------------------------
    |
    | Müşteri teklif aşamasından çıktıysa alarm kapanır.
    | Son temas 24 saatten yeniyse de müşteri artık "sessiz" değildir.
    |
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