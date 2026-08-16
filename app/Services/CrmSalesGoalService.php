<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CrmSalesGoalService
{
    public function target(
        User $user
    ): float {
        return max(
            0,
            (float) (
                $user->monthly_sales_target
                ?? 0
            )
        );
    }

    protected function conversationQuery(
        int $userId,
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
            $userId
        );
    }

    public function realizedRevenue(
        int $userId,
        ?int $organizationId = null
    ): float {
        return round(
            (float) $this
                ->conversationQuery(
                    $userId,
                    $organizationId
                )
                ->where(
                    'lead_status',
                    'won'
                )
                ->whereNotNull(
                    'actual_value'
                )
                ->whereBetween(
                    'created_at',
                    [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ]
                )
                ->sum(
                    'actual_value'
                ),
            2
        );
    }

    public function progress(
        User $user,
        ?int $organizationId = null
    ): array {
        $target =
            $this->target(
                $user
            );

        $realized =
            $this->realizedRevenue(
                $user->id,
                $organizationId
            );

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

        $percent =
            $target > 0
                ? round(
                    ($realized / $target)
                    * 100,
                    1
                )
                : 0;

        return [
            'target' =>
                $target,
            'realized' =>
                $realized,
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

    public function saveTarget(
        User $user,
        ?float $target
    ): void {
        $target =
            $target !== null
                ? max(
                    0,
                    $target
                )
                : null;

        $user->forceFill([
            'monthly_sales_target' =>
                $target,
        ])->save();
    }
}