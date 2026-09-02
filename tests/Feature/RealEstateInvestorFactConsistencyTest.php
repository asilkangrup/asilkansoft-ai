<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateFactConsistencyActionService;
use App\Services\RealEstateFactConsistencyService;
use App\Services\RealEstateMatchVerificationFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorFactConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unmarked_investor_mandate_change_is_held_for_confirmation(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'investor-fact-conflict');
        $existing = [
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'property_type' => 'arsa',
            'area_min_sqm' => 1000,
            'area_max_sqm' => 1500,
            'budget_max' => 5_000_000,
            'accepts_shared_title' => false,
            'target_discount_percent' => 15,
        ];

        $result = app(RealEstateFactConsistencyService::class)->reconcile(
            conversation: $conversation,
            profileType: 'investor',
            existing: $existing,
            incoming: [
                'district' => 'Bodrum',
                'budget_max' => 6_000_000,
            ],
            customerMessage: 'Bodrum olabilir, bütçe 6 milyon.',
        );

        $this->assertSame('Marmaris', $result['district']);
        $this->assertSame(5_000_000, $result['budget_max']);
        $this->assertSame(
            'confirmation_required',
            $result['fact_consistency_intelligence']['status']
        );
        $this->assertSame('investor', $result['fact_consistency_intelligence']['profile_type']);
        $this->assertSame(2, $result['fact_consistency_intelligence']['pending_count']);
        $this->assertSame(
            'district',
            $result['fact_consistency_intelligence']['highest_priority_field']
        );
        $this->assertFalse(
            $result['fact_consistency_intelligence']['guardrails']['unconfirmed_investor_mandate_may_be_used_for_matching']
        );
        $this->assertFalse(
            $result['fact_consistency_intelligence']['guardrails']['follow_up_scheduling_allowed']
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_explicit_investor_mandate_change_updates_immediately(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'investor-fact-explicit');

        $result = app(RealEstateFactConsistencyService::class)->reconcile(
            conversation: $conversation,
            profileType: 'investor',
            existing: [
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'property_type' => 'arsa',
                'budget_max' => 5_000_000,
            ],
            incoming: [
                'district' => 'Bodrum',
                'budget_max' => 6_000_000,
            ],
            customerMessage: 'Artık sadece Bodrum bakıyorum, bütçemi 6 milyona çıkardım.',
        );

        $this->assertSame('Bodrum', $result['district']);
        $this->assertSame(6_000_000, $result['budget_max']);
        $this->assertSame(
            'consistent',
            $result['fact_consistency_intelligence']['status']
        );
        $this->assertContains(
            'district',
            $result['fact_consistency_intelligence']['resolved_fields']
        );
        $this->assertContains(
            'budget_max',
            $result['fact_consistency_intelligence']['resolved_fields']
        );
    }

    public function test_repeating_pending_investor_value_confirms_it(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'investor-fact-repeat');
        $service = app(RealEstateFactConsistencyService::class);
        $existing = [
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'property_type' => 'arsa',
            'budget_max' => 5_000_000,
        ];

        $first = $service->reconcile(
            conversation: $conversation,
            profileType: 'investor',
            existing: $existing,
            incoming: ['budget_max' => 6_000_000],
            customerMessage: 'Bütçe 6 milyon.',
        );
        $second = $service->reconcile(
            conversation: $conversation,
            profileType: 'investor',
            existing: $first,
            incoming: ['budget_max' => 6_000_000],
            customerMessage: 'Evet, 6 milyon bütçe.',
        );

        $this->assertSame(6_000_000, $second['budget_max']);
        $this->assertSame(
            'consistent',
            $second['fact_consistency_intelligence']['status']
        );
        $this->assertContains(
            'budget_max',
            $second['fact_consistency_intelligence']['resolved_fields']
        );
    }

    public function test_investor_fact_conflict_becomes_blocking_action_and_quarantines_matches(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'investor-fact-action');
        $conversation->update([
            'tags' => ['business:real_estate_investor', 'real_estate:match:strong'],
        ]);
        $profile = $this->profileQuietly($conversation, 'investor', [
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'property_type' => 'arsa',
            'budget_max' => 5_000_000,
            'fact_consistency_intelligence' => [
                'status' => 'confirmation_required',
                'profile_type' => 'investor',
                'pending_count' => 1,
                'highest_priority_field' => 'budget_max',
                'confirmation_question' => 'Maksimum bütçe bilgisini netleştirelim: daha önce 5.000.000 TL, şimdi 6.000.000 TL bilgisi geçti. Güncel yatırım kriterini hangisi?',
                'pending_conflicts' => [[
                    'field' => 'budget_max',
                    'current_value' => 5_000_000,
                    'proposed_value' => 6_000_000,
                ]],
            ],
            'decision_intelligence' => [
                'stage' => 'qualified',
            ],
            'opportunity_matches' => [[
                'seller_profile_id' => 999,
                'investor_profile_id' => 1,
                'candidate_role' => 'seller',
                'match_score' => 91,
            ]],
        ]);

        $plan = app(RealEstateFactConsistencyActionService::class)
            ->sync($conversation->fresh());

        $this->assertSame('confirm_investor_mandate_conflict', $plan['action_code']);
        $this->assertTrue($plan['blocking']);
        $this->assertSame(1, $plan['quarantined_match_count']);
        $this->assertSame([], $profile->fresh()->data['opportunity_matches']);
        $this->assertTrue(
            (bool) $profile->fresh()->data['opportunity_match_summary']['blocked_by_fact_consistency']
        );
        $this->assertNotContains('real_estate:match:strong', $conversation->fresh()->etiketler());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);

        $event = RealEstateNextBestActionEvent::query()
            ->where('action_code', 'confirm_investor_mandate_conflict')
            ->firstOrFail();
        $eventJson = json_encode(
            $event->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->assertStringNotContainsString('5000000', (string) $eventJson);
        $this->assertStringNotContainsString('6000000', (string) $eventJson);
        $this->assertSame(1, (int) ($event->metadata['quarantined_match_count'] ?? 0));
    }

    public function test_match_filter_fails_closed_for_current_investor_with_unresolved_mandate(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'investor-filter-conflict');
        $profile = $this->profileQuietly($conversation, 'investor', [
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'property_type' => 'arsa',
            'budget_max' => 5_000_000,
            'fact_consistency_intelligence' => [
                'status' => 'confirmation_required',
                'profile_type' => 'investor',
                'pending_count' => 1,
                'highest_priority_field' => 'district',
                'confirmation_question' => 'Hedef ilçeyi netleştir.',
                'pending_conflicts' => [[
                    'field' => 'district',
                    'current_value' => 'Marmaris',
                    'proposed_value' => 'Bodrum',
                ]],
            ],
            'opportunity_matches' => [[
                'seller_profile_id' => 999,
                'investor_profile_id' => 1,
                'candidate_role' => 'seller',
                'match_score' => 88,
            ]],
        ]);

        $matches = app(RealEstateMatchVerificationFilterService::class)
            ->process($conversation->fresh());

        $this->assertSame([], $matches);
        $profile->refresh();
        $this->assertSame([], $profile->data['opportunity_matches']);
        $this->assertTrue(
            (bool) ($profile->data['opportunity_match_summary']['fact_consistency_filtered'] ?? false)
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_filter_source_checks_both_seller_and_investor_fact_consistency(): void
    {
        $source = file_get_contents(app_path('Services/RealEstateMatchVerificationFilterService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('profileFactConsistent($seller)', $source);
        $this->assertStringContainsString('profileFactConsistent($investor)', $source);
        $this->assertStringContainsString("'fact_consistency_filtered' => true", $source);
    }

    private function profileQuietly(
        ConversationControl $conversation,
        string $type,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => $type,
                'data' => $data,
                'valuation' => [],
                'completeness_score' => 90,
                'confidence_score' => 90,
            ])
        );
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'investor-fact-consistency@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'investor-fact-consistency',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }

    private function conversation(AiBot $bot, string $sessionId): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => ['business:real_estate_investor'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
