<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\StaffSalesGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmStaffSalesGoalService
{
    protected function conversationQuery(
        int $ownerUserId,
        ?int $organizationId = null
    ): Builder {
        $query =
            ConversationControl::query();

        if ($organizationId !== null) {
            return $query->where(
                'organization_id',
                $organizationId
            );
        }

        return $query->where(
            'user_id',
            $ownerUserId
        );
    }

    public function staffUsers(
        int $ownerUserId,
        ?int $organizationId = null
    ): Collection {
        if ($organizationId !== null) {
            return User::query()
                ->whereHas(
                    'organizations',
                    function (Builder $query) use (
                        $organizationId
                    ): void {
                        $query
                            ->where(
                                'organizations.id',
                                $organizationId
                            )
                            ->where(
                                'organization_user.status',
                                'active'
                            )
                            ->whereIn(
                                'organization_user.role',
                                [
                                    'manager',
                                    'sales',
                                    'support',
                                ]
                            );
                    }
                )
                ->orderBy('name')
                ->get();
        }

        $staffIds =
            $this->conversationQuery(
                $ownerUserId
            )
                ->whereNotNull(
                    'assigned_user_id'
                )
                ->distinct()
                ->pluck(
                    'assigned_user_id'
                )
                ->filter()
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->values();

        if ($staffIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn(
                'id',
                $staffIds
            )
            ->orderBy('name')
            ->get();
    }

    public function rows(
        int $ownerUserId,
        ?int $organizationId = null
    ): Collection {
        $month =
            now()
                ->startOfMonth()
                ->toDateString();

        return $this->staffUsers(
            $ownerUserId,
            $organizationId
        )
            ->map(
                function (
                    User $staff
                ) use (
                    $ownerUserId,
                    $organizationId,
                    $month
                ): array {
                    $goal =
                        StaffSalesGoal::query()
                            ->where(
                                'owner_user_id',
                                $ownerUserId
                            )
                            ->where(
                                'staff_user_id',
                                $staff->id
                            )
                            ->whereDate(
                                'month',
                                $month
                            )
                            ->first();

                    $salesQuery =
                        $this->conversationQuery(
                            $ownerUserId,
                            $organizationId
                        )
                            ->where(
                                'assigned_user_id',
                                $staff->id
                            )
                            ->where(
                                'lead_status',
                                'won'
                            )
                            ->whereBetween(
                                'won_at',
                                [
                                    now()->startOfMonth(),
                                    now()->endOfMonth(),
                                ]
                            );

                    $realized =
                        round(
                            (float) (
                                clone $salesQuery
                            )
                                ->whereNotNull(
                                    'actual_value'
                                )
                                ->sum(
                                    'actual_value'
                                ),
                            2
                        );

                    $wonSales =
                        (clone $salesQuery)
                            ->count();

                    $target =
                        max(
                            0,
                            (float) (
                                $goal?->target_amount
                                ?? 0
                            )
                        );

                    $percent =
                        $target > 0
                            ? round(
                                ($realized / $target)
                                * 100,
                                1
                            )
                            : 0;

                    $remaining =
                        max(
                            0,
                            round(
                                $target - $realized,
                                2
                            )
                        );

                    $exceeded =
                        max(
                            0,
                            round(
                                $realized - $target,
                                2
                            )
                        );

                    return [
                        'staff_user_id' =>
                            (int) $staff->id,
                        'name' =>
                            $staff->name
                            ?: $staff->email
                            ?: 'Personel',
                        'email' =>
                            $staff->email,
                        'target' =>
                            $target,
                        'realized' =>
                            $realized,
                        'won_sales' =>
                            $wonSales,
                        'remaining' =>
                            $remaining,
                        'exceeded' =>
                            $exceeded,
                        'percent' =>
                            $percent,
                        'completed' =>
                            $target > 0
                            && $realized >= $target,
                    ];
                }
            )
            ->sortByDesc(
                fn (array $row): float =>
                    (float) $row['realized']
            )
            ->values();
    }

    public function saveTarget(
        int $ownerUserId,
        int $staffUserId,
        ?float $target,
        ?int $organizationId = null
    ): bool {
        if ($organizationId !== null) {
            $staffExists =
                User::query()
                    ->whereKey(
                        $staffUserId
                    )
                    ->whereHas(
                        'organizations',
                        function (Builder $query) use (
                            $organizationId
                        ): void {
                            $query
                                ->where(
                                    'organizations.id',
                                    $organizationId
                                )
                                ->where(
                                    'organization_user.status',
                                    'active'
                                );
                        }
                    )
                    ->exists();
        } else {
            $staffExists =
                $this->conversationQuery(
                    $ownerUserId
                )
                    ->where(
                        'assigned_user_id',
                        $staffUserId
                    )
                    ->exists();
        }

        if (! $staffExists) {
            return false;
        }

        $target =
            $target !== null
                ? max(0, $target)
                : 0;

        StaffSalesGoal::query()
            ->updateOrCreate(
                [
                    'owner_user_id' =>
                        $ownerUserId,
                    'staff_user_id' =>
                        $staffUserId,
                    'month' =>
                        now()
                            ->startOfMonth()
                            ->toDateString(),
                ],
                [
                    'target_amount' =>
                        $target,
                ]
            );

        return true;
    }
}