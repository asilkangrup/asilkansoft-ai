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

class RealEstateMultiValueCriteriaMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_comma_separated_investor_cities_types_and_districts_match_as_explicit_or_criteria(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'multi-seller', '905551200001');
        $investorConversation = $this->conversation($bot, 'multi-investor', '905551200002');

        $seller = $this->profile($sellerConversation, 'seller', [
            'city' => 'Bilecik',
            'district' => 'Bozüyük',
            'property_type' => 'arsa',
            'area_sqm' => 1500,
            'asking_price' => 3_000_000,
            'title_deed_type' => 'müstakil',
            'zoning_status' => 'konut',
        ], [
            'investor_buy_max' => 2_700_000,
            'quick_sale_max' => 2_850_000,
        ]);

        $investor = $this->profile($investorConversation, 'investor', [
            'city' => 'Kocaeli, Sakarya, Bilecik, Kütahya',
            'district' => 'Merkez, Bozüyük',
            'property_type' => 'daire, arsa, dükkan',
            'budget_max' => 3_500_000,
            'financing' => 'cash',
            'investment_goal' => 'değer artışı',
            'location_flexibility' => 'strict_district',
        ]);

        $matches = app(RealEstateMatchService::class)->process($sellerConversation);

        $this->assertCount(1, $matches);
        $this->assertSame($investor->id, $matches[0]['candidate_profile_id']);
        $this->assertSame('strong', $matches[0]['grade']);
        $this->assertGreaterThanOrEqual(85, $matches[0]['match_score']);
        $this->assertTrue($matches[0]['criteria_checks']['multi_value_criteria_aware']);
        $this->assertTrue($matches[0]['criteria_checks']['city_match']);
        $this->assertTrue($matches[0]['criteria_checks']['property_type_match']);
        $this->assertTrue($matches[0]['criteria_checks']['district_match']);
        $this->assertArrayNotHasKey('whatsapp_number', $matches[0]);
        $this->assertArrayNotHasKey('customer_name', $matches[0]);

        $seller->refresh();
        $this->assertTrue($seller->data['opportunity_match_summary']['multi_value_criteria_aware']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
    }

    public function test_strict_district_still_rejects_when_none_of_multiple_districts_overlap(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'strict-seller', '905551200003');
        $investorConversation = $this->conversation($bot, 'strict-investor', '905551200004');

        $this->profile($sellerConversation, 'seller', [
            'city' => 'Bilecik',
            'district' => 'Bozüyük',
            'property_type' => 'arsa',
            'asking_price' => 2_500_000,
        ], [
            'investor_buy_max' => 2_200_000,
        ]);

        $this->profile($investorConversation, 'investor', [
            'city' => 'Bilecik, Kütahya',
            'district' => 'Merkez, Söğüt',
            'property_type' => 'arsa, tarla',
            'budget_max' => 3_000_000,
            'financing' => 'cash',
            'location_flexibility' => 'strict_district',
        ]);

        $matches = app(RealEstateMatchService::class)->process($sellerConversation);

        $this->assertSame([], $matches);
        $this->assertContains('real_estate:match:none', $sellerConversation->fresh()->etiketler());
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
    }

    public function test_array_and_conjunction_criteria_are_supported_without_weakening_hard_filters(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'array-seller', '905551200005');
        $investorConversation = $this->conversation($bot, 'array-investor', '905551200006');

        $seller = $this->profile($sellerConversation, 'seller', [
            'city' => 'Aydın',
            'district' => 'Kuşadası',
            'property_type' => 'tarla',
            'asking_price' => 4_000_000,
            'title_deed_type' => 'müstakil',
            'zoning_status' => 'tarla',
        ], [
            'investor_buy_max' => 3_500_000,
        ]);

        $this->profile($investorConversation, 'investor', [
            'city' => ['Muğla', 'Aydın'],
            'district' => 'Didim veya Kuşadası',
            'property_type' => 'arsa ve tarla',
            'budget_max' => 4_500_000,
            'financing' => 'cash',
            'location_flexibility' => 'strict_district',
        ]);

        $matches = app(RealEstateMatchService::class)->process($sellerConversation);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]['criteria_checks']['city_match']);
        $this->assertTrue($matches[0]['criteria_checks']['property_type_match']);
        $this->assertTrue($matches[0]['criteria_checks']['district_match']);
        $this->assertNull($seller->conversation->fresh()->next_follow_up_at);
    }

    private function profile(
        ConversationControl $conversation,
        string $type,
        array $data,
        array $valuation = [],
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => $valuation,
            'completeness_score' => 90,
            'confidence_score' => 80,
        ]));
    }

    private function conversation(
        AiBot $bot,
        string $session,
        string $phone,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
            'whatsapp_number' => $phone,
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedIsolatedAccount(): AiBot
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'multi-match@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'multi-match',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $owner->organizations()->syncWithoutDetaching([
            37 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
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
}
