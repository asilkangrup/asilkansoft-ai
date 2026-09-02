<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateSellerMotivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateSellerMotivationIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_seller_intelligence_is_persisted_without_raw_reason_or_private_floor(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'seller-intelligence-complete');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
                'asking_price' => 5_000_000,
                'minimum_price' => 4_400_000,
                'urgency' => 'high',
                'urgency_reason' => 'Borç kapatacağım, 05550000000 numarasından ulaşın.',
                'timeline' => '30 gün içinde',
                'block_no' => '123',
                'parcel_no' => '45',
                'notes' => 'Özel mailim seller@example.com',
            ],
            'valuation' => [
                'market_min' => 4_800_000,
                'market_max' => 5_400_000,
                'confidence_score' => 78,
            ],
            'completeness_score' => 100,
            'confidence_score' => 90,
        ]);

        $profile->refresh();
        $summary = $profile->data['seller_motivation_intelligence'];

        $this->assertSame(100, $summary['readiness_score']);
        $this->assertSame('strong', $summary['status']);
        $this->assertTrue($summary['core_ready']);
        $this->assertSame('high_explicit', $summary['motivation_level']);
        $this->assertSame('within_estimated_market_range', $summary['pricing_alignment']);
        $this->assertTrue($summary['confidential_price_flexibility']['minimum_price_present']);
        $this->assertSame('moderate', $summary['confidential_price_flexibility']['band']);
        $this->assertTrue($summary['confidential_price_flexibility']['confidential']);
        $this->assertTrue($summary['seller_protection']['required']);
        $this->assertContains('high_explicit_urgency', $summary['seller_protection']['reasons']);
        $this->assertNull($summary['recommended_next_question']);
        $this->assertSame('compare_speed_vs_price_tradeoff', $summary['recommended_negotiation_posture']);
        $this->assertTrue($summary['guardrails']['urgency_must_not_be_used_for_pressure']);
        $this->assertTrue($summary['guardrails']['seller_private_floor_is_confidential']);
        $this->assertFalse($summary['guardrails']['follow_up_scheduling_allowed']);

        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('Borç kapatacağım', (string) $json);
        $this->assertStringNotContainsString('05550000000', (string) $json);
        $this->assertStringNotContainsString('seller@example.com', (string) $json);
        $this->assertStringNotContainsString('4400000', (string) $json);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_partial_seller_intelligence_recommends_one_high_value_question_without_inventing_floor(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'seller-intelligence-partial');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'tarla',
                'city' => 'Aydın',
                'district' => 'Kuşadası',
                'asking_price' => 3_500_000,
            ],
            'valuation' => [],
            'completeness_score' => 55,
            'confidence_score' => 60,
        ]);

        $summary = app(RealEstateSellerMotivationService::class)
            ->summaryForProfile($profile->fresh());

        $this->assertSame(36, $summary['readiness_score']);
        $this->assertSame('building', $summary['status']);
        $this->assertTrue($summary['core_ready']);
        $this->assertSame('unknown', $summary['motivation_level']);
        $this->assertSame('unknown', $summary['pricing_alignment']);
        $this->assertFalse($summary['confidential_price_flexibility']['minimum_price_present']);
        $this->assertNull($summary['confidential_price_flexibility']['band']);
        $this->assertSame(
            'Net veya yaklaşık m² bilgisini sor.',
            $summary['recommended_next_question']
        );
        $this->assertContains('urgency', $summary['missing_high_value_criteria']);
        $this->assertContains('timeline', $summary['missing_high_value_criteria']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_high_urgency_without_valuation_uses_protective_posture(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'seller-intelligence-protect');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'daire',
                'city' => 'İstanbul',
                'district' => 'Beylikdüzü',
                'area_sqm' => 110,
                'asking_price' => 4_900_000,
                'urgency' => 'high',
                'timeline' => 'bu hafta',
            ],
            'valuation' => [],
            'completeness_score' => 85,
            'confidence_score' => 80,
        ]);

        $summary = app(RealEstateSellerMotivationService::class)
            ->summaryForProfile($profile->fresh());

        $this->assertSame('protect_urgent_seller_before_price_anchor', $summary['recommended_negotiation_posture']);
        $this->assertTrue($summary['seller_protection']['required']);
        $this->assertSame('high_explicit', $summary['motivation_level']);
        $this->assertSame('unknown', $summary['pricing_alignment']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_repeated_sync_is_stable_and_does_not_churn_timestamp(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'seller-intelligence-stable');
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'asking_price' => 8_000_000,
                'urgency' => 'medium',
            ],
            'valuation' => [],
            'completeness_score' => 65,
            'confidence_score' => 70,
        ]);

        $service = app(RealEstateSellerMotivationService::class);
        $first = $service->sync($profile->fresh());
        $second = $service->sync($profile->fresh());

        $this->assertSame($first['updated_at'], $second['updated_at']);
        $this->assertSame($first['readiness_score'], $second['readiness_score']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_with_same_user_and_bot_fails_closed(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'seller-intelligence-foreign-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 37, 'seller-intelligence-foreign');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 5_000_000,
                'urgency' => 'high',
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $summary = app(RealEstateSellerMotivationService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertArrayNotHasKey(
            'seller_motivation_intelligence',
            $profile->fresh()->data
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_investor_profiles_never_receive_seller_motivation_intelligence(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation(
            $bot,
            37,
            'seller-intelligence-investor',
            ['business:real_estate_investor']
        );
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'city' => 'Muğla',
                'property_type' => 'arsa',
                'budget_max' => 5_000_000,
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $this->assertSame(
            [],
            app(RealEstateSellerMotivationService::class)->sync($profile)
        );
        $this->assertArrayNotHasKey(
            'seller_motivation_intelligence',
            $profile->fresh()->data
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'seller-intelligence@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'seller-intelligence',
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
        array $tags = ['business:real_estate_seller']
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
