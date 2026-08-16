<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Database\Eloquent\Builder;

class CrmRevenueService
{
    /*
    |--------------------------------------------------------------------------
    | GERÃ‡EKLEÅEN CÄ°RO
    |--------------------------------------------------------------------------
    */

    public function totalWonRevenue(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): float {
        return (float) $this->baseQuery(
                $userId,
                $organizationId,
                $role
            )
            ->where(
                'lead_status',
                'won'
            )
            ->whereNotNull(
                'actual_value'
            )
            ->sum(
                'actual_value'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | KAZANILAN SATIÅ SAYISI
    |--------------------------------------------------------------------------
    */

    public function wonSalesCount(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): int {
        return $this->baseQuery(
                $userId,
                $organizationId,
                $role
            )
            ->where(
                'lead_status',
                'won'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | GERÃ‡EK TUTARI GÄ°RÄ°LMÄ°Å SATIÅ SAYISI
    |--------------------------------------------------------------------------
    */

    public function valuedWonSalesCount(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): int {
        return $this->baseQuery(
                $userId,
                $organizationId,
                $role
            )
            ->where(
                'lead_status',
                'won'
            )
            ->whereNotNull(
                'actual_value'
            )
            ->where(
                'actual_value',
                '>',
                0
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | ORTALAMA SATIÅ TUTARI
    |--------------------------------------------------------------------------
    */

    public function averageWonValue(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): float {
        return round(
            (float) $this->baseQuery(
                    $userId,
                    $organizationId,
                    $role
                )
                ->where(
                    'lead_status',
                    'won'
                )
                ->whereNotNull(
                    'actual_value'
                )
                ->where(
                    'actual_value',
                    '>',
                    0
                )
                ->avg(
                    'actual_value'
                ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TAHMÄ°N / GERÃ‡EKLEÅEN FARKI
    |--------------------------------------------------------------------------
    |
    | Sadece hem estimated_value hem actual_value bulunan kazanÄ±lmÄ±ÅŸ satÄ±ÅŸlarÄ±
    | dikkate alÄ±r.
    |
    */

    public function forecastComparison(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): array {
        $sales =
            $this->baseQuery(
                    $userId,
                    $organizationId,
                    $role
                )
                ->where(
                    'lead_status',
                    'won'
                )
                ->whereNotNull(
                    'estimated_value'
                )
                ->whereNotNull(
                    'actual_value'
                )
                ->get([
                    'estimated_value',
                    'actual_value',
                ]);

        if ($sales->isEmpty()) {
            return [
                'estimated' => 0.0,
                'actual' => 0.0,
                'difference' => 0.0,
                'accuracy' => null,
                'count' => 0,
            ];
        }

        $estimated =
            round(
                (float) $sales->sum(
                    'estimated_value'
                ),
                2
            );

        $actual =
            round(
                (float) $sales->sum(
                    'actual_value'
                ),
                2
            );

        $difference =
            round(
                $actual - $estimated,
                2
            );

        $accuracy = null;

        if ($estimated > 0) {
            $absoluteDifference =
                abs(
                    $actual - $estimated
                );

            $accuracy =
                (int) round(
                    max(
                        0,
                        min(
                            100,
                            100
                            - (
                                $absoluteDifference
                                / $estimated
                                * 100
                            )
                        )
                    )
                );
        }

        return [
            'estimated' => $estimated,
            'actual' => $actual,
            'difference' => $difference,
            'accuracy' => $accuracy,
            'count' => $sales->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ORTAK ORGANİZASYON QUERY
    |--------------------------------------------------------------------------
    */

    private function baseQuery(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): Builder {
        $query = ConversationControl::query();

        if ($organizationId !== null) {
            $query->where(
                'organization_id',
                $organizationId
            );

            if ($role === 'sales') {
                $query->where(
                    'assigned_user_id',
                    $userId
                );
            }
        } else {
            $query->where(
                'user_id',
                $userId
            );
        }

        return $query;
    }
}