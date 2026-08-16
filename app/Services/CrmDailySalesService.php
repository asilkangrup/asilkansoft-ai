<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmDailySalesService
{
    /*
    |--------------------------------------------------------------------------
    | GÃœNLÃœK SATIÅ MERKEZÄ°
    |--------------------------------------------------------------------------
    |
    | Bu servis gÃ¼nlÃ¼k operasyon listelerini tek yerde toplar.
    | BÃ¶ylece aynÄ± mantÄ±k ileride Dashboard / GÃ¶revler / Mobil panelde
    | tekrar kullanÄ±labilir.
    |
    */

    public function todayFollowUps(
        int $userId,
        int $limit = 10,
        ?int $organizationId = null,
        ?string $role = null
    ): Collection {
        return $this->baseQuery($userId, $organizationId, $role)
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
        int $limit = 10,
        ?int $organizationId = null,
        ?string $role = null
    ): Collection {
        return $this->baseQuery($userId, $organizationId, $role)
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
        int $limit = 10,
        ?int $organizationId = null,
        ?string $role = null
    ): Collection {
        return $this->baseQuery($userId, $organizationId, $role)
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
        int $limit = 10,
        ?int $organizationId = null,
        ?string $role = null
    ): Collection {
        return $this->baseQuery($userId, $organizationId, $role)
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
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): array {
        return [
            'today_follow_ups' =>
                $this->todayFollowUps(
                    $userId,
                    10,
                    $organizationId,
                    $role00,
                    $organizationId,
                    $role
                )->count(),

            'unattended_24h' =>
                $this->unattendedFor24Hours(
                    $userId,
                    10,
                    $organizationId,
                    $role00,
                    $organizationId,
                    $role
                )->count(),

            'silent_7d' =>
                $this->silentFor7Days(
                    $userId,
                    10,
                    $organizationId,
                    $role00,
                    $organizationId,
                    $role
                )->count(),

            'closest_to_sale' =>
                $this->closestToSale(
                    $userId,
                    10,
                    $organizationId,
                    $role
                )->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ORTAK QUERY
    |--------------------------------------------------------------------------
    */

    private function baseQuery(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): Builder {
        $query = ConversationControl::query()
            ->with([
                'aiBot',
                'assignedUser',
            ]);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);

            if ($role === 'sales') {
                $query->where('assigned_user_id', $userId);
            }
        } else {
            $query->where('user_id', $userId);
        }

        return $query
            ->whereNotIn(
                'lead_status',
                [
                    'won',
                    'lost',
                ]
            );
    }
}