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

class RealEstateOpportunityMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_is_matched_to_compatible_investor_without_exposing_contact_data(): void
    {
        $bot = $this->seedRealEstateBot();

        $sellerConversation = $this->conversation(
            $bot,
            'seller-match-session',
            '905551111111',
            ['business:real_estate_seller']
        );
        $investorConversation = $this->conversation(
            $bot,
            'investor-match-session',
            '905552222222',
            ['business:real_estate_investor']
        );

        $seller = RealEstateProfile::query()->create([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
                'asking_price' => 4200000,
                'urgency' => 'medium',
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
            ],
            'valuation' => [
                'market_min' => 3700000,
                'market_max' => 4300000,
                'quick_sale_min' => 3400000,
                'quick_sale_max' => 3800000,
                'investor_buy_min' => 3200000,
                'investor_buy_max' => 3600000,
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
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Mugla',
                'district' => 'Marmaris',
                'budget_max' => 4000000,
                'financing' => 'cash',
                'investment_goal' => 'değer artışı',
                'timeline' => '30 gün',
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 80,
        ]);

        $matches = app(RealEstateMatchService::class)->process($sellerConversation);

        $this->assertCount(1, $matches);
        $this->assertSame($investor->id, $matches[0]['candidate_profile_id']);
        $this->assertSame($investorConversation->id, $matches[0]['candidate_conversation_id']);
        $this->assertSame('investor', $matches[0]['candidate_role']);
        $this->assertGreaterThanOrEqual(85, $matches[0]['match_score']);
        $this->assertSame('strong', $matches[0]['grade']);
        $this->assertArrayNotHasKey('whatsapp_number', $matches[0]);
        $this->assertArrayNotHasKey('customer_name', $matches[0]);

        $seller->refresh();
        $sellerConversation->refresh();

        $this->assertSame(
            $investor->id,
            $seller->data['opportunity_matches'][0]['candidate_profile_id']
        );
        $this->assertContains(
            'real_estate:match:strong',
            $sellerConversation->etiketler()
        );
        $this->assertNull($sellerConversation->next_follow_up_at);
    }

    public function test_investor_perspective_points_to_seller_as_candidate(): void
    {
        $bot = $this->seedRealEstateBot();

        $sellerConversation = $this->conversation(
            $bot,
            'seller-reverse-session',
            '905557777777',
            ['business:real_estate_seller']
        );
        $investorConversation = $this->conversation(
            $bot,
            'investor-reverse-session',
            '905558888888',
            ['business:real_estate_investor']
        );

        $seller = RealEstateProfile::query()->create([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'tarla',
                'city' => 'Aydın',
                'district' => 'Kuşadası',
                'asking_price' => 5000000,
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'tarla',
            ],
            'valuation' => [
                'investor_buy_max' => 4400000,
                'quick_sale_max' => 4700000,
                'confidence_score' => 78,
            ],
            'completeness_score' => 90,
            'confidence_score' => 78,
        ]);

        $investor = RealEstateProfile::query()->create([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'tarla',
                'city' => 'Aydin',
                'district' => 'Kusadasi',
                'budget_max' => 5000000,
                'financing' => 'cash',
            ],
            'valuation' => [],
            'completeness_score' => 85,
            'confidence_score' => 75,
        ]);

        $matches = app(RealEstateMatchService::class)->process($investorConversation);

        $this->assertCount(1, $matches);
        $this->assertSame($seller->id, $matches[0]['candidate_profile_id']);
        $this->assertSame($sellerConversation->id, $matches[0]['candidate_conversation_id']);
        $this->assertSame('seller', $matches[0]['candidate_role']);
        $this->assertSame($investor->id, $matches[0]['investor_profile_id']);
        $this->assertSame($seller->id, $matches[0]['seller_profile_id']);
        $this->assertGreaterThanOrEqual(85, $matches[0]['match_score']);
        $this->assertNull($investorConversation->next_follow_up_at);
    }

    public function test_investor_does_not_receive_wrong_city_or_property_type_matches(): void
    {
        $bot = $this->seedRealEstateBot();

        $investorConversation = $this->conversation(
            $bot,
            'investor-filter-session',
            '905553333333',
            ['business:real_estate_investor']
        );

        RealEstateProfile::query()->create([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'budget_max' => 8000000,
                'financing' => 'cash',
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $wrongCityConversation = $this->conversation(
            $bot,
            'wrong-city-session',
            '905554444444',
            ['business:real_estate_seller']
        );
        RealEstateProfile::query()->create([
            'conversation_control_id' => $wrongCityConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'İstanbul',
                'district' => 'Silivri',
                'asking_price' => 3000000,
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $wrongTypeConversation = $this->conversation(
            $bot,
            'wrong-type-session',
            '905555555555',
            ['business:real_estate_seller']
        );
        RealEstateProfile::query()->create([
            'conversation_control_id' => $wrongTypeConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'daire',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'asking_price' => 7000000,
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $matches = app(RealEstateMatchService::class)->process($investorConversation);

        $this->assertSame([], $matches);

        $investorConversation->refresh();
        $this->assertContains(
            'real_estate:match:none',
            $investorConversation->etiketler()
        );
        $this->assertNull($investorConversation->next_follow_up_at);
    }

    public function test_matching_service_is_hard_scoped_to_fresh_real_estate_account(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other WAI User',
            'email' => 'matching-other@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $otherBot = AiBot::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Bot',
            'company_name' => 'Other Company',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
        ]);
        $conversation = ConversationControl::query()->create([
            'user_id' => $otherUser->id,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'other-matching-session',
            'whatsapp_number' => '905556666666',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
        ]);

        $this->assertSame(
            [],
            app(RealEstateMatchService::class)->process($conversation)
        );

        $conversation->refresh();
        $this->assertSame(
            ['business:real_estate_seller'],
            $conversation->etiketler()
        );
    }

    private function seedRealEstateBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'emlak-match@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-opportunity-matching',
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
        array $tags
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => $whatsappNumber,
            'tags' => $tags,
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
    }
}
