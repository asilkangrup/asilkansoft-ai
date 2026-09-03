<?php

namespace App\Services;

use App\Models\CrmActivity;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RealEstateConversionReportService
{
    private const DATA_KEY = 'marketing_attribution';

    public const SOURCES = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'google_ads' => 'Google Ads',
        'organik' => 'Organik',
        'referans' => 'Referans',
        'whatsapp' => 'WhatsApp',
        'web' => 'Web',
        'diger' => 'Diğer',
    ];

    public function report(?string $from = null, ?string $until = null): array
    {
        [$fromDate, $untilDate] = $this->dateRange($from, $until);

        $profiles = RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->whereHas('conversation', fn ($query) => $query
                ->whereBetween('created_at', [$fromDate, $untilDate]))
            ->get();

        $profilesByConversation = $profiles
            ->sortBy(fn (RealEstateProfile $profile): int => match ($profile->profile_type) {
                'seller' => 0,
                'investor', 'buyer' => 1,
                default => 2,
            })
            ->groupBy('conversation_control_id');

        $conversations = $profiles
            ->pluck('conversation')
            ->filter()
            ->unique('id')
            ->values();
        $conversationIds = $conversations->pluck('id')->map(fn ($id): int => (int) $id);

        $offeredConversationIds = $this->offeredConversationIds($conversationIds);
        $acceptedDeals = app(RealEstateClosingService::class)
            ->acceptedDeals()
            ->filter(fn (array $deal): bool => $conversationIds->contains(
                (int) $deal['seller']->conversation_control_id
            ))
            ->values();

        $acceptedByConversation = $acceptedDeals
            ->groupBy(fn (array $deal): int => (int) $deal['seller']->conversation_control_id);

        $rows = $conversations
            ->groupBy(function ($conversation) use ($profilesByConversation): string {
                $profile = $profilesByConversation->get($conversation->id)?->first();
                $attribution = $this->attribution($profile, $conversation);

                return $attribution['source_key'].'|'.$attribution['campaign'];
            })
            ->map(function (Collection $cohort, string $key) use (
                $profilesByConversation,
                $offeredConversationIds,
                $acceptedByConversation,
            ): array {
                [$sourceKey, $campaign] = explode('|', $key, 2);
                $leadIds = $cohort->pluck('id')->map(fn ($id): int => (int) $id);
                $profiled = $leadIds->filter(
                    fn (int $id): bool => $profilesByConversation->has($id)
                )->count();
                $sellerLeads = $leadIds->filter(function (int $id) use ($profilesByConversation): bool {
                    return $profilesByConversation->get($id, collect())
                        ->contains(fn (RealEstateProfile $profile): bool => $profile->profile_type === 'seller');
                })->count();
                $investorLeads = $leadIds->filter(function (int $id) use ($profilesByConversation): bool {
                    return $profilesByConversation->get($id, collect())
                        ->contains(fn (RealEstateProfile $profile): bool => in_array(
                            $profile->profile_type,
                            ['investor', 'buyer'],
                            true
                        ));
                })->count();
                $qualified = $cohort->filter(function ($conversation) use ($profilesByConversation): bool {
                    $profileScore = (int) ($profilesByConversation->get($conversation->id)?->max('completeness_score') ?? 0);

                    return in_array($conversation->lead_status, ['qualified', 'proposal', 'won'], true)
                        || (int) $conversation->lead_score >= 50
                        || $profileScore >= 60;
                })->count();
                $offers = $leadIds->intersect($offeredConversationIds)->count();
                $deals = $leadIds
                    ->flatMap(fn (int $id): Collection => $acceptedByConversation->get($id, collect()))
                    ->values();

                $closed = 0;
                $commissionDue = 0;
                $commissionCollected = 0;

                foreach ($deals as $deal) {
                    $closing = app(RealEstateClosingService::class)->caseForPair(
                        (int) $deal['seller_profile_id'],
                        (int) $deal['investor_profile_id'],
                    );
                    $commission = app(RealEstateCommissionService::class)->summaryForPair(
                        (int) $deal['seller_profile_id'],
                        (int) $deal['investor_profile_id'],
                    );

                    if (($closing['status'] ?? null) === 'completed') {
                        $closed++;
                    }

                    $commissionDue += (int) ($commission['total_due_amount'] ?? 0);
                    $commissionCollected += (int) ($commission['total_collected_amount'] ?? 0);
                }

                $leadCount = $cohort->count();

                return [
                    'source_key' => $sourceKey,
                    'source' => self::SOURCES[$sourceKey] ?? 'Diğer',
                    'campaign' => $campaign,
                    'leads' => $leadCount,
                    'seller_leads' => $sellerLeads,
                    'investor_leads' => $investorLeads,
                    'profiled_leads' => $profiled,
                    'qualified_leads' => $qualified,
                    'offered_leads' => $offers,
                    'accepted_deals' => $deals->count(),
                    'closed_deals' => $closed,
                    'commission_due_amount' => $commissionDue,
                    'commission_collected_amount' => $commissionCollected,
                    'profile_rate' => $this->rate($profiled, $leadCount),
                    'qualification_rate' => $this->rate($qualified, $leadCount),
                    'offer_rate' => $this->rate($offers, $leadCount),
                    'closing_rate' => $this->rate($closed, $leadCount),
                    'automatic_outbound_allowed' => false,
                    'contains_customer_payload' => false,
                ];
            })
            ->sortByDesc('leads')
            ->values();

        $totals = [
            'leads' => $rows->sum('leads'),
            'seller_leads' => $rows->sum('seller_leads'),
            'investor_leads' => $rows->sum('investor_leads'),
            'profiled_leads' => $rows->sum('profiled_leads'),
            'qualified_leads' => $rows->sum('qualified_leads'),
            'offered_leads' => $rows->sum('offered_leads'),
            'accepted_deals' => $rows->sum('accepted_deals'),
            'closed_deals' => $rows->sum('closed_deals'),
            'commission_due_amount' => $rows->sum('commission_due_amount'),
            'commission_collected_amount' => $rows->sum('commission_collected_amount'),
            'profile_rate' => $this->rate($rows->sum('profiled_leads'), $rows->sum('leads')),
            'qualification_rate' => $this->rate($rows->sum('qualified_leads'), $rows->sum('leads')),
            'offer_rate' => $this->rate($rows->sum('offered_leads'), $rows->sum('leads')),
            'closing_rate' => $this->rate($rows->sum('closed_deals'), $rows->sum('leads')),
            'unattributed_leads' => $rows
                ->where('campaign', 'Belirtilmedi')
                ->sum('leads'),
            'automatic_outbound_allowed' => false,
            'contains_customer_payload' => false,
        ];

        return [
            'from' => $fromDate->toDateString(),
            'until' => $untilDate->toDateString(),
            'rows' => $rows->all(),
            'totals' => $totals,
            'automatic_outbound_allowed' => false,
            'contains_customer_payload' => false,
        ];
    }

    public function updateAttribution(
        RealEstateProfile $profile,
        array $input,
        ?User $operator = null,
    ): array {
        abort_unless($profile->belongsToIsolatedProductionScope(), 403);

        $validated = Validator::make($input, [
            'source' => ['required', 'string', 'in:'.implode(',', array_keys(self::SOURCES))],
            'campaign' => ['nullable', 'string', 'max:120'],
            'ad_set' => ['nullable', 'string', 'max:120'],
            'creative' => ['nullable', 'string', 'max:120'],
        ])->validate();

        return DB::transaction(function () use ($profile, $validated, $operator): array {
            $locked = RealEstateProfile::query()
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($locked->belongsToIsolatedProductionScope(), 403);

            $data = is_array($locked->data) ? $locked->data : [];
            $attribution = [
                'source' => $validated['source'],
                'campaign' => trim((string) ($validated['campaign'] ?? '')) ?: null,
                'ad_set' => trim((string) ($validated['ad_set'] ?? '')) ?: null,
                'creative' => trim((string) ($validated['creative'] ?? '')) ?: null,
                'updated_at' => now()->toIso8601String(),
                'updated_by_user_id' => $operator?->id,
                'automatic_outbound_allowed' => false,
                'customer_follow_up_allowed' => false,
            ];

            $data[self::DATA_KEY] = $attribution;
            $locked->forceFill(['data' => $data])->saveQuietly();

            $locked->conversation?->activities()->create([
                'user_id' => RealEstateIsolationService::USER_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'performed_by_user_id' => $operator?->id,
                'type' => 'real_estate_marketing_attribution',
                'title' => 'Reklam kaynağı güncellendi',
                'description' => self::SOURCES[$validated['source']],
                'new_value' => $validated['source'],
                'meta' => [
                    'scope' => 'isolated_real_estate',
                    'real_estate_profile_id' => $locked->id,
                    'source' => $validated['source'],
                    'has_campaign' => filled($attribution['campaign']),
                    'has_ad_set' => filled($attribution['ad_set']),
                    'has_creative' => filled($attribution['creative']),
                    'automatic_outbound_allowed' => false,
                    'follow_up_scheduling_allowed' => false,
                    'contains_customer_pii' => false,
                    'contains_private_seller_floor' => false,
                ],
            ]);

            return $this->safeAttribution($attribution);
        });
    }

    public function attributionCandidates(): Collection
    {
        return RealEstateProfile::query()
            ->isolatedProduction()
            ->with('conversation')
            ->whereIn('profile_type', ['seller', 'investor', 'buyer'])
            ->latest('id')
            ->get();
    }

    public function storedAttribution(RealEstateProfile $profile): array
    {
        if (! $profile->belongsToIsolatedProductionScope()) {
            return [];
        }

        $stored = data_get($profile->data, self::DATA_KEY);

        return is_array($stored) ? $this->safeAttribution($stored) : [];
    }

    private function attribution(?RealEstateProfile $profile, $conversation): array
    {
        $stored = is_array(data_get($profile?->data, self::DATA_KEY))
            ? data_get($profile?->data, self::DATA_KEY)
            : [];
        $source = (string) ($stored['source'] ?? $this->legacySource($profile, $conversation));
        $sourceKey = array_key_exists($source, self::SOURCES) ? $source : 'diger';
        $campaign = trim((string) (
            $stored['campaign']
            ?? data_get($profile?->data, 'campaign_name')
            ?? data_get($profile?->data, 'campaign')
            ?? ''
        ));

        return [
            'source_key' => $sourceKey,
            'campaign' => $campaign !== '' ? $campaign : 'Belirtilmedi',
        ];
    }

    private function legacySource(?RealEstateProfile $profile, $conversation): string
    {
        $raw = mb_strtolower(trim((string) (
            data_get($profile?->data, 'lead_source')
            ?? data_get($profile?->data, 'source')
            ?? $conversation->channel
            ?? 'whatsapp'
        )));

        return match (true) {
            str_contains($raw, 'instagram') => 'instagram',
            str_contains($raw, 'facebook'), str_contains($raw, 'meta') => 'facebook',
            str_contains($raw, 'google') => 'google_ads',
            str_contains($raw, 'organik'), str_contains($raw, 'organic') => 'organik',
            str_contains($raw, 'referans'), str_contains($raw, 'referral') => 'referans',
            str_contains($raw, 'web') => 'web',
            str_contains($raw, 'whatsapp') => 'whatsapp',
            default => 'diger',
        };
    }

    private function offeredConversationIds(Collection $conversationIds): Collection
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        return CrmActivity::query()
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('type', 'real_estate_operator_call')
            ->whereIn('conversation_control_id', $conversationIds)
            ->whereHas('conversationControl', fn ($query) => $query
                ->where('user_id', RealEstateIsolationService::USER_ID)
                ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
                ->where('ai_bot_id', RealEstateIsolationService::BOT_ID))
            ->get()
            ->filter(fn (CrmActivity $activity): bool => is_numeric(data_get($activity->meta, 'offer_amount'))
                && (int) data_get($activity->meta, 'offer_amount') > 0)
            ->pluck('conversation_control_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function safeAttribution(array $attribution): array
    {
        return [
            'source' => array_key_exists((string) ($attribution['source'] ?? ''), self::SOURCES)
                ? (string) $attribution['source']
                : 'diger',
            'source_label' => self::SOURCES[$attribution['source'] ?? 'diger'] ?? 'Diğer',
            'campaign' => filled($attribution['campaign'] ?? null) ? (string) $attribution['campaign'] : null,
            'ad_set' => filled($attribution['ad_set'] ?? null) ? (string) $attribution['ad_set'] : null,
            'creative' => filled($attribution['creative'] ?? null) ? (string) $attribution['creative'] : null,
            'automatic_outbound_allowed' => false,
            'customer_follow_up_allowed' => false,
            'contains_customer_pii' => false,
            'contains_private_seller_floor' => false,
        ];
    }

    private function dateRange(?string $from, ?string $until): array
    {
        $untilDate = filled($until) ? Carbon::parse($until)->endOfDay() : now()->endOfDay();
        $fromDate = filled($from) ? Carbon::parse($from)->startOfDay() : $untilDate->copy()->subDays(29)->startOfDay();

        if ($fromDate->greaterThan($untilDate)) {
            [$fromDate, $untilDate] = [$untilDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [$fromDate, $untilDate];
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : 0.0;
    }
}
