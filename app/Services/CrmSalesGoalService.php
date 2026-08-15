<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\User;

class CrmSalesGoalService
{
    /*
    |--------------------------------------------------------------------------
    | AYLIK HEDEF
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | AYLIK GERÇEKLEŞEN CİRO
    |--------------------------------------------------------------------------
    */

    public function realizedRevenue(
        int $userId
    ): float {
        return round(
            (float) ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
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

    /*
    |--------------------------------------------------------------------------
    | HEDEF İLERLEMESİ
    |--------------------------------------------------------------------------
    */

    public function progress(
        User $user
    ): array {
        $target =
            $this->target(
                $user
            );

        $realized =
            $this->realizedRevenue(
                $user->id
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
                    (
                        $realized
                        / $target
                    )
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

    /*
    |--------------------------------------------------------------------------
    | HEDEF KAYDET
    |--------------------------------------------------------------------------
    */

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