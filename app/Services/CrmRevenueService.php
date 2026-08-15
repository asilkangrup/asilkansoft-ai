<?php

namespace App\Services;

use App\Models\ConversationControl;

class CrmRevenueService
{
    /*
    |--------------------------------------------------------------------------
    | GERÇEKLEŞEN CİRO
    |--------------------------------------------------------------------------
    */

    public function totalWonRevenue(
        int $userId
    ): float {
        return (float) ConversationControl::query()
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
            ->sum(
                'actual_value'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | KAZANILAN SATIŞ SAYISI
    |--------------------------------------------------------------------------
    */

    public function wonSalesCount(
        int $userId
    ): int {
        return ConversationControl::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'lead_status',
                'won'
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | GERÇEK TUTARI GİRİLMİŞ SATIŞ SAYISI
    |--------------------------------------------------------------------------
    */

    public function valuedWonSalesCount(
        int $userId
    ): int {
        return ConversationControl::query()
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
            ->where(
                'actual_value',
                '>',
                0
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | ORTALAMA SATIŞ TUTARI
    |--------------------------------------------------------------------------
    */

    public function averageWonValue(
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
    | TAHMİN / GERÇEKLEŞEN FARKI
    |--------------------------------------------------------------------------
    |
    | Sadece hem estimated_value hem actual_value bulunan kazanılmış satışları
    | dikkate alır.
    |
    */

    public function forecastComparison(
        int $userId
    ): array {
        $sales =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
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
}