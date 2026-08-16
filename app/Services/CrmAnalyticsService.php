<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmAnalyticsService
{
    protected function messageQuery(
        int $userId,
        ?int $organizationId = null
    ): Builder {
        $query = ChatMessage::query();

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

    protected function conversationQuery(
        int $userId,
        ?int $organizationId = null
    ): Builder {
        $query = ConversationControl::query();

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

    public function aiMessageCount(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): int {
        return $this->messageQuery(
            $userId,
            $organizationId
        )
            ->where('sender_type', 'ai')
            ->when(
                $startDate,
                fn (Builder $query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    public function humanMessageCount(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): int {
        return $this->messageQuery(
            $userId,
            $organizationId
        )
            ->where('sender_type', 'human')
            ->when(
                $startDate,
                fn (Builder $query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    public function customerMessageCount(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): int {
        return $this->messageQuery(
            $userId,
            $organizationId
        )
            ->where('sender_type', 'customer')
            ->when(
                $startDate,
                fn (Builder $query) =>
                    $query->where(
                        'created_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    public function aiResponseRate(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): float {
        $ai = $this->aiMessageCount(
            $userId,
            $startDate,
            $organizationId
        );

        $human = $this->humanMessageCount(
            $userId,
            $startDate,
            $organizationId
        );

        $total = $ai + $human;

        return $total > 0
            ? round(($ai / $total) * 100, 1)
            : 0;
    }

    public function humanResponseRate(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): float {
        $ai = $this->aiMessageCount(
            $userId,
            $startDate,
            $organizationId
        );

        $human = $this->humanMessageCount(
            $userId,
            $startDate,
            $organizationId
        );

        $total = $ai + $human;

        return $total > 0
            ? round(($human / $total) * 100, 1)
            : 0;
    }

    public function takeoverCount(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): int {
        return $this->conversationQuery(
            $userId,
            $organizationId
        )
            ->whereNotNull('taken_over_at')
            ->when(
                $startDate,
                fn (Builder $query) =>
                    $query->where(
                        'taken_over_at',
                        '>=',
                        $startDate
                    )
            )
            ->count();
    }

    public function activeHumanTakeovers(
        int $userId,
        ?int $organizationId = null
    ): int {
        return $this->conversationQuery(
            $userId,
            $organizationId
        )
            ->where(
                'human_takeover',
                true
            )
            ->count();
    }

    public function averageFirstResponseSeconds(
        int $userId,
        $startDate = null,
        ?int $organizationId = null,
        int $limit = 500
    ): float {
        return $this->averageResponseSeconds(
            userId: $userId,
            senderTypes: ['ai', 'human'],
            startDate: $startDate,
            organizationId: $organizationId,
            limit: $limit,
        );
    }

    public function averageAiResponseSeconds(
        int $userId,
        $startDate = null,
        ?int $organizationId = null,
        int $limit = 500
    ): float {
        return $this->averageResponseSeconds(
            userId: $userId,
            senderTypes: ['ai'],
            startDate: $startDate,
            organizationId: $organizationId,
            limit: $limit,
        );
    }

    public function averageHumanResponseSeconds(
        int $userId,
        $startDate = null,
        ?int $organizationId = null,
        int $limit = 500
    ): float {
        return $this->averageResponseSeconds(
            userId: $userId,
            senderTypes: ['human'],
            startDate: $startDate,
            organizationId: $organizationId,
            limit: $limit,
        );
    }

    protected function averageResponseSeconds(
        int $userId,
        array $senderTypes,
        $startDate = null,
        ?int $organizationId = null,
        int $limit = 500
    ): float {
        $customerMessages =
            $this->messageQuery(
                $userId,
                $organizationId
            )
                ->where(
                    'sender_type',
                    'customer'
                )
                ->when(
                    $startDate,
                    fn (Builder $query) =>
                        $query->where(
                            'created_at',
                            '>=',
                            $startDate
                        )
                )
                ->orderByDesc('created_at')
                ->limit($limit)
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
                $this->messageQuery(
                    $userId,
                    $organizationId
                )
                    ->where(
                        'session_id',
                        $message->session_id
                    )
                    ->whereIn(
                        'sender_type',
                        $senderTypes
                    )
                    ->where(
                        'created_at',
                        '>',
                        $message->created_at
                    )
                    ->orderBy('created_at')
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

            $totalSeconds += $seconds;
            $matchedCount++;
        }

        return $matchedCount > 0
            ? round(
                $totalSeconds / $matchedCount,
                1
            )
            : 0;
    }

    public function staffPerformance(
        int $userId,
        $startDate = null,
        ?int $organizationId = null
    ): Collection {
        $staffIds =
            $this->messageQuery(
                $userId,
                $organizationId
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
                    fn (Builder $query) =>
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
            $this->conversationQuery(
                $userId,
                $organizationId
            )
                ->whereNotNull(
                    'assigned_user_id'
                )
                ->when(
                    $startDate,
                    fn (Builder $query) =>
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
                ->merge($assignedIds)
                ->filter()
                ->unique()
                ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $users =
            User::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

        return $ids
            ->map(
                function ($staffId) use (
                    $users,
                    $userId,
                    $startDate,
                    $organizationId
                ) {
                    $staff =
                        $users->get($staffId);

                    if (! $staff) {
                        return null;
                    }

                    $humanMessages =
                        $this->messageQuery(
                            $userId,
                            $organizationId
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
                                fn (Builder $query) =>
                                    $query->where(
                                        'created_at',
                                        '>=',
                                        $startDate
                                    )
                            )
                            ->count();

                    $assigned =
                        $this->conversationQuery(
                            $userId,
                            $organizationId
                        )
                            ->where(
                                'assigned_user_id',
                                $staffId
                            )
                            ->when(
                                $startDate,
                                fn (Builder $query) =>
                                    $query->where(
                                        'created_at',
                                        '>=',
                                        $startDate
                                    )
                            );

                    $assignedCount =
                        (clone $assigned)->count();

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
                                ($wonCount / $assignedCount)
                                * 100,
                                1
                            )
                            : 0;

                    return [
                        'id' => (int) $staff->id,
                        'name' => $staff->name,
                        'human_messages' => $humanMessages,
                        'assigned_leads' => $assignedCount,
                        'open_leads' => $openCount,
                        'won_leads' => $wonCount,
                        'conversion_rate' => $conversionRate,
                    ];
                }
            )
            ->filter()
            ->sortByDesc('won_leads')
            ->values();
    }

    public function formatSeconds(
        float|int $seconds
    ): string {
        $seconds =
            max(
                0,
                (int) round($seconds)
            );

        if ($seconds < 60) {
            return $seconds.' sn';
        }

        if ($seconds < 3600) {
            $minutes =
                floor($seconds / 60);

            $remainingSeconds =
                $seconds % 60;

            return $remainingSeconds === 0
                ? $minutes.' dk'
                : $minutes.' dk '
                    .$remainingSeconds
                    .' sn';
        }

        $hours =
            floor($seconds / 3600);

        $minutes =
            floor(
                ($seconds % 3600)
                / 60
            );

        return $minutes === 0
            ? $hours.' sa'
            : $hours.' sa '
                .$minutes
                .' dk';
    }
}