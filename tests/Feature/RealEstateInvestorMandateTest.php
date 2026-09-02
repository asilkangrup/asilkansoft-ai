<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorMandateTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_area_range_is_a_hard_match_filter(): void
    {
        [$sellerConversation, $investorConversation] = $this->seedPair(
            sellerData: $this->sellerData(['area_sqm' => 650]),
            investorData: $this->investorData([
                'area_min_sqm' => 900,
                'area_max_sqm' => 1500,
            ]),
        );

        $this->assertSame(
            [],
            app(RealEstateMatchService::class)->process($investorConversation)
        );
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_strict_district_mandate_rejects_other_district_in_same_city(): void
    {
        [, $investorConversation] = $this->seedPair(
            sellerData: $this->sellerData(['district' => 'Fethiye']),
            investorData: $this->investorData([
                'district' => 'Marmaris',
                'location_flexibility' => 'strict_district',
            ]),
        );

        $this->assertSame(
            [],
            app(RealEstateMatchService::class)->process($investorConversation)
        );
    }

    public function test_same_city_flexibility_allows_other_district_with_visible_risk(): void
    {
        [$sellerConversation, $investorConversation, $seller] = $this->seedPair(
            sellerData: $this->sellerData(['district' => 'Fethiye']),
            investorData: $this->investorData([
                'district' => 'Marmaris',
                'location_flexibility' => 'same_city',
            ]),
        );

        $matches = app(RealEstateMatchService::class)->process($investorConversation);

        $this->assertCount(1, $matches);
        $this->assertSame($seller->id, $matches[0]['candidate_profile_id']);
        $this->assertSame('same_city', $matches[0]['criteria_checks']['location_flexibility']);
        $this->assertFalse($matches[0]['criteria_checks']['district_match']);
        $this->assertTrue($matches[0]['criteria_checks']['hard_filters_passed']);
        $this->assertContains(
            'İlçe farklı; yatırımcı aynı il içindeki alternatifleri kabul ediyor.',
            $matches[0]['risks']
        );
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_shared_title_is_rejected_when_investor_explicitly_refuses_it(): void
    {
        [, $investorConversation] = $this->seedPair(
            sellerData: $this->sellerData(['is_shared_title' => true]),
            investorData: $this->investorData(['accepts_shared_title' => false]),
        );

        $this->assertSame(
            [],
            app(RealEstateMatchService::class)->process($investorConversation)
        );
    }

    public function test_severely_insufficient_budget_is_rejected_before_scoring(): void
    {
        [, $investorConversation] = $this->seedPair(
            sellerData: $this->sellerData(),
            investorData: $this->investorData(['budget_max' => 2_500_000]),
            valuation: [
                'market_min' => 4_400_000,
                'market_max' => 5_000_000,
                'quick_sale_min' => 4_000_000,
                'quick_sale_max' => 4_400_000,
                'investor_buy_min' => 3_800_000,
                'investor_buy_max' => 4_200_000,
                'confidence_score' => 82,
            ],
        );

        $this->assertSame(
            [],
            app(RealEstateMatchService::class)->process($investorConversation)
        );
    }

    public function test_area_title_budget_and_discount_criteria_are_explained_without_contact_data(): void
    {
        [$sellerConversation, $investorConversation, $seller] = $this->seedPair(
            sellerData: $this->sellerData([
                'area_sqm' => 1200,
                'is_shared_title' => true,
                'asking_price' => 5_000_000,
            ]),
            investorData: $this->investorData([
                'area_min_sqm' => 1000,
                'area_max_sqm' => 1500,
                'accepts_shared_title' => true,
                'location_flexibility' => 'strict_district',
                'target_discount_percent' => 15,
                'budget_max' => 4_500_000,
            ]),
            valuation: [
                'market_min' => 4_500_000,
                'market_max' => 5_100_000,
                'quick_sale_min' => 4_000_000,
                'quick_sale_max' => 4_400_000,
                'investor_buy_min' => 3_800_000,
                'investor_buy_max' => 4_200_000,
                'confidence_score' => 84,
            ],
        );

        $matches = app(RealEstateMatchService::class)->process($investorConversation);

        $this->assertCount(1, $matches);
        $this->assertSame($seller->id, $matches[0]['candidate_profile_id']);
        $this->assertGreaterThanOrEqual(85, $matches[0]['match_score']);
        $this->assertSame('strong', $matches[0]['grade']);
        $this->assertSame(1200.0, $matches[0]['criteria_checks']['seller_area_sqm']);
        $this->assertSame(1000.0, $matches[0]['criteria_checks']['area_min_sqm']);
        $this->assertSame(1500.0, $matches[0]['criteria_checks']['area_max_sqm']);
        $this->assertTrue($matches[0]['criteria_checks']['area_match']);
        $this->assertTrue($matches[0]['criteria_checks']['seller_shared_title']);
        $this->assertTrue($matches[0]['criteria_checks']['accepts_shared_title']);
        $this->assertSame(15.0, $matches[0]['criteria_checks']['target_discount_percent']);
        $this->assertSame(16.0, $matches[0]['criteria_checks']['observed_discount_percent']);
        $this->assertGreaterThanOrEqual(100.0, $matches[0]['criteria_checks']['budget_coverage_percent']);
        $this->assertArrayNotHasKey('whatsapp_number', $matches[0]);
        $this->assertArrayNotHasKey('customer_name', $matches[0]);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    private function seedPair(
        array $sellerData,
        array $investorData,
        ?array $valuation = null,
    ): array {
        $bot = $this->seedRealEstateBot();

        $sellerConversation = $this->conversation(
            $bot,
            'seller-'.bin2hex(random_bytes(4)),
            '905551111111',
            ['business:real_estate_seller'],
        );
        $investorConversation = $this->conversation(
            $bot,
            'investor-'.bin2hex(random_bytes(4)),
            '905552222222',
            ['business:real_estate_investor'],
        );

        $seller = RealEstateProfile::query()->create([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => $sellerData,
            'valuation' => $valuation ?? [
                'market_min' => 3_800_000,
                'market_max' => 4_500_000,
                'quick_sale_min' => 3_500_000,
                'quick_sale_max' => 3_900_000,
                'investor_buy_min' => 3_200_000,
                'investor_buy_max' => 3_600_000,
                'confidence_score' => 82,
            ],
            'completeness_score' => 100,
            'confidence_score' => 82,
        ]);

        $investor = RealEstateProfile::query()->create([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => $investorData,
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 82,
        ]);

        return [$sellerConversation, $investorConversation, $seller, $investor];
    }

    private function sellerData(array $overrides = []): array
    {
        return [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 4_200_000,
            'urgency' => 'medium',
            'title_deed_type' => 'müstakil',
            'zoning_status' => 'konut',
            'is_shared_title' => false,
            ...$overrides,
        ];
    }

    private function investorData(array $overrides = []): array
    {
        return [
            'property_type' => 'arsa',
            'city' => 'Mugla',
            'district' => 'Marmaris',
            'budget_max' => 4_000_000,
            'financing' => 'cash',
            'investment_goal' => 'değer artışı',
            'timeline' => '30 gün',
            ...$overrides,
        ];
    }

    private function seedRealEstateBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'mandate-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'mandate-'.bin2hex(random_bytes(4)),
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
        string $whatsappNumber,
        array $tags,
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => $whatsappNumber,
            'tags' => $tags,
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
