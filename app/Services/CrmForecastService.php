<?php

namespace App\Services;

use App\Models\ConversationControl;
use Illuminate\Database\Eloquent\Builder;

class CrmForecastService
{
    /*
    |--------------------------------------------------------------------------
    | SATIŞ OLASILIĞI
    |--------------------------------------------------------------------------
    |
    | Olasılık yalnızca lead_score değildir.
    | Pipeline aşaması ile lead puanı birlikte değerlendirilir.
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
        | PUANIN ETKİSİ
        |--------------------------------------------------------------------------
        |
        | Lead score toplam olasılığın %60'ını etkiler.
        | Pipeline aşaması kalan davranış tabanını oluşturur.
        |
        */

        $probability =
            (int) round(
                ($score * 0.60)
                + ($stageBase * 0.40)
            );

        /*
        |--------------------------------------------------------------------------
        | SICAK / ÖNCELİKLİ LEAD ALT SINIRLARI
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
    | AĞIRLIKLI SATIŞ DEĞERİ
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
    | TOPLAM AÇIK PIPELINE DEĞERİ
    |--------------------------------------------------------------------------
    */

    public function totalPipelineValue(
        int $userId
    ): float {
        return (float) $this->openQuery(
            $userId
        )->sum('estimated_value');
    }

    /*
    |--------------------------------------------------------------------------
    | AĞIRLIKLI PIPELINE
    |--------------------------------------------------------------------------
    */

    public function weightedPipelineValue(
        int $userId
    ): float {
        return round(
            $this->openQuery(
                $userId
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
    | DEĞERİ OLAN AÇIK FIRSATLAR
    |--------------------------------------------------------------------------
    */

    public function valuedOpportunitiesCount(
        int $userId
    ): int {
        return $this->openQuery(
            $userId
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
    | EN DEĞERLİ FIRSATLAR
    |--------------------------------------------------------------------------
    */

    public function topOpportunities(
        int $userId,
        int $limit = 10
    ) {
        return $this->openQuery(
            $userId
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
        int $userId
    ): Builder {
        return ConversationControl::query()
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