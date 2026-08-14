<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\User;
use Illuminate\Support\Collection;

class CrmAnalyticsService
{
    /*
    |--------------------------------------------------------------------------
    | AI MESAJ SAYISI
    |--------------------------------------------------------------------------
    */

    public function aiMessageCount(
        int $userId,
        $startDate = null
    ): int {
        return ChatMessage::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'sender_type',
                'ai'
            )
            ->when(
                $startDate,
                fn ($query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN MESAJ SAYISI
    |--------------------------------------------------------------------------
    */

    public function humanMessageCount(
        int $userId,
        $startDate = null
    ): int {
        return ChatMessage::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'sender_type',
                'human'
            )
            ->when(
                $startDate,
                fn ($query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | MÜŞTERİ MESAJ SAYISI
    |--------------------------------------------------------------------------
    */

    public function customerMessageCount(
        int $userId,
        $startDate = null
    ): int {
        return ChatMessage::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'sender_type',
                'customer'
            )
            ->when(
                $startDate,
                fn ($query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | AI CEVAPLAMA ORANI
    |--------------------------------------------------------------------------
    */

    public function aiResponseRate(
        int $userId,
        $startDate = null
    ): float {
        $ai =
            $this->aiMessageCount(
                $userId,
                $startDate
            );

        $human =
            $this->humanMessageCount(
                $userId,
                $startDate
            );

        $total =
            $ai + $human;

        if ($total === 0) {
            return 0;
        }

        return round(
            ($ai / $total) * 100,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN CEVAPLAMA ORANI
    |--------------------------------------------------------------------------
    */

    public function humanResponseRate(
        int $userId,
        $startDate = null
    ): float {
        $ai =
            $this->aiMessageCount(
                $userId,
                $startDate
            );

        $human =
            $this->humanMessageCount(
                $userId,
                $startDate
            );

        $total =
            $ai + $human;

        if ($total === 0) {
            return 0;
        }

        return round(
            ($human / $total) * 100,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | İNSAN DEVRALMA SAYISI
    |--------------------------------------------------------------------------
    |
    | taken_over_at alanı dolmuş konuşmaları sayar.
    |
    */

    public function takeoverCount(
        int $userId,
        $startDate = null
    ): int {
        return ConversationControl::query()
            ->where(
                'user_id',
                $userId
            )
            ->whereNotNull(
                'taken_over_at'
            )
            ->when(
                $startDate,
                fn ($query) =>
                    $query->where(
                        'taken_over_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | ŞU ANDA İNSANDA OLAN KONUŞMALAR
    |--------------------------------------------------------------------------
    */

    public function activeHumanTakeovers(
        int $userId
    ): int {
        return ConversationControl::query()
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'human_takeover',
                true
            )
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | ORTALAMA İLK CEVAP SÜRESİ
    |--------------------------------------------------------------------------
    |
    | Her müşteri mesajından sonra gelen ilk AI veya insan cevabını bulur.
    | Aradaki saniye farklarının ortalamasını döndürür.
    |
    | Limit koymamızın sebebi rapor ekranında çok büyük hesap yükü oluşturmamak.
    |
    */

    public function averageFirstResponseSeconds(
        int $userId,
        $startDate = null,
        int $limit = 500
    ): float {
        $customerMessages =
            ChatMessage::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'sender_type',
                    'customer'
                )
                ->when(
                    $startDate,
                    fn ($query) =>
                        $query->where(
                            'created_at',
                            '>=',
                            $startDate
                        )
                )
                ->orderByDesc(
                    'created_at'
                )
                ->limit(
                    $limit
                )
                ->get([
                    'id',
                    'session_id',
                    'created_at',
                ]);

        if ($customerMessages->isEmpty()) {
            return 0;
        }

        $totalSeconds = 0;

        $matchedCount = 0;

        foreach ($customerMessages as $message) {
            $reply =
                ChatMessage::query()
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'session_id',
                        $message->session_id
                    )
                    ->whereIn(
                        'sender_type',
                        [
                            'ai',
                            'human',
                        ]
                    )
                    ->where(
                        'created_at',
                        '>',
                        $message->created_at
                    )
                    ->orderBy(
                        'created_at'
                    )
                    ->first([
                        'id',
                        'created_at',
                    ]);

            if (! $reply) {
                continue;
            }

            $seconds =
                $message
                    ->created_at
                    ->diffInSeconds(
                        $reply->created_at
                    );

            /*
            |--------------------------------------------------------------------------
            | ANORMAL VERİ KORUMASI
            |--------------------------------------------------------------------------
            |
            | 24 saatten uzun cevap farklarını ortalamaya dahil etmiyoruz.
            |
            */

            if ($seconds > 86400) {
                continue;
            }

            $totalSeconds +=
                $seconds;

            $matchedCount++;
        }

        if ($matchedCount === 0) {
            return 0;
        }

        return round(
            $totalSeconds / $matchedCount,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORTALAMA AI CEVAP SÜRESİ
    |--------------------------------------------------------------------------
    */

    public function averageAiResponseSeconds(
        int $userId,
        $startDate = null,
        int $limit = 500
    ): float {
        return $this->averageResponseSecondsByType(
            userId: $userId,
            senderType: 'ai',
            startDate: $startDate,
            limit: $limit,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORTALAMA İNSAN CEVAP SÜRESİ
    |--------------------------------------------------------------------------
    */

    public function averageHumanResponseSeconds(
        int $userId,
        $startDate = null,
        int $limit = 500
    ): float {
        return $this->averageResponseSecondsByType(
            userId: $userId,
            senderType: 'human',
            startDate: $startDate,
            limit: $limit,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CEVAP SÜRESİ - TİPE GÖRE
    |--------------------------------------------------------------------------
    */

    protected function averageResponseSecondsByType(
        int $userId,
        string $senderType,
        $startDate = null,
        int $limit = 500
    ): float {
        $customerMessages =
            ChatMessage::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'sender_type',
                    'customer'
                )
                ->when(
                    $startDate,
                    fn ($query) =>
                        $query->where(
                            'created_at',
                            '>=',
                            $startDate
                        )
                )
                ->orderByDesc(
                    'created_at'
                )
                ->limit(
                    $limit
                )
                ->get([
                    'id',
                    'session_id',
                    'created_at',
                ]);

        if ($customerMessages->isEmpty()) {
            return 0;
        }

        $totalSeconds = 0;

        $matchedCount = 0;

        foreach ($customerMessages as $message) {
            $reply =
                ChatMessage::query()
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'session_id',
                        $message->session_id
                    )
                    ->where(
                        'sender_type',
                        $senderType
                    )
                    ->where(
                        'created_at',
                        '>',
                        $message->created_at
                    )
                    ->orderBy(
                        'created_at'
                    )
                    ->first([
                        'id',
                        'created_at',
                    ]);

            if (! $reply) {
                continue;
            }

            $seconds =
                $message
                    ->created_at
                    ->diffInSeconds(
                        $reply->created_at
                    );

            if ($seconds > 86400) {
                continue;
            }

            $totalSeconds +=
                $seconds;

            $matchedCount++;
        }

        if ($matchedCount === 0) {
            return 0;
        }

        return round(
            $totalSeconds / $matchedCount,
            1
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERSONEL PERFORMANSI
    |--------------------------------------------------------------------------
    |
    | sent_by_user_id üzerinden gerçek insan mesaj sayısını,
    | assigned_user_id üzerinden CRM müşteri sonuçlarını toplar.
    |
    */

    public function staffPerformance(
        int $userId,
        $startDate = null
    ): Collection {
        $staffIds =
            ChatMessage::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->where(
                    'sender_type',
                    'human'
                )
                ->whereNotNull(
                    'sent_by_user_id'
                )
                ->when(
                    $startDate,
                    fn ($query) =>
                        $query->where(
                            'created_at',
                            '>=',
                            $startDate
                        )
                )
                ->distinct()
                ->pluck(
                    'sent_by_user_id'
                );

        $assignedIds =
            ConversationControl::query()
                ->where(
                    'user_id',
                    $userId
                )
                ->whereNotNull(
                    'assigned_user_id'
                )
                ->when(
                    $startDate,
                    fn ($query) =>
                        $query->where(
                            'created_at',
                            '>=',
                            $startDate
                        )
                )
                ->distinct()
                ->pluck(
                    'assigned_user_id'
                );

        $ids =
            $staffIds
                ->merge(
                    $assignedIds
                )
                ->unique()
                ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $users =
            User::query()
                ->whereIn(
                    'id',
                    $ids
                )
                ->get()
                ->keyBy(
                    'id'
                );

        return $ids
            ->map(
                function ($staffId) use (
                    $users,
                    $userId,
                    $startDate
                ) {
                    $staff =
                        $users->get(
                            $staffId
                        );

                    if (! $staff) {
                        return null;
                    }

                    $humanMessages =
                        ChatMessage::query()
                            ->where(
                                'user_id',
                                $userId
                            )
                            ->where(
                                'sender_type',
                                'human'
                            )
                            ->where(
                                'sent_by_user_id',
                                $staffId
                            )
                            ->when(
                                $startDate,
                                fn ($query) =>
                                    $query->where(
                                        'created_at',
                                        '>=',
                                        $startDate
                                    )
                            )
                            ->count();

                    $assigned =
                        ConversationControl::query()
                            ->where(
                                'user_id',
                                $userId
                            )
                            ->where(
                                'assigned_user_id',
                                $staffId
                            )
                            ->when(
                                $startDate,
                                fn ($query) =>
                                    $query->where(
                                        'created_at',
                                        '>=',
                                        $startDate
                                    )
                            );

                    $assignedCount =
                        (clone $assigned)
                            ->count();

                    $wonCount =
                        (clone $assigned)
                            ->where(
                                'lead_status',
                                'won'
                            )
                            ->count();

                    $openCount =
                        (clone $assigned)
                            ->whereNotIn(
                                'lead_status',
                                [
                                    'won',
                                    'lost',
                                ]
                            )
                            ->count();

                    $conversionRate =
                        $assignedCount > 0
                            ? round(
                                (
                                    $wonCount
                                    /
                                    $assignedCount
                                )
                                * 100,
                                1
                            )
                            : 0;

                    return [
                        'id' =>
                            $staff->id,

                        'name' =>
                            $staff->name,

                        'human_messages' =>
                            $humanMessages,

                        'assigned_leads' =>
                            $assignedCount,

                        'open_leads' =>
                            $openCount,

                        'won_leads' =>
                            $wonCount,

                        'conversion_rate' =>
                            $conversionRate,
                    ];
                }
            )
            ->filter()
            ->sortByDesc(
                'won_leads'
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | CEVAP SÜRESİNİ OKUNABİLİR HALE GETİR
    |--------------------------------------------------------------------------
    */

    public function formatSeconds(
        float|int $seconds
    ): string {
        $seconds =
            max(
                0,
                (int) round(
                    $seconds
                )
            );

        if ($seconds < 60) {
            return $seconds
                .' sn';
        }

        if ($seconds < 3600) {
            $minutes =
                floor(
                    $seconds / 60
                );

            $remainingSeconds =
                $seconds % 60;

            if ($remainingSeconds === 0) {
                return $minutes
                    .' dk';
            }

            return $minutes
                .' dk '
                .$remainingSeconds
                .' sn';
        }

        $hours =
            floor(
                $seconds / 3600
            );

        $minutes =
            floor(
                ($seconds % 3600) / 60
            );

        if ($minutes === 0) {
            return $hours
                .' sa';
        }

        return $hours
            .' sa '
            .$minutes
            .' dk';
    }
}