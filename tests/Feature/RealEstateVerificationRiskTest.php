<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMatchVerificationFilterService;
use App\Services\RealEstateVerificationDecisionGuardService;
use App\Services\RealEstateVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateVerificationRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_document_evidence_is_corroborated_without_claiming_legal_verification(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'verification-ok');

        $profile = $this->sellerProfile($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'neighborhood' => 'Hisarönü',
            'area_sqm' => 1200,
            'block_no' => '101',
            'parcel_no' => '22',
            'title_deed_type' => 'müstakil',
            'zoning_status' => 'konut',
            'asking_price' => 4200000,
            'media_findings' => [[
                'document_type' => 'tapu',
                'property_type' => 'arsa',
                'city' => 'Mugla',
                'district' => 'Marmaris',
                'neighborhood' => 'Hisaronu',
                'area_sqm' => 1201,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
                'visible_asking_price' => 4200000,
                'confidence_score' => 90,
            ]],
        ]);

        $result = app(RealEstateVerificationService::class)->process($conversation);

        $this->assertNotNull($result);
        $this->assertSame('corroborated', $result['status']);
        $this->assertTrue($result['safe_to_match']);
        $this->assertFalse($result['legal_verification_complete']);
        $this->assertSame([], $result['conflicts']);
        $this->assertContains('district', $result['corroborated_fields']);
        $this->assertContains('parcel_no', $result['corroborated_fields']);

        $profile->refresh();
        $conversation->refresh();

        $this->assertSame(
            'corroborated',
            $profile->data['verification_intelligence']['status']
        );
        $this->assertContains(
            'real_estate:verification:corroborated',
            $conversation->etiketler()
        );
        $this->assertContains(
            'real_estate:verification:safe_to_match',
            $conversation->etiketler()
        );
        $this->assertNull($conversation->next_follow_up_at);
    }

    public function test_critical_location_conflict_blocks_matching_and_surfaces_verification_action(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'verification-conflict');

        $profile = $this->sellerProfile($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1000,
            'block_no' => '10',
            'parcel_no' => '20',
            'title_deed_type' => 'müstakil',
            'zoning_status' => 'konut',
            'asking_price' => 4000000,
            'media_findings' => [[
                'document_type' => 'tapu',
                'city' => 'Muğla',
                'district' => 'Bodrum',
                'area_sqm' => 1000,
                'block_no' => '10',
                'parcel_no' => '20',
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
                'confidence_score' => 92,
            ]],
            'decision_intelligence' => [
                'lead_score' => 90,
                'lead_temperature' => 'hot',
                'ready_for_match' => true,
                'next_best_action' => 'Yatırımcıyla eşleştir.',
            ],
        ]);

        $verification = app(RealEstateVerificationService::class)->process($conversation);
        $decision = app(RealEstateVerificationDecisionGuardService::class)->process($conversation);

        $this->assertSame('blocked', $verification['status']);
        $this->assertFalse($verification['safe_to_match']);
        $this->assertGreaterThanOrEqual(65, $verification['risk_score']);
        $this->assertSame('district', $verification['conflicts'][0]['field']);
        $this->assertSame('critical', $verification['conflicts'][0]['severity']);
        $this->assertFalse($decision['ready_for_match']);
        $this->assertSame('blocked', $decision['verification_status']);

        $profile->refresh();
        $conversation->refresh();

        $this->assertFalse(
            $profile->data['decision_intelligence']['ready_for_match']
        );
        $this->assertStringContainsString(
            'çelişkisini netleştir',
            $conversation->next_best_action
        );
        $this->assertContains(
            'real_estate:verification:blocked',
            $conversation->etiketler()
        );
        $this->assertNull($conversation->next_follow_up_at);
    }

    public function test_unsafe_seller_is_removed_from_investor_opportunity_matches(): void
    {
        $bot = $this->seedRealEstateBot();
        $sellerConversation = $this->conversation($bot, 'unsafe-seller');
        $investorConversation = $this->conversation(
            $bot,
            'safe-investor',
            ['business:real_estate_investor']
        );

        $seller = $this->sellerProfile($sellerConversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'asking_price' => 4000000,
            'verification_intelligence' => [
                'status' => 'blocked',
                'risk_score' => 90,
                'safe_to_match' => false,
            ],
        ]);

        $investor = RealEstateProfile::query()->create([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'budget_max' => 5000000,
                'opportunity_matches' => [[
                    'candidate_profile_id' => $seller->id,
                    'candidate_conversation_id' => $sellerConversation->id,
                    'candidate_role' => 'seller',
                    'seller_profile_id' => $seller->id,
                    'seller_conversation_id' => $sellerConversation->id,
                    'investor_profile_id' => 999,
                    'investor_conversation_id' => $investorConversation->id,
                    'match_score' => 91,
                    'grade' => 'strong',
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $matches = app(RealEstateMatchVerificationFilterService::class)
            ->process($investorConversation);

        $this->assertSame([], $matches);

        $investor->refresh();
        $investorConversation->refresh();

        $this->assertSame([], $investor->data['opportunity_matches']);
        $this->assertTrue(
            $investor->data['opportunity_match_summary']['verification_filtered']
        );
        $this->assertContains(
            'real_estate:match:none',
            $investorConversation->etiketler()
        );
        $this->assertNull($investorConversation->next_follow_up_at);
    }

    public function test_verification_services_are_hard_scoped_to_fresh_account(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other-verification@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $otherBot = AiBot::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Bot',
            'company_name' => 'Other',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
        ]);
        $conversation = ConversationControl::query()->create([
            'user_id' => $otherUser->id,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'other-verification',
            'whatsapp_number' => '905559999999',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);

        $this->assertNull(
            app(RealEstateVerificationService::class)->process($conversation)
        );
        $this->assertNull(
            app(RealEstateVerificationDecisionGuardService::class)->process($conversation)
        );
        $this->assertSame(
            [],
            app(RealEstateMatchVerificationFilterService::class)->process($conversation)
        );
    }

    private function seedRealEstateBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'verification@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'verification-risk-isolated',
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
        array $tags = ['business:real_estate_seller']
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => $tags,
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
    }

    private function sellerProfile(
        ConversationControl $conversation,
        array $data
    ): RealEstateProfile {
        return RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 80,
        ]);
    }
}
