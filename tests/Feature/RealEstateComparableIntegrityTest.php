<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateComparableIntegrityService;
use App\Services\RealEstateMatchValuationFreshnessFilterService;
use App\Services\RealEstateValuationFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateComparableIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_relevant_comparables_are_safe_for_decision_and_matching(): void
    {
        [, $sellerConversation] = $this->seedIsolated('safe');
        $seller = $this->sellerProfile($sellerConversation);
        $seller->update(['valuation' => $this->stamped($seller, $this->valuation())]);

        $assessment = app(RealEstateComparableIntegrityService::class)
            ->assess($seller->fresh());

        $this->assertSame('safe', $assessment['status']);
        $this->assertTrue($assessment['sufficient_for_decision']);
        $this->assertTrue($assessment['sufficient_for_matching']);
        $this->assertSame(2, $assessment['usable_comparable_count']);
        $this->assertSame('asking', $assessment['price_basis']);
        $this->assertFalse($assessment['official_sale_price_verified']);
    }

    public function test_location_mismatch_prevents_match_safe_comparable_set(): void
    {
        [, $sellerConversation] = $this->seedIsolated('location-mismatch');
        $seller = $this->sellerProfile($sellerConversation);
        $valuation = $this->valuation();
        $valuation['comparables'][1]['location'] = 'Antalya Alanya';
        $seller->update(['valuation' => $this->stamped($seller, $valuation)]);

        $assessment = app(RealEstateComparableIntegrityService::class)
            ->assess($seller->fresh());

        $this->assertTrue($assessment['sufficient_for_decision']);
        $this->assertFalse($assessment['sufficient_for_matching']);
        $this->assertContains('location_mismatch', $assessment['reason_codes']);
        $this->assertSame(1, $assessment['usable_comparable_count']);
    }

    public function test_extreme_unit_price_spread_blocks_matching_without_inventing_sale_prices(): void
    {
        [, $sellerConversation] = $this->seedIsolated('spread');
        $seller = $this->sellerProfile($sellerConversation);
        $valuation = $this->valuation();
        $valuation['comparables'][0]['listing_price'] = 3600000;
        $valuation['comparables'][0]['area_sqm'] = 1200;
        $valuation['comparables'][1]['listing_price'] = 12000000;
        $valuation['comparables'][1]['area_sqm'] = 1000;
        $valuation['market_min'] = 3800000;
        $valuation['market_max'] = 4500000;
        $seller->update(['valuation' => $this->stamped($seller, $valuation)]);

        $assessment = app(RealEstateComparableIntegrityService::class)
            ->assess($seller->fresh());

        $this->assertFalse($assessment['sufficient_for_matching']);
        $this->assertContains('wide_unit_price_spread', $assessment['reason_codes']);
        $this->assertGreaterThan(2.75, $assessment['unit_price_spread_ratio']);
        $this->assertFalse($assessment['official_sale_price_verified']);
    }

    public function test_inconsistent_price_ranges_block_decision_use(): void
    {
        [, $sellerConversation] = $this->seedIsolated('bad-ranges');
        $seller = $this->sellerProfile($sellerConversation);
        $valuation = $this->valuation();
        $valuation['market_max'] = 4300000;
        $valuation['quick_sale_max'] = 5100000;
        $seller->update(['valuation' => $this->stamped($seller, $valuation)]);

        $assessment = app(RealEstateComparableIntegrityService::class)
            ->assess($seller->fresh());

        $this->assertSame('blocked', $assessment['status']);
        $this->assertFalse($assessment['sufficient_for_decision']);
        $this->assertFalse($assessment['sufficient_for_matching']);
        $this->assertContains('inconsistent_price_ranges', $assessment['reason_codes']);
    }

    public function test_match_filter_removes_seller_when_comparable_integrity_is_not_match_safe(): void
    {
        [$bot, $sellerConversation] = $this->seedIsolated('filter');
        $seller = $this->sellerProfile($sellerConversation);
        $valuation = $this->valuation();
        $valuation['comparables'][1]['property_type'] = 'daire';
        $seller->update(['valuation' => $this->stamped($seller, $valuation)]);

        $investorConversation = $this->conversation($bot, 'investor-filter', ['business:real_estate_investor']);
        $investor = RealEstateProfile::query()->create([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'budget_max' => 5000000,
                'opportunity_matches' => [[
                    'seller_profile_id' => $seller->id,
                    'match_score' => 90,
                    'grade' => 'strong',
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 80,
        ]);

        $filtered = app(RealEstateMatchValuationFreshnessFilterService::class)
            ->process($investorConversation);

        $this->assertSame([], $filtered);
        $this->assertSame([], $investor->fresh()->data['opportunity_matches']);
        $this->assertTrue($investor->fresh()->data['opportunity_match_summary']['comparable_integrity_enforced']);
        $this->assertContains('real_estate:match:none', $investorConversation->fresh()->etiketler());
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_same_user_profile_outside_isolated_organization_is_rejected(): void
    {
        [$bot] = $this->seedIsolated('out-of-scope');
        $foreignOrganization = Organization::query()->create([
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'foreign-org-integrity',
            'status' => 'active',
        ]);
        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => $foreignOrganization->id,
            'ai_bot_id' => $bot->id,
            'session_id' => 'foreign-integrity',
            'whatsapp_number' => '905550000099',
            'tags' => [],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
        $profile = $this->sellerProfile($conversation);
        $profile->update(['valuation' => $this->valuation()]);

        $assessment = app(RealEstateComparableIntegrityService::class)->assess($profile);

        $this->assertSame('out_of_scope', $assessment['status']);
        $this->assertFalse($assessment['sufficient_for_decision']);
        $this->assertFalse($assessment['sufficient_for_matching']);
    }

    private function seedIsolated(string $session): array
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => $session.'@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-'.$session,
            'status' => 'active',
        ]);

        $bot = AiBot::query()->forceCreate([
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

        return [$bot, $this->conversation($bot, $session, ['business:real_estate_seller'])];
    }

    private function conversation(AiBot $bot, string $session, array $tags): ConversationControl
    {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
            'whatsapp_number' => '905551112233',
            'tags' => $tags,
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
    }

    private function sellerProfile(ConversationControl $conversation): RealEstateProfile
    {
        return RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
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
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 82,
        ]);
    }

    private function stamped(RealEstateProfile $profile, array $valuation): array
    {
        return app(RealEstateValuationFreshnessService::class)->stamp(
            $profile,
            $valuation,
            CarbonImmutable::now()
        );
    }

    private function valuation(): array
    {
        return [
            'market_min' => 3700000,
            'market_max' => 4300000,
            'quick_sale_min' => 3400000,
            'quick_sale_max' => 3800000,
            'investor_buy_min' => 3200000,
            'investor_buy_max' => 3900000,
            'confidence_score' => 82,
            'sources' => [
                'https://example.test/listing/1',
                'https://example.test/listing/2',
            ],
            'comparables' => [
                [
                    'source' => 'Emsal 1',
                    'url' => 'https://example.test/listing/1',
                    'listing_price' => 4100000,
                    'area_sqm' => 1150,
                    'location' => 'Muğla Marmaris',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
                [
                    'source' => 'Emsal 2',
                    'url' => 'https://example.test/listing/2',
                    'listing_price' => 4300000,
                    'area_sqm' => 1250,
                    'location' => 'Muğla Marmaris',
                    'property_type' => 'arsa',
                    'observed_at' => now()->toDateString(),
                ],
            ],
        ];
    }
}
