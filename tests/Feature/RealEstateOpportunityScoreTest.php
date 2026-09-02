<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOpportunityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOpportunityScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_investor_band_deal_becomes_high_priority_without_using_urgency(): void
    {
        $this->seedAccount();
        $conversation = $this->conversation('opportunity-strong');
        $profile = $this->profile($conversation, [
            'property_type'=>'arsa','location'=>'Marmaris','asking_price'=>2_700_000,
            'minimum_price'=>2_400_000,'urgency'=>'high',
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

        $this->assertSame('actionable', $summary['state']);
        $this->assertContains($summary['grade'], ['exceptional','strong']);
        $this->assertGreaterThanOrEqual(65, $summary['score']);
        $this->assertSame(0, $summary['components']['urgency_score_contribution']);
        $this->assertTrue($summary['metrics']['asking_within_investor_band']);
        $this->assertFalse($summary['guardrails']['urgency_increases_score']);
        $this->assertFalse($summary['guardrails']['seller_distress_may_be_used_for_pressure']);
        $this->assertFalse($summary['guardrails']['private_seller_floor_included']);
        $json = json_encode($summary);
        $this->assertStringNotContainsString('2400000', $json);
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

    private function profile(ConversationControl $conversation, array $data, array $valuation, int $complete, int $confidence): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,
            'profile_type'=>'seller','data'=>$data,'valuation'=>$valuation,
            'completeness_score'=>$complete,'confidence_score'=>$confidence,
        ]));
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
