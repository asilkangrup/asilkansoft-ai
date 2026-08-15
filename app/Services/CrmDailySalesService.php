<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmDailySalesService
{
    /*
    |--------------------------------------------------------------------------
    | GÜNLÜK SATIŞ MERKEZİ
    |--------------------------------------------------------------------------
    |
    | Bu servis günlük operasyon listelerini tek yerde toplar.
    | Böylece aynı mantık ileride Dashboard / Görevler / Mobil panelde
    | tekrar kullanılabilir.
    |
    */

    public function todayFollowUps(
        int $userId,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($userId)
            ->whereNotNull('next_follow_up_at')
            ->whereBetween(
                'next_follow_up_at',
                [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ]
            )
            ->orderBy('next_follow_up_at')
            ->limit($limit)
            ->get();
    }

    public function unattendedFor24Hours(
        int $userId,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($userId)
            ->whereNotNull('last_contact_at')
            ->where(
                'last_contact_at',
                '<=',
                now()->subDay()
            )
            ->orderBy('last_contact_at')
            ->limit($limit)
            ->get();
    }

    public function silentFor7Days(
        int $userId,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($userId)
            ->whereNotNull('last_contact_at')
            ->where(
                'last_contact_at',
                '<=',
                now()->subDays(7)
            )
            ->orderBy('last_contact_at')
            ->limit($limit)
            ->get();
    }

    public function closestToSale(
        int $userId,
        int $limit = 10
    ): Collection {
        return $this->baseQuery($userId)
            ->orderByDesc('lead_score')
            ->orderByRaw(
                "
                CASE
                    WHEN lead_status = 'proposal' THEN 1
                    WHEN lead_status = 'qualified' THEN 2
                    WHEN lead_status = 'contacted' THEN 3
                    ELSE 4
                END
                "
            )
            ->orderByDesc('last_contact_at')
            ->limit($limit)
            ->get();
    }

    public function counts(
        int $userId
    ): array {
        return [
            'today_follow_ups' =>
                $this->todayFollowUps(
                    $userId,
                    1000
                )->count(),

            'unattended_24h' =>
                $this->unattendedFor24Hours(
                    $userId,
                    1000
                )->count(),

            'silent_7d' =>
                $this->silentFor7Days(
                    $userId,
                    1000
                )->count(),

            'closest_to_sale' =>
                $this->closestToSale(
                    $userId,
                    10
                )->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ORTAK QUERY
    |--------------------------------------------------------------------------
    */

    private function baseQuery(
        int $userId
    ): Builder {
        return ConversationControl::query()
            ->with([
                'aiBot',
                'assignedUser',
            ])
            ->where(
                'user_id',
                $userId
            )
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            );
    }
}