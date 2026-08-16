<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Database\Eloquent\Builder;

class CrmForecastService
{
    /*
    |--------------------------------------------------------------------------
    | SATIÅ OLASILIÄI
    |--------------------------------------------------------------------------
    |
    | OlasÄ±lÄ±k yalnÄ±zca lead_score deÄŸildir.
    | Pipeline aÅŸamasÄ± ile lead puanÄ± birlikte deÄŸerlendirilir.
    |
    */

    public function probability(
        ConversationControl $conversation
    ): int {
        if ($conversation->lead_status === 'won') {
            return 100;
        }

        if ($conversation->lead_status === 'lost') {
            return 0;
        }

        $score =
            max(
                0,
                min(
                    100,
                    (int) $conversation->lead_score
                )
            );

        $stageBase =
            match ($conversation->lead_status) {
                'proposal' => 65,
                'qualified' => 45,
                'contacted' => 25,
                default => 10,
            };

        /*
        |--------------------------------------------------------------------------
        | PUANIN ETKÄ°SÄ°
        |--------------------------------------------------------------------------
        |
        | Lead score toplam olasÄ±lÄ±ÄŸÄ±n %60'Ä±nÄ± etkiler.
        | Pipeline aÅŸamasÄ± kalan davranÄ±ÅŸ tabanÄ±nÄ± oluÅŸturur.
        |
        */

        $probability =
            (int) round(
                ($score * 0.60)
                + ($stageBase * 0.40)
            );

        /*
        |--------------------------------------------------------------------------
        | SICAK / Ã–NCELÄ°KLÄ° LEAD ALT SINIRLARI
        |--------------------------------------------------------------------------
        */

        if ($score >= 85) {
            $probability =
                max(
                    $probability,
                    75
                );
        } elseif ($score >= 70) {
            $probability =
                max(
                    $probability,
                    60
                );
        }

        if (
            $conversation->lead_status === 'proposal'
        ) {
            $probability =
                max(
                    $probability,
                    70
                );
        }

        return max(
            0,
            min(
                95,
                $probability
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AÄIRLIKLI SATIÅ DEÄERÄ°
    |--------------------------------------------------------------------------
    */

    public function weightedValue(
        ConversationControl $conversation
    ): float {
        $estimatedValue =
            max(
                0,
                (float) (
                    $conversation->estimated_value
                    ?? 0
                )
            );

        if ($estimatedValue <= 0) {
            return 0;
        }

        return round(
            $estimatedValue
            * (
                $this->probability(
                    $conversation
                )
                / 100
            ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOPLAM AÃ‡IK PIPELINE DEÄERÄ°
    |--------------------------------------------------------------------------
    */

    public function totalPipelineValue(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): float {
        return (float) $this->openQuery(
            $userId,
            $organizationId,
            $role
        )->sum('estimated_value');
    }

    /*
    |--------------------------------------------------------------------------
    | AÄIRLIKLI PIPELINE
    |--------------------------------------------------------------------------
    */

    public function weightedPipelineValue(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): float {
        return round(
            $this->openQuery(
                $userId,
                $organizationId,
                $role
            )
                ->get()
                ->sum(
                    fn (
                        ConversationControl $conversation
                    ): float =>
                        $this->weightedValue(
                            $conversation
                        )
                ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DEÄERÄ° OLAN AÃ‡IK FIRSATLAR
    |--------------------------------------------------------------------------
    */

    public function valuedOpportunitiesCount(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): int {
        return $this->openQuery(
            $userId,
            $organizationId,
            $role
        )
            ->whereNotNull(
                'estimated_value'
            )
            ->where(
                'estimated_value',
                '>',
                0
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | EN DEÄERLÄ° FIRSATLAR
    |--------------------------------------------------------------------------
    */

    public function topOpportunities(
        int $userId,
        int $limit = 10,
        ?int $organizationId = null,
        ?string $role = null
    ) {
        return $this->openQuery(
            $userId,
            $organizationId,
            $role
        )
            ->whereNotNull(
                'estimated_value'
            )
            ->where(
                'estimated_value',
                '>',
                0
            )
            ->orderByDesc(
                'estimated_value'
            )
            ->orderByDesc(
                'lead_score'
            )
            ->limit(
                $limit
            )
            ->get();
    }

    private function openQuery(
        int $userId,
        ?int $organizationId = null,
        ?string $role = null
    ): Builder {
        $query = ConversationControl::query();

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