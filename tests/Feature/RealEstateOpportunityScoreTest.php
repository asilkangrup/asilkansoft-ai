<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakFirsatlar;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCommercialDealService;
use App\Services\RealEstateOpportunityScoreService;
use App\Services\RealEstateValuationFreshnessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOpportunityScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_investor_band_deal_gets_extra_contact_priority_when_urgent(): void
    {
        $this->seedAccount();
        $conversation = $this->conversation('opportunity-strong');
        $profile = $this->profile($conversation, [
            'property_type'=>'arsa','location'=>'Marmaris','asking_price'=>2_700_000,
            'minimum_price'=>2_400_000,'urgency'=>'high',
            'commercial_terms'=>['commission_rate_percent'=>3],
            'seller_motivation_intelligence'=>[
                'motivation_level'=>'high_explicit',
                'confidential_price_flexibility'=>['band'=>'moderate','confidential'=>true],
            ],
            'verification_intelligence'=>['status'=>'verified','risk_score'=>10],
            'fact_consistency_intelligence'=>['status'=>'consistent'],
            'investor_offer_handoff_intelligence'=>[
                'ready_for_operator_handoff'=>true,'candidate_count'=>2,
                'strongest_match_score'=>90,
            ],
        ], [
            'realistic_sale_min'=>3_300_000,'realistic_sale_max'=>3_500_000,
            'investor_buy_min'=>2_600_000,'investor_buy_max'=>2_800_000,
            'confidence_score'=>85,
        ], 95, 90);

        $summary = app(RealEstateOpportunityScoreService::class)->sync($profile);
        $commercial = app(RealEstateCommercialDealService::class)->summaryForProfile($profile);

        $this->assertSame('actionable', $summary['state']);
        $this->assertContains($summary['grade'], ['exceptional','strong']);
        $this->assertGreaterThanOrEqual(65, $summary['score']);
        $this->assertSame(20, $summary['components']['urgency_priority']);
        $this->assertSame(3, $summary['metrics']['urgency_rank']);
        $this->assertSame('Acil satış', $summary['metrics']['urgency_label']);
        $this->assertTrue($summary['metrics']['asking_within_investor_band']);
        $this->assertSame('investor_ready', $commercial['state']);
        $this->assertSame(2_600_000, $commercial['negotiation_target_min']);
        $this->assertSame(2_800_000, $commercial['negotiation_target_max']);
        $this->assertSame(81_000, $commercial['expected_commission_amount']);
        $this->assertSame(0, $commercial['gap_to_investor_band_amount']);
        $this->assertFalse($commercial['guardrails']['urgency_changes_target_price']);
        $this->assertFalse($commercial['guardrails']['private_seller_floor_included']);
        $this->assertTrue($summary['guardrails']['urgency_may_increase_contact_priority']);
        $this->assertFalse($summary['guardrails']['urgency_may_change_valuation_or_offer']);
        $this->assertFalse($summary['guardrails']['seller_distress_may_be_used_for_pressure']);
        $this->assertFalse($summary['guardrails']['private_seller_floor_included']);
        $json = json_encode($summary);
        $this->assertStringNotContainsString('2400000', $json);
        $this->assertStringNotContainsString('2400000', json_encode($commercial));
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_incomplete_document_file_remains_in_default_priority_queue_with_request_action(): void
    {
        $this->seedAccount();
        $conversation = $this->conversation('opportunity-missing-document');
        $profile = $this->profile($conversation, [
            'property_type'=>'arsa','location'=>'Marmaris','asking_price'=>2_900_000,
            'urgency'=>'medium',
            'verification_intelligence'=>['status'=>'unverified','risk_score'=>10],
            'fact_consistency_intelligence'=>['status'=>'consistent'],
            'investor_offer_handoff_intelligence'=>[
                'ready_for_operator_handoff'=>false,'candidate_count'=>0,
                'recommended_operator_action'=>'Satıcıdan tapu fotoğrafını iste.',
            ],
        ], [
            'realistic_sale_max'=>3_500_000,'investor_buy_max'=>2_800_000,'confidence_score'=>75,
        ], 60, 75);

        $summary = app(RealEstateOpportunityScoreService::class)->sync($profile);
        $page = new EmlakFirsatlar;
        $items = $page->getOpportunitiesProperty();

        $this->assertSame('preparation_required', $summary['state']);
        $this->assertSame(10, $summary['components']['urgency_priority']);
        $this->assertSame(2, $summary['metrics']['urgency_rank']);
        $this->assertSame('priority', $page->filter);
        $this->assertCount(1, $items);
        $this->assertSame($profile->id, $items->first()['id']);
        $this->assertStringContainsString('tapu fotoğrafını iste', $items->first()['action']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_verification_risk_blocks_opportunity_even_with_good_price(): void
    {
        $this->seedAccount();
        $conversation = $this->conversation('opportunity-blocked');
        $profile = $this->profile($conversation, [
            'property_type'=>'arsa','location'=>'Marmaris','asking_price'=>2_500_000,
            'verification_intelligence'=>['status'=>'high_risk','risk_score'=>85],
            'fact_consistency_intelligence'=>['status'=>'consistent'],
            'investor_offer_handoff_intelligence'=>[
                'ready_for_operator_handoff'=>true,'candidate_count'=>1,
                'strongest_match_score'=>95,
            ],
        ], [
            'realistic_sale_max'=>3_500_000,'investor_buy_max'=>2_800_000,'confidence_score'=>90,
        ], 100, 90);

        $summary = app(RealEstateOpportunityScoreService::class)->summaryForProfile($profile);

        $this->assertSame('blocked', $summary['state']);
        $this->assertSame('blocked', $summary['grade']);
        $this->assertContains('verification_risk_blocks_action', $summary['reasons']);
        $this->assertStringContainsString('yatırımcıya sunma', $summary['recommended_operator_action']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_short_sale_timeline_ranks_above_higher_scored_standard_file(): void
    {
        $this->seedAccount();

        $standard = $this->profile($this->conversation('standard-priority'), [
            'property_type' => 'arsa', 'location' => 'İstanbul', 'asking_price' => 2_000_000,
            'urgency' => 'low',
            'verification_intelligence' => ['status' => 'verified', 'risk_score' => 0],
            'fact_consistency_intelligence' => ['status' => 'consistent'],
            'investor_offer_handoff_intelligence' => [
                'ready_for_operator_handoff' => true, 'candidate_count' => 8, 'strongest_match_score' => 100,
            ],
        ], [
            'realistic_sale_max' => 3_000_000, 'investor_buy_max' => 2_200_000, 'confidence_score' => 100,
        ], 100, 100);

        $urgent = $this->profile($this->conversation('urgent-priority'), [
            'property_type' => 'tarla', 'location' => 'Kırklareli', 'asking_price' => 4_000_000,
            'timeline' => 'hemen',
            'verification_intelligence' => ['status' => 'unverified', 'risk_score' => 0],
            'fact_consistency_intelligence' => ['status' => 'consistent'],
            'investor_offer_handoff_intelligence' => [
                'ready_for_operator_handoff' => false, 'candidate_count' => 0,
            ],
        ], [
            'realistic_sale_max' => 3_000_000, 'investor_buy_max' => 2_200_000, 'confidence_score' => 40,
        ], 40, 40);

        $page = new EmlakFirsatlar;
        $items = $page->getOpportunitiesProperty();

        $this->assertSame($urgent->id, $items->first()['id']);
        $this->assertSame('Acil satış', $items->first()['urgency_label']);
        $this->assertSame('hemen', $items->first()['sale_timeline']);
        $this->assertNotSame($standard->id, $items->first()['id']);
        $this->assertNotEmpty($items->first()['summary']);
        $this->assertNotEmpty($items->first()['action']);
    }

    private function profile(ConversationControl $conversation, array $data, array $valuation, int $complete, int $confidence): RealEstateProfile
    {
        $data = array_merge([
            'city'=>'Muğla','district'=>'Marmaris','area_sqm'=>1000,
            'location_url'=>'https://maps.example.test/property',
        ], $data);
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,
            'profile_type'=>'seller','data'=>$data,'valuation'=>[],
            'completeness_score'=>$complete,'confidence_score'=>$confidence,
        ]));

        if ($valuation !== []) {
            $valuation['sources'] = ['https://example.test/1','https://example.test/2'];
            $valuation['comparables'] = [
                ['url'=>'https://example.test/1','listing_price'=>3_300_000,'area_sqm'=>1000,'location'=>'Marmaris','property_type'=>'arsa'],
                ['url'=>'https://example.test/2','listing_price'=>3_500_000,'area_sqm'=>1100,'location'=>'Marmaris','property_type'=>'arsa'],
            ];
            $profile->forceFill([
                'valuation'=>app(RealEstateValuationFreshnessService::class)->stamp($profile, $valuation),
            ])->saveQuietly();
        }

        return $profile->fresh();
    }

    private function conversation(string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,'session_id'=>$session,
            'whatsapp_number'=>'905550000007','customer_name'=>'Satıcı','tags'=>[],
            'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }

    private function seedAccount(): void
    {
        User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'opportunity@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'opportunity','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
    }
}
