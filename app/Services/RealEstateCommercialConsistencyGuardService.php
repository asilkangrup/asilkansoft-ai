<?php

namespace App\Services;

use App\Models\RealEstateProfile;

class RealEstateCommercialConsistencyGuardService
{
    private const TAG_PREFIX = 'real_estate:commercial_consistency:';

    public function sync(RealEstateProfile $profile): array
    {
        if (! $profile->belongsToIsolatedProductionScope()) {
            return [];
        }

        $conversation = $profile->conversation()->first();

        if (! app(RealEstateIsolationService::class)->supportsConversation($conversation)) {
            return [];
        }

        $service = app(RealEstateCommercialConsistencyService::class);
        $data = is_array($profile->data) ? $profile->data : [];
        $data = $service->reconcile(
            conversation: $conversation,
            profileType: (string) $profile->profile_type,
            data: $data,
        );
        $summary = $service->summary($data);
        $blocked = ($summary['status'] ?? 'consistent') === 'confirmation_required';

        if ($blocked) {
            $data['opportunity_matches'] = [];
            $data['opportunity_match_summary'] = [
                'count' => 0,
                'strongest_score' => null,
                'strongest_grade' => null,
                'commercial_consistency_blocked' => true,
                'updated_at' => now()->toIso8601String(),
            ];

            if ((string) $profile->profile_type === 'seller') {
                $handoff = is_array($data['investor_offer_handoff_intelligence'] ?? null)
                    ? $data['investor_offer_handoff_intelligence']
                    : [];

                if ($handoff !== []) {
                    $handoff['status'] = 'commercial_confirmation_required';
                    $handoff['ready_for_operator_handoff'] = false;
                    $handoff['checks'] = is_array($handoff['checks'] ?? null)
                        ? $handoff['checks']
                        : [];
                    $handoff['checks']['commercial_consistent'] = false;
                    $handoff['recommended_operator_action'] = $this->operatorAction(
                        $summary['highest_priority_conflict'] ?? null
                    );
                    $handoff['guardrails'] = is_array($handoff['guardrails'] ?? null)
                        ? $handoff['guardrails']
                        : [];
                    $handoff['guardrails']['investor_presentation_export_allowed'] = false;
                    $handoff['guardrails']['commercial_terms_confirmation_required'] = true;
                    $handoff['updated_at'] = now()->toIso8601String();
                    $data['investor_offer_handoff_intelligence'] = $handoff;
                }
            }

            $question = trim((string) ($summary['confirmation_question'] ?? ''));
            $data['next_best_action_intelligence'] = [
                'action_code' => 'confirm_commercial_terms_conflict',
                'priority' => 'high',
                'action_text' => $question !== ''
                    ? $question
                    : 'Çelişkili fiyat veya bütçe bilgisini müşteriden tek soruyla netleştir.',
                'single_question' => $question !== '' ? $question : null,
                'blocking' => true,
                'reason_codes' => array_values(array_map(
                    static fn (string $code): string => 'commercial_'.$code,
                    array_filter(
                        is_array($summary['conflict_codes'] ?? null)
                            ? $summary['conflict_codes']
                            : [],
                        'is_string'
                    )
                )),
                'match_count' => 0,
                'guardrails' => [
                    'automatic_customer_follow_up_allowed' => false,
                    'automatic_investor_outreach_allowed' => false,
                    'private_seller_floor_included' => false,
                    'commercial_terms_must_be_confirmed_before_matching' => true,
                ],
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $profile->forceFill(['data' => $data])->saveQuietly();

        $tags = collect($conversation->etiketler())
            ->filter(fn ($tag): bool => is_string($tag) && ! str_starts_with($tag, self::TAG_PREFIX))
            ->push(self::TAG_PREFIX.($blocked ? 'blocked' : 'clear'))
            ->unique()
            ->values()
            ->all();

        $attributes = [
            'tags' => $tags,
            'next_follow_up_at' => null,
        ];

        if ($blocked) {
            $attributes['next_best_action'] = $data['next_best_action_intelligence']['action_text'];
        }

        $conversation->forceFill($attributes)->saveQuietly();

        return $summary;
    }

    public function health(): array
    {
        $service = app(RealEstateCommercialConsistencyService::class);
        $counts = [
            'profiles' => 0,
            'blocked' => 0,
            'seller_floor_above_asking' => 0,
            'investor_budget_range_inverted' => 0,
        ];

        RealEstateProfile::query()
            ->isolatedProduction()
            ->get(['id', 'profile_type', 'data'])
            ->each(function (RealEstateProfile $profile) use ($service, &$counts): void {
                $counts['profiles']++;
                $assessment = $service->assess(
                    (string) $profile->profile_type,
                    is_array($profile->data) ? $profile->data : []
                );

                if (($assessment['status'] ?? 'consistent') !== 'confirmation_required') {
                    return;
                }

                $counts['blocked']++;

                foreach ($assessment['conflict_codes'] ?? [] as $code) {
                    if (array_key_exists($code, $counts)) {
                        $counts[$code]++;
                    }
                }
            });

        return [
            'state' => $counts['blocked'] > 0 ? 'attention_required' : 'healthy',
            'counts' => $counts,
            'scope' => [
                'user_id' => RealEstateIsolationService::USER_ID,
                'organization_id' => RealEstateIsolationService::ORGANIZATION_ID,
                'ai_bot_id' => RealEstateIsolationService::BOT_ID,
                'instance' => RealEstateIsolationService::INSTANCE,
            ],
            'privacy' => [
                'customer_pii_included' => false,
                'commercial_values_included' => false,
                'raw_messages_included' => false,
            ],
        ];
    }

    private function operatorAction(mixed $conflict): string
    {
        return match ($conflict) {
            'seller_floor_above_asking' => 'Satıcının satış beklentisi ile gizli hızlı-nakit alt sınırı birbiriyle çelişiyor. Güncel rakamları satıcıdan tek soruyla yeniden teyit et; doğrulanana kadar yatırımcı sunumu veya fiyat pazarlığı başlatma.',
            default => 'Çelişkili ticari şartı müşteriden teyit et; doğrulanana kadar eşleştirme veya yatırımcı sunumu yapma.',
        };
    }
}
