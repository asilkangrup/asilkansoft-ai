<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCommercialConsistencyGuardService;
use App\Services\RealEstateCommercialConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateCommercialConsistencyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_floor_above_asking_blocks_matching_and_handoff_without_leaking_prices(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'commercial-seller', 'seller');
        $profile = $this->profile($conversation, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Bodrum',
            'asking_price' => 4_000_000,
            'minimum_price' => 4_500_000,
            'opportunity_matches' => [[
                'candidate_profile_id' => 999,
                'match_score' => 90,
            ]],
            'investor_offer_handoff_intelligence' => [
                'status' => 'ready',
                'ready_for_operator_handoff' => true,
                'checks' => ['authorization_ready' => true],
                'guardrails' => ['investor_presentation_export_allowed' => true],
            ],
        ]);

        $summary = app(RealEstateCommercialConsistencyGuardService::class)->sync($profile);
        $fresh = $profile->fresh();
        $data = $fresh->data;

        $this->assertSame('confirmation_required', $summary['status']);
        $this->assertSame(['seller_floor_above_asking'], $summary['conflict_codes']);
        $this->assertSame([], $data['opportunity_matches']);
        $this->assertTrue($data['opportunity_match_summary']['commercial_consistency_blocked']);
        $this->assertSame(
            'commercial_confirmation_required',
            $data['investor_offer_handoff_intelligence']['status']
        );
        $this->assertFalse(
            $data['investor_offer_handoff_intelligence']['ready_for_operator_handoff']
        );
        $this->assertFalse(
            $data['investor_offer_handoff_intelligence']['guardrails']['investor_presentation_export_allowed']
        );
        $this->assertSame(
            'confirm_commercial_terms_conflict',
            $data['next_best_action_intelligence']['action_code']
        );
        $this->assertTrue($data['next_best_action_intelligence']['blocking']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertContains(
            'real_estate:commercial_consistency:blocked',
            $conversation->fresh()->etiketler()
        );

        $safeJson = json_encode([
            $summary,
            $data['next_best_action_intelligence'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('4000000', (string) $safeJson);
        $this->assertStringNotContainsString('4500000', (string) $safeJson);
        $this->assertFalse(
            $summary['guardrails']['confidential_seller_floor_included']
        );
    }

    public function test_inverted_investor_budget_is_blocked_until_range_becomes_consistent(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'commercial-investor', 'investor');
        $profile = $this->profile($conversation, 'investor', [
            'city' => 'Muğla',
            'property_type' => 'arsa',
            'budget_min' => 8_000_000,
            'budget_max' => 5_000_000,
            'opportunity_matches' => [[
                'candidate_profile_id' => 123,
                'match_score' => 82,
            ]],
        ]);
        $guard = app(RealEstateCommercialConsistencyGuardService::class);

        $blocked = $guard->sync($profile);
        $this->assertSame('confirmation_required', $blocked['status']);
        $this->assertSame([], $profile->fresh()->data['opportunity_matches']);
        $this->assertSame(
            'confirm_commercial_terms_conflict',
            $profile->fresh()->data['next_best_action_intelligence']['action_code']
        );

        $data = $profile->fresh()->data;
        $data['budget_min'] = 3_000_000;
        $data['budget_max'] = 5_000_000;
        $profile->forceFill(['data' => $data])->saveQuietly();

        $consistent = $guard->sync($profile->fresh());

        $this->assertSame('consistent', $consistent['status']);
        $this->assertContains(
            'real_estate:commercial_consistency:clear',
            $conversation->fresh()->etiketler()
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_consistent_commercial_terms_do_not_destroy_existing_safe_match(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'commercial-safe', 'seller');
        $profile = $this->profile($conversation, 'seller', [
            'asking_price' => 5_000_000,
            'minimum_price' => 4_000_000,
            'opportunity_matches' => [[
                'candidate_profile_id' => 55,
                'match_score' => 77,
            ]],
        ]);

        $summary = app(RealEstateCommercialConsistencyGuardService::class)->sync($profile);

        $this->assertSame('consistent', $summary['status']);
        $this->assertCount(1, $profile->fresh()->data['opportunity_matches']);
        $this->assertContains(
            'real_estate:commercial_consistency:clear',
            $conversation->fresh()->etiketler()
        );
    }

    public function test_foreign_organization_fails_closed_without_mutation(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'commercial-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 'commercial-foreign', 'seller');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();
        $profile = $this->profile($conversation, 'seller', [
            'asking_price' => 4_000_000,
            'minimum_price' => 4_500_000,
            'opportunity_matches' => [['match_score' => 90]],
        ]);

        $before = $profile->data;
        $summary = app(RealEstateCommercialConsistencyGuardService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertSame($before, $profile->fresh()->data);
        $this->assertArrayNotHasKey(
            'commercial_consistency_intelligence',
            $profile->fresh()->data
        );
    }

    public function test_health_snapshot_contains_counts_only_and_observer_runs_guard_last(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'commercial-health-seller', 'seller');
        $investorConversation = $this->conversation($bot, 'commercial-health-investor', 'investor');
        $this->profile($sellerConversation, 'seller', [
            'asking_price' => 4_000_000,
            'minimum_price' => 4_500_000,
        ]);
        $this->profile($investorConversation, 'investor', [
            'budget_min' => 8_000_000,
            'budget_max' => 5_000_000,
        ]);

        $health = app(RealEstateCommercialConsistencyGuardService::class)->health();

        $this->assertSame('attention_required', $health['state']);
        $this->assertSame(2, $health['counts']['profiles']);
        $this->assertSame(2, $health['counts']['blocked']);
        $this->assertSame(1, $health['counts']['seller_floor_above_asking']);
        $this->assertSame(1, $health['counts']['investor_budget_range_inverted']);
        $this->assertFalse($health['privacy']['customer_pii_included']);
        $this->assertFalse($health['privacy']['commercial_values_included']);

        $json = json_encode($health, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('4000000', (string) $json);
        $this->assertStringNotContainsString('4500000', (string) $json);
        $this->assertStringNotContainsString('8000000', (string) $json);
        $this->assertStringNotContainsString('5000000', (string) $json);

        $observerSource = file_get_contents(app_path('Observers/RealEstateProfileObserver.php'));
        $this->assertIsString($observerSource);
        $this->assertStringContainsString(
            'RealEstateCommercialConsistencyGuardService::class)->sync($profile)',
            $observerSource
        );
        $this->assertGreaterThan(
            strpos($observerSource, 'RealEstateNextBestActionDecisionBridgeService::class'),
            strpos($observerSource, 'RealEstateCommercialConsistencyGuardService::class)->sync')
        );
    }

    public function test_assessment_never_embeds_commercial_values(): void
    {
        $assessment = app(RealEstateCommercialConsistencyService::class)->assess('seller', [
            'asking_price' => 3_900_000,
            'minimum_price' => 4_100_000,
        ]);

        $json = json_encode($assessment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSame('confirmation_required', $assessment['status']);
        $this->assertStringNotContainsString('3900000', (string) $json);
        $this->assertStringNotContainsString('4100000', (string) $json);
    }

    private function profile(
        ConversationControl $conversation,
        string $profileType,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => $profileType,
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
            'email' => 'commercial-consistency@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'commercial-consistency-isolated',
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

    private function conversation(
        AiBot $bot,
        string $sessionId,
        string $role,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => ['business:real_estate_'.$role],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
