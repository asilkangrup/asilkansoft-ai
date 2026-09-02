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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateFactConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unmarked_stable_property_change_is_held_for_confirmation(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'fact-conflict');
        $existing = [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 5_000_000,
            'minimum_price' => 4_250_000,
        ];

        $result = app(RealEstateFactConsistencyService::class)->reconcile(
            conversation: $conversation,
            profileType: 'seller',
            existing: $existing,
            incoming: [
                'district' => 'Bodrum',
                'area_sqm' => 1500,
                'asking_price' => 4_900_000,
                'minimum_price' => 4_100_000,
            ],
            customerMessage: 'Bodrum 1500 metrekare, fiyat 4.9 milyon.',
        );

        $this->assertSame('Marmaris', $result['district']);
        $this->assertSame(1200, $result['area_sqm']);
        $this->assertSame(4_900_000, $result['asking_price']);
        $this->assertSame(4_100_000, $result['minimum_price']);
        $this->assertSame(
            'confirmation_required',
            $result['fact_consistency_intelligence']['status']
        );
        $this->assertSame(2, $result['fact_consistency_intelligence']['pending_count']);
        $this->assertSame(
            'district',
            $result['fact_consistency_intelligence']['highest_priority_field']
        );
        $this->assertStringContainsString(
            'Marmaris',
            $result['fact_consistency_intelligence']['confirmation_question']
        );
        $this->assertStringContainsString(
            'Bodrum',
            $result['fact_consistency_intelligence']['confirmation_question']
        );

        $json = json_encode(
            $result['fact_consistency_intelligence'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->assertStringNotContainsString('4250000', (string) $json);
        $this->assertStringNotContainsString('4100000', (string) $json);
        $this->assertFalse(
            $result['fact_consistency_intelligence']['guardrails']['follow_up_scheduling_allowed']
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_repeating_pending_value_on_next_turn_confirms_it(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'fact-repeat');
        $service = app(RealEstateFactConsistencyService::class);
        $existing = [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
        ];

        $first = $service->reconcile(
            conversation: $conversation,
            profileType: 'seller',
            existing: $existing,
            incoming: ['area_sqm' => 1500],
            customerMessage: '1500 metrekare.',
        );
        $second = $service->reconcile(
            conversation: $conversation,
            profileType: 'seller',
            existing: $first,
            incoming: ['area_sqm' => 1500],
            customerMessage: 'Evet 1500 metrekare.',
        );

        $this->assertSame(1500, $second['area_sqm']);
        $this->assertSame(
            'consistent',
            $second['fact_consistency_intelligence']['status']
        );
        $this->assertSame(0, $second['fact_consistency_intelligence']['pending_count']);
        $this->assertContains(
            'area_sqm',
            $second['fact_consistency_intelligence']['resolved_fields']
        );
    }

    public function test_explicit_customer_correction_updates_stable_fact_immediately(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'fact-correction');

        $result = app(RealEstateFactConsistencyService::class)->reconcile(
            conversation: $conversation,
            profileType: 'seller',
            existing: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
            ],
            incoming: ['area_sqm' => 1500],
            customerMessage: 'Pardon, yanlış söyledim; doğrusu 1500 metrekare.',
        );

        $this->assertSame(1500, $result['area_sqm']);
        $this->assertSame(
            'consistent',
            $result['fact_consistency_intelligence']['status']
        );
        $this->assertContains(
            'area_sqm',
            $result['fact_consistency_intelligence']['resolved_fields']
        );
    }

    public function test_fact_conflict_becomes_final_blocking_next_action_without_follow_up(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'fact-action');
        $profile = $this->profileQuietly($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 5_000_000,
            'minimum_price' => 4_250_000,
            'fact_consistency_intelligence' => [
                'status' => 'confirmation_required',
                'pending_count' => 1,
                'highest_priority_field' => 'district',
                'confirmation_question' => 'İlçe bilgisini netleştirelim: daha önce Marmaris, şimdi Bodrum bilgisi geçti. Hangisi doğru?',
                'pending_conflicts' => [[
                    'field' => 'district',
                    'current_value' => 'Marmaris',
                    'proposed_value' => 'Bodrum',
                ]],
            ],
            'decision_intelligence' => [
                'stage' => 'ready',
                'missing_critical_data' => [],
                'ready_for_match' => true,
                'valuation_research_needed' => false,
                'verification_status' => 'verified',
                'evidence_sufficient_for_matching' => true,
            ],
            'opportunity_matches' => [[
                'candidate_role' => 'investor',
                'match_score' => 90,
            ]],
        ]);

        $plan = app(RealEstateFactConsistencyActionService::class)
            ->sync($conversation->fresh());

        $this->assertSame('confirm_property_fact_conflict', $plan['action_code']);
        $this->assertTrue($plan['blocking']);
        $this->assertSame('high', $plan['priority']);
        $this->assertSame($plan['single_question'], $conversation->fresh()->next_best_action);
        $this->assertContains('conflict_district', $plan['reason_codes']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);

        $stored = $profile->fresh()->data['next_best_action_intelligence'];
        $this->assertSame('confirm_property_fact_conflict', $stored['action_code']);

        $eventJson = json_encode(
            RealEstateNextBestActionEvent::query()
                ->where('action_code', 'confirm_property_fact_conflict')
                ->firstOrFail()
                ->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->assertStringNotContainsString('4250000', (string) $eventJson);
        $this->assertStringNotContainsString('Marmaris', (string) $eventJson);
        $this->assertStringNotContainsString('Bodrum', (string) $eventJson);
    }

    public function test_same_user_and_bot_in_foreign_organization_fail_closed(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'fact-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 'fact-foreign-scope');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $existing = ['district' => 'Marmaris', 'area_sqm' => 1200];
        $result = app(RealEstateFactConsistencyService::class)->reconcile(
            conversation: $conversation,
            profileType: 'seller',
            existing: $existing,
            incoming: ['district' => 'Bodrum'],
            customerMessage: 'Bodrum.',
        );

        $this->assertSame($existing, $result);
        $this->assertArrayNotHasKey('fact_consistency_intelligence', $result);
        $this->assertNull(
            app(RealEstateFactConsistencyActionService::class)
                ->sync($conversation)
        );
        $this->assertSame(0, RealEstateNextBestActionEvent::query()->count());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_profile_extractor_wiring_strips_internal_intelligence_and_uses_consistency_guard(): void
    {
        $source = file_get_contents(app_path('Services/RealEstateProfileService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "'existing_profile' => \$this->extractableProfile(\$profile)",
            $source
        );
        $this->assertStringContainsString(
            'RealEstateFactConsistencyService::class)->reconcile',
            $source
        );
        $this->assertStringContainsString(
            "'fact_consistency_intelligence'",
            file_get_contents(app_path('Services/RealEstateFactConsistencyService.php'))
        );
    }

    private function profileQuietly(
        ConversationControl $conversation,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => 'seller',
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
            'email' => 'fact-consistency@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'fact-consistency-isolated',
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
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
