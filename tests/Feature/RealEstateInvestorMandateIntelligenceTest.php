<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateInvestorMandateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorMandateIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_investor_mandate_is_persisted_without_contact_or_follow_up_side_effects(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'mandate-complete');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'property_type' => 'arsa',
                'budget_max' => 5_000_000,
                'investment_goal' => 'değer artışı',
                'timeline' => '30 gün',
                'financing' => 'cash',
                'risk_preference' => 'düşük',
                'area_min_sqm' => 900,
                'area_max_sqm' => 1500,
                'location_flexibility' => 'strict_district',
                'accepts_shared_title' => false,
                'target_discount_percent' => 20,
                'notes' => 'Beni 05550000000 numarasından arayın, mail test@example.com',
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 90,
        ]);

        $profile->refresh();
        $summary = $profile->data['investor_mandate_intelligence'];

        $this->assertSame(100, $summary['strength_score']);
        $this->assertSame('strong', $summary['status']);
        $this->assertTrue($summary['core_ready']);
        $this->assertSame([], $summary['missing_high_value_criteria']);
        $this->assertNull($summary['recommended_next_question']);
        $this->assertSame(900.0, (float) $summary['explicit_match_constraints']['area_min_sqm']);
        $this->assertSame(1500.0, (float) $summary['explicit_match_constraints']['area_max_sqm']);
        $this->assertSame('strict_district', $summary['explicit_match_constraints']['location_flexibility']);
        $this->assertFalse($summary['explicit_match_constraints']['accepts_shared_title']);
        $this->assertSame(20.0, (float) $summary['explicit_match_constraints']['target_discount_percent']);
        $this->assertTrue($summary['guardrails']['unknown_preferences_are_not_hard_rejections']);
        $this->assertFalse($summary['guardrails']['seller_private_floor_exposed']);
        $this->assertFalse($summary['guardrails']['follow_up_scheduling_allowed']);

        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('05550000000', (string) $json);
        $this->assertStringNotContainsString('test@example.com', (string) $json);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_partial_mandate_recommends_one_high_value_question_without_inventing_constraints(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'mandate-partial');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'city' => 'Aydın',
                'property_type' => 'tarla',
                'budget_max' => 3_500_000,
            ],
            'valuation' => [],
            'completeness_score' => 50,
            'confidence_score' => 60,
        ]);

        $summary = app(RealEstateInvestorMandateService::class)
            ->summaryForProfile($profile->fresh());

        $this->assertSame(35, $summary['strength_score']);
        $this->assertSame('building', $summary['status']);
        $this->assertTrue($summary['core_ready']);
        $this->assertSame(
            'Yatırım hedefini: al-sat, kira getirisi veya değer artışı olarak netleştir.',
            $summary['recommended_next_question']
        );
        $this->assertNull($summary['explicit_match_constraints']['area_min_sqm']);
        $this->assertNull($summary['explicit_match_constraints']['area_max_sqm']);
        $this->assertNull($summary['explicit_match_constraints']['location_flexibility']);
        $this->assertNull($summary['explicit_match_constraints']['accepts_shared_title']);
        $this->assertNull($summary['explicit_match_constraints']['target_discount_percent']);
        $this->assertContains('area_range', $summary['missing_high_value_criteria']);
        $this->assertContains('shared_title_preference', $summary['missing_high_value_criteria']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_repeated_sync_is_stable_and_does_not_churn_timestamp(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'mandate-stable');
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'buyer',
            'data' => [
                'city' => 'Muğla',
                'property_type' => 'daire',
                'budget_max' => 7_000_000,
                'investment_goal' => 'kira getirisi',
            ],
            'valuation' => [],
            'completeness_score' => 60,
            'confidence_score' => 70,
        ]);

        $service = app(RealEstateInvestorMandateService::class);
        $first = $service->sync($profile->fresh());
        $second = $service->sync($profile->fresh());

        $this->assertSame($first['updated_at'], $second['updated_at']);
        $this->assertSame($first['strength_score'], $second['strength_score']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_with_same_user_and_bot_fails_closed(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'mandate-foreign-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 37, 'mandate-foreign');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'city' => 'Muğla',
                'property_type' => 'arsa',
                'budget_max' => 9_000_000,
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $summary = app(RealEstateInvestorMandateService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertArrayNotHasKey(
            'investor_mandate_intelligence',
            $profile->fresh()->data
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_seller_profiles_never_receive_investor_mandate_intelligence(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation(
            $bot,
            37,
            'mandate-seller',
            ['business:real_estate_seller']
        );
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'city' => 'Muğla',
                'property_type' => 'arsa',
                'asking_price' => 5_000_000,
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $this->assertSame(
            [],
            app(RealEstateInvestorMandateService::class)->sync($profile)
        );
        $this->assertArrayNotHasKey(
            'investor_mandate_intelligence',
            $profile->fresh()->data
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'mandate-intelligence@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'mandate-intelligence',
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
        int $organizationId,
        string $sessionId,
        array $tags = ['business:real_estate_investor']
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => $organizationId,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => $tags,
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
