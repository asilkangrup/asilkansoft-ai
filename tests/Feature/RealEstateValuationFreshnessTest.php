<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateConversationService;
use App\Services\RealEstateDecisionService;
use App\Services\RealEstateMatchService;
use App\Services\RealEstateMatchValuationFreshnessFilterService;
use App\Services\RealEstateValuationDecisionGuardService;
use App\Services\RealEstateValuationFreshnessService;
use App\Services\RealEstateValuationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateValuationFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_stamped_current_valuation_with_structured_comparables_is_match_safe(): void
    {
        [$bot, $conversation] = $this->seedIsolatedSeller('fresh-valuation');
        $profile = $this->sellerProfile($conversation);

        $service = app(RealEstateValuationFreshnessService::class);
        $profile->update([
            'valuation' => $service->stamp(
                $profile,
                $this->researchedValuation(),
                CarbonImmutable::now(),
            ),
        ]);
        $profile->refresh();

        $assessment = $service->assess($profile);

        $this->assertSame('fresh', $assessment['status']);
        $this->assertTrue($assessment['usable_for_decision']);
        $this->assertTrue($assessment['usable_for_matching']);
        $this->assertSame(2, $assessment['comparable_count']);
        $this->assertGreaterThanOrEqual(2, $assessment['source_count']);
        $this->assertSame(
            $service->fingerprint($profile->data),
            $profile->valuation['profile_fingerprint']
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_material_property_change_invalidates_previous_valuation_immediately(): void
    {
        [, $conversation] = $this->seedIsolatedSeller('changed-profile');
        $profile = $this->sellerProfile($conversation);
        $service = app(RealEstateValuationFreshnessService::class);

        $profile->update([
            'valuation' => $service->stamp($profile, $this->researchedValuation()),
        ]);

        $data = $profile->fresh()->data;
        $data['area_sqm'] = 1450;
        $profile->update(['data' => $data]);
        $profile->refresh();

        $assessment = $service->assess($profile);

        $this->assertSame('stale', $assessment['status']);
        $this->assertContains('profile_changed', $assessment['reasons']);
        $this->assertFalse($assessment['usable_for_decision']);
        $this->assertFalse($assessment['usable_for_matching']);
    }

    public function test_valuation_older_than_seven_days_is_expired(): void
    {
        [, $conversation] = $this->seedIsolatedSeller('expired-valuation');
        $profile = $this->sellerProfile($conversation);
        $service = app(RealEstateValuationFreshnessService::class);

        $profile->update([
            'valuation' => $service->stamp(
                $profile,
                $this->researchedValuation(),
                CarbonImmutable::now()->subDays(8),
            ),
        ]);
        $profile->refresh();

        $assessment = $service->assess($profile);

        $this->assertSame('stale', $assessment['status']);
        $this->assertContains('expired', $assessment['reasons']);
        $this->assertFalse($assessment['usable_for_matching']);
    }

    public function test_price_ranges_without_structured_comparables_are_not_treated_as_current_research(): void
    {
        [, $conversation] = $this->seedIsolatedSeller('missing-comparables');
        $profile = $this->sellerProfile($conversation);
        $service = app(RealEstateValuationFreshnessService::class);
        $valuation = $this->researchedValuation();
        $valuation['comparables'] = [];

        $profile->update([
            'valuation' => $service->stamp($profile, $valuation),
        ]);
        $profile->refresh();

        $assessment = $service->assess($profile);

        $this->assertSame('stale', $assessment['status']);
        $this->assertContains('missing_comparables', $assessment['reasons']);
        $this->assertFalse($assessment['usable_for_decision']);
    }

    public function test_decision_guard_blocks_stale_valuation_from_matching_and_keeps_followups_disabled(): void
    {
        [, $conversation] = $this->seedIsolatedSeller('stale-decision');
        $profile = $this->sellerProfile($conversation);

        // Legacy price data intentionally has no fingerprint, research timestamp,
        // sources or structured comparables.
        $profile->update([
            'valuation' => [
                'market_min' => 3500000,
                'market_max' => 4000000,
                'quick_sale_max' => 3600000,
                'investor_buy_max' => 3300000,
                'confidence_score' => 80,
            ],
        ]);

        $raw = app(RealEstateDecisionService::class)->process($conversation);
        $this->assertTrue($raw['ready_for_match']);

        $guarded = app(RealEstateValuationDecisionGuardService::class)
            ->process($conversation);

        $this->assertFalse($guarded['ready_for_match']);
        $this->assertTrue($guarded['valuation_research_needed']);
        $this->assertSame('refresh_valuation', $guarded['negotiation_posture']);

        $conversation->refresh();
        $this->assertNull($conversation->next_follow_up_at);
        $this->assertContains('real_estate:valuation:stale', $conversation->etiketler());
        $this->assertNotContains('real_estate:state:ready_for_match', $conversation->etiketler());
    }

    public function test_match_filter_removes_stale_seller_then_allows_fresh_researched_seller(): void
    {
        [$bot, $sellerConversation] = $this->seedIsolatedSeller('seller-match-freshness');
        $seller = $this->sellerProfile($sellerConversation);

        $investorConversation = $this->conversation(
            $bot,
            'investor-match-freshness',
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
                'district' => 'Marmaris',
                'budget_max' => 5000000,
                'financing' => 'cash',
                'investment_goal' => 'değer artışı',
                'timeline' => '30 gün',
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 80,
        ]);

        $seller->update([
            'valuation' => [
                'market_min' => 3700000,
                'market_max' => 4300000,
                'investor_buy_max' => 3900000,
                'confidence_score' => 80,
            ],
        ]);

        $raw = app(RealEstateMatchService::class)->process($investorConversation);
        $this->assertNotEmpty($raw);

        $filtered = app(RealEstateMatchValuationFreshnessFilterService::class)
            ->process($investorConversation);
        $this->assertSame([], $filtered);
        $this->assertContains('real_estate:match:none', $investorConversation->fresh()->etiketler());

        $freshness = app(RealEstateValuationFreshnessService::class);
        $seller->refresh();
        $seller->update([
            'valuation' => $freshness->stamp($seller, $this->researchedValuation()),
        ]);

        app(RealEstateMatchService::class)->process($investorConversation);
        $filtered = app(RealEstateMatchValuationFreshnessFilterService::class)
            ->process($investorConversation);

        $this->assertCount(1, $filtered);
        $this->assertSame($seller->id, $filtered[0]['seller_profile_id']);
        $this->assertSame('fresh', $filtered[0]['valuation_freshness']['status']);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_fresh_cached_valuation_is_reused_without_api_key_or_external_call(): void
    {
        [, $conversation] = $this->seedIsolatedSeller('cached-valuation');
        $profile = $this->sellerProfile($conversation);
        $freshness = app(RealEstateValuationFreshnessService::class);
        $profile->update([
            'valuation' => $freshness->stamp($profile, $this->researchedValuation()),
        ]);

        $result = app(RealEstateValuationService::class)->process(
            $conversation,
            'Bu arsa kaç para eder?'
        );

        $this->assertNotNull($result);
        $this->assertSame('fresh', $result['freshness_status']);
        $this->assertSame(3700000.0, $result['market_min']);
    }

    public function test_real_estate_router_does_not_mutate_same_user_outside_fresh_org_and_bot(): void
    {
        [$bot] = $this->seedIsolatedSeller('scope-seed');

        $foreignBot = AiBot::query()->create([
            'user_id' => 40,
            'name' => 'Foreign Real Estate Bot',
            'company_name' => 'Foreign',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
        ]);

        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $foreignBot->id,
            'session_id' => 'foreign-bot-route',
            'whatsapp_number' => '905550000099',
            'tags' => ['keep:this'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);

        $result = app(RealEstateConversationService::class)->route(
            $conversation,
            'Arsamı acil satmak istiyorum.'
        );

        $this->assertSame('real_estate_general', $result);
        $this->assertSame(['keep:this'], $conversation->fresh()->etiketler());
        $this->assertSame(35, $bot->id);
    }

    private function seedIsolatedSeller(string $session): array
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

        $conversation = $this->conversation(
            $bot,
            $session,
            ['business:real_estate_seller']
        );

        return [$bot, $conversation];
    }

    private function conversation(
        AiBot $bot,
        string $session,
        array $tags
    ): ConversationControl {
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
                'location_url' => 'https://maps.example/property',
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 82,
        ]);
    }

    private function researchedValuation(): array
    {
        return [
            'market_min' => 3700000,
            'market_max' => 4300000,
            'quick_sale_min' => 3400000,
            'quick_sale_max' => 3800000,
            'investor_buy_min' => 3200000,
            'investor_buy_max' => 3900000,
            'confidence_score' => 82,
            'summary' => 'Güncel ilan emsalleri üzerinden temkinli aralık.',
            'next_best_action' => 'Tapu/imar doğrulamasından sonra yatırımcı hedef aralığını teyit et.',
            'missing_data' => [],
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
