<?php

namespace App\Services;

use App\Models\ConversationControl;
use App\Models\CrmManagerSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RealEstateAwareCrmManagerSummaryService extends CrmManagerSummaryService
{
    public function metrics(int $userId): array
    {
        if (! app(RealEstateIsolationService::class)->isIsolatedUserId($userId)) {
            return parent::metrics($userId);
        }

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todayLeads = $this->realEstateConversations()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $hotLeads = $this->realEstateConversations()
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->where('lead_temperature', 'hot')
            ->count();

        $priorityLeads = $this->realEstateConversations()
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->where('lead_score', '>=', 85)
            ->count();

        $wonToday = $this->realEstateConversations()
            ->where('lead_status', 'won')
            ->whereBetween('won_at', [$todayStart, $todayEnd])
            ->count();

        $revenueToday = round((float) $this->realEstateConversations()
            ->where('lead_status', 'won')
            ->whereBetween('won_at', [$todayStart, $todayEnd])
            ->whereNotNull('actual_value')
            ->sum('actual_value'), 2);

        $openPipeline = round((float) $this->realEstateConversations()
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->whereNotNull('estimated_value')
            ->sum('estimated_value'), 2);

        $overdueFollowUps = $this->realEstateConversations()
            ->whereNotIn('lead_status', ['won', 'lost'])
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now())
            ->count();

        $lostReason = $this->realEstateConversations()
            ->where('lead_status', 'lost')
            ->whereDate('lost_at', today())
            ->selectRaw("COALESCE(NULLIF(TRIM(lost_reason), ''), 'Belirtilmemiş') as reason, COUNT(*) as total")
            ->groupBy('reason')
            ->orderByDesc('total')
            ->first();

        return [
            'today_leads' => $todayLeads,
            'hot_leads' => $hotLeads,
            'priority_leads' => $priorityLeads,
            'won_today' => $wonToday,
            'revenue_today' => $revenueToday,
            'open_pipeline' => $openPipeline,
            'overdue_follow_ups' => $overdueFollowUps,
            'top_lost_reason' => $lostReason?->reason,
            'top_lost_reason_count' => (int) ($lostReason?->total ?? 0),
        ];
    }

    public function generate(User $user): CrmManagerSummary
    {
        if (! app(RealEstateIsolationService::class)->isIsolatedUserId((int) $user->id)) {
            return parent::generate($user);
        }

        $metrics = $this->metrics((int) $user->id);
        $summary = $this->deterministicSummary($metrics);

        $existing = CrmManagerSummary::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->whereDate('summary_date', today())
            ->first();

        if ($existing) {
            $existing->update([
                'summary' => $summary,
                'metrics' => $metrics,
                'generated_at' => now(),
            ]);
            $existing->refresh();

            return $existing;
        }

        return CrmManagerSummary::query()->create([
            'user_id' => RealEstateIsolationService::USER_ID,
            'summary_date' => today()->toDateString(),
            'summary' => $summary,
            'metrics' => $metrics,
            'generated_at' => now(),
        ]);
    }

    private function realEstateConversations(): Builder
    {
        return ConversationControl::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID);
    }

    private function deterministicSummary(array $metrics): string
    {
        $parts = [
            'Bugün '.$metrics['today_leads'].' yeni Emlak AI lead kaydı oluştu.',
            $metrics['hot_leads'].' sıcak lead ve '.$metrics['priority_leads'].' 85+ öncelikli lead bulunuyor.',
            'Bugün '.$metrics['won_today'].' işlem kazanıldı; kayıtlı gerçekleşen değer '.number_format((float) $metrics['revenue_today'], 2, ',', '.').' TL.',
            'Açık pipeline değeri '.number_format((float) $metrics['open_pipeline'], 2, ',', '.').' TL.',
        ];

        if ((int) $metrics['overdue_follow_ups'] > 0) {
            // Emlak AI follow-up runtime'ı kapalıdır. Bu ifade yalnızca veri
            // bütünlüğü için var olan geçmiş/stale kayıtları görünür kılar.
            $parts[] = $metrics['overdue_follow_ups'].' kayıtta geçmiş tarihli takip alanı var; otomatik mesaj gönderilmez.';
        }

        if (! empty($metrics['top_lost_reason'])) {
            $parts[] = 'Bugünün en sık kayıp nedeni: '.$metrics['top_lost_reason'].'.';
        }

        return mb_substr(implode(' ', $parts), 0, 1800);
    }
}
