<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateEvidenceQualityService;
use App\Services\RealEstateMatchVerificationFilterService;
use App\Services\RealEstateVerificationDecisionGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateEvidenceQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_screenshot_cannot_make_a_seller_match_ready_even_when_fields_agree(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-listing');
        $profile = $this->sellerProfile($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'block_no' => '101',
            'parcel_no' => '22',
            'title_deed_type' => 'müstakil',
            'verification_intelligence' => [
                'status' => 'corroborated',
                'risk_score' => 10,
                'safe_to_match' => true,
                'conflicts' => [],
                'next_best_action' => 'Eşleştirmeye ilerle.',
            ],
            'decision_intelligence' => [
                'lead_score' => 90,
                'lead_temperature' => 'hot',
                'ready_for_match' => true,
                'next_best_action' => 'Yatırımcıyla eşleştir.',
            ],
            'media_findings' => [[
                'document_type' => 'ilan ekran görüntüsü',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'müstakil',
                'confidence_score' => 98,
            ]],
        ]);

        $quality = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: false);
        $decision = app(RealEstateVerificationDecisionGuardService::class)
            ->process($conversation);

        $this->assertSame('supporting_only', $quality['status']);
        $this->assertFalse($quality['sufficient_for_matching']);
        $this->assertSame(0, $quality['qualified_documentary_evidence_count']);
        $this->assertFalse($decision['ready_for_match']);
        $this->assertFalse($decision['evidence_sufficient_for_matching']);
        $this->assertSame('supporting_only', $decision['evidence_quality_status']);
        $this->assertStringContainsString('İlan veya genel görsel', $decision['next_best_action']);

        $conversation->refresh();
        $profile->refresh();

        $this->assertContains('real_estate:evidence:supporting_only', $conversation->etiketler());
        $this->assertNotContains('real_estate:state:ready_for_match', $conversation->etiketler());
        $this->assertFalse(
            $profile->data['evidence_quality_intelligence']['sufficient_for_matching']
        );
        $this->assertNull($conversation->next_follow_up_at);
    }

    public function test_clear_title_deed_identity_signals_can_support_matching_without_claiming_official_verification(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'evidence-tapu');
        $profile = $this->sellerProfile($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'block_no' => '101',
            'parcel_no' => '22',
            'title_deed_type' => 'müstakil',
            'verification_intelligence' => [
                'status' => 'corroborated',
                'risk_score' => 10,
                'safe_to_match' => true,
                'conflicts' => [],
            ],
            'decision_intelligence' => [
                'lead_score' => 88,
                'lead_temperature' => 'hot',
                'ready_for_match' => true,
                'next_best_action' => 'Yatırımcıyla eşleştir.',
            ],
            'media_findings' => [[
                'document_type' => 'tapu senedi',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1200,
                'block_no' => '101',
                'parcel_no' => '22',
                'title_deed_type' => 'müstakil',
                'confidence_score' => 92,
            ]],
        ]);

        $quality = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: false);
        $decision = app(RealEstateVerificationDecisionGuardService::class)
            ->process($conversation);

        $this->assertSame('documentary_support', $quality['status']);
        $this->assertTrue($quality['sufficient_for_matching']);
        $this->assertFalse($quality['official_verification_complete']);
        $this->assertContains('block_parcel', $quality['identity_signals']);
        $this->assertTrue($decision['ready_for_match']);
        $this->assertTrue($decision['evidence_sufficient_for_matching']);

        $conversation->refresh();
        $this->assertContains('real_estate:evidence:documentary_support', $conversation->etiketler());
        $this->assertContains('real_estate:evidence:match_sufficient', $conversation->etiketler());
        $this->assertNull($conversation->next_follow_up_at);
    }

    public function test_investor_match_filter_removes_seller_backed_only_by_listing_media(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'evidence-seller');
        $investorConversation = $this->conversation(
            $bot,
            'evidence-investor',
            ['business:real_estate_investor']
        );

        $seller = $this->sellerProfile($sellerConversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'verification_intelligence' => [
                'status' => 'corroborated',
                'risk_score' => 10,
                'safe_to_match' => true,
            ],
            'media_findings' => [[
                'document_type' => 'ilan screenshot',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'confidence_score' => 99,
            ]],
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
            'completeness_score' => 85,
            'confidence_score' => 85,
        ]);

        $matches = app(RealEstateMatchVerificationFilterService::class)
            ->process($investorConversation);

        $this->assertSame([], $matches);
        $investor->refresh();
        $this->assertSame([], $investor->data['opportunity_matches']);
        $this->assertTrue(
            $investor->data['opportunity_match_summary']['evidence_quality_filtered']
        );
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_evidence_quality_service_is_hard_scoped_to_fresh_account(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other',
            'email' => 'evidence-other@example.test',
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
            'session_id' => 'evidence-other',
            'whatsapp_number' => '905559999999',
            'tags' => [],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'ai_bot_id' => $otherBot->id,
            'profile_type' => 'seller',
            'data' => ['media_findings' => [['document_type' => 'tapu']]],
            'valuation' => [],
            'completeness_score' => 50,
            'confidence_score' => 50,
        ]);

        $quality = app(RealEstateEvidenceQualityService::class)
            ->assess($profile, persist: true);

        $this->assertSame('out_of_scope', $quality['status']);
        $this->assertFalse($quality['sufficient_for_matching']);
        $this->assertArrayNotHasKey(
            'evidence_quality_intelligence',
            $profile->fresh()->data
        );
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'evidence@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-evidence',
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
        array $tags = ['business:real_estate_seller']
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
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
            'confidence_score' => 85,
        ]);
    }
}
