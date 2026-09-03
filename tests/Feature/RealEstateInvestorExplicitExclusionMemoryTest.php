<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateInvestorExclusionMemoryService;
use App\Services\RealEstateInvestorMandateService;
use App\Services\RealEstateMatchVerificationFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorExplicitExclusionMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_customer_no_go_criteria_are_persisted_without_follow_up_side_effects(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'exclusion-investor', '905551300001');
        $profile = $this->profile($conversation, 'investor', [
            'city' => 'Muğla, İstanbul',
            'district' => 'Bodrum, Fethiye',
            'property_type' => 'arsa, tarla, daire',
            'budget_max' => 8_000_000,
        ]);

        $this->customerMessage($conversation, 'Tarla istemiyorum. İstanbul da olmasın, Bodrum hariç bakabiliriz.');

        $summary = app(RealEstateInvestorExclusionMemoryService::class)->sync($profile);

        $this->assertContains('tarla', $summary['excluded_property_types']);
        $this->assertContains('İstanbul', $summary['excluded_cities']);
        $this->assertContains('Bodrum', $summary['excluded_districts']);
        $this->assertSame(3, $summary['explicit_no_go_count']);
        $this->assertTrue($summary['explicit_only']);
        $this->assertFalse($summary['guardrails']['follow_up_scheduling_allowed']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);

        $stored = $profile->fresh()->data['investor_exclusion_intelligence'];
        $this->assertSame($summary['excluded_property_types'], $stored['excluded_property_types']);
        $this->assertArrayNotHasKey('raw_message', $stored);
        $this->assertArrayNotHasKey('whatsapp_number', $stored);

        $mandate = app(RealEstateInvestorMandateService::class)
            ->summaryForProfile($profile->fresh());
        $this->assertTrue($mandate['explicit_exclusions_present']);
        $this->assertContains(
            'tarla',
            $mandate['explicit_match_constraints']['excluded_property_types']
        );
    }

    public function test_later_explicit_re_inclusion_removes_older_exclusion(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'reinclusion-investor', '905551300002');
        $profile = $this->profile($conversation, 'investor', [
            'city' => 'Muğla',
            'district' => 'Bodrum, Fethiye',
            'property_type' => 'arsa, tarla',
            'budget_max' => 6_000_000,
        ]);

        $this->customerMessage($conversation, 'Tarla istemiyorum, Bodrum olmasın.');
        $this->customerMessage($conversation, 'Fikrimi değiştirdim, tarla da olur ve Bodrum da olabilir.');

        $summary = app(RealEstateInvestorExclusionMemoryService::class)->sync($profile);

        $this->assertNotContains('tarla', $summary['excluded_property_types']);
        $this->assertNotContains('Bodrum', $summary['excluded_districts']);
        $this->assertSame(0, $summary['explicit_no_go_count']);
        $this->assertGreaterThanOrEqual(2, $summary['source_message_count']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_final_match_filter_removes_property_that_conflicts_with_explicit_investor_exclusion(): void
    {
        $bot = $this->seedIsolatedAccount();
        $sellerConversation = $this->conversation($bot, 'excluded-seller', '905551300003');
        $investorConversation = $this->conversation($bot, 'excluded-investor', '905551300004');

        $seller = $this->profile($sellerConversation, 'seller', [
            'city' => 'Muğla',
            'district' => 'Fethiye',
            'property_type' => 'tarla',
            'area_sqm' => 1400,
            'title_deed_type' => 'müstakil',
            'verification_intelligence' => [
                'safe_to_match' => true,
                'status' => 'verified_with_evidence',
                'risk_score' => 10,
            ],
            'media_findings' => [[
                'document_type' => 'tapu',
                'confidence_score' => 94,
                'district' => 'Fethiye',
                'area_sqm' => 1400,
                'title_deed_type' => 'müstakil',
            ]],
        ]);
        $investor = $this->profile($investorConversation, 'investor', [
            'city' => 'Muğla',
            'property_type' => 'arsa, tarla',
            'budget_max' => 7_000_000,
        ]);

        $this->customerMessage($investorConversation, 'Muğla olur ama tarla istemiyorum, sadece arsa bakıyorum.');

        $this->assertTrue(
            app(RealEstateInvestorExclusionMemoryService::class)
                ->blocks($investor->fresh(), $seller->fresh())
        );

        $sellerData = $seller->fresh()->data;
        $sellerData['opportunity_matches'] = [[
            'seller_profile_id' => $seller->id,
            'investor_profile_id' => $investor->id,
            'candidate_profile_id' => $investor->id,
            'candidate_role' => 'investor',
            'match_score' => 91,
            'grade' => 'strong',
            'reasons' => ['Lokasyon ve bütçe uyumlu.'],
            'risks' => [],
        ]];
        RealEstateProfile::withoutEvents(
            fn () => $seller->update(['data' => $sellerData])
        );

        $matches = app(RealEstateMatchVerificationFilterService::class)
            ->process($sellerConversation);

        $this->assertSame([], $matches);
        $this->assertContains('real_estate:match:none', $sellerConversation->fresh()->etiketler());
        $this->assertTrue(
            $seller->fresh()->data['opportunity_match_summary']['explicit_investor_exclusion_filtered']
        );
        $this->assertContains(
            'tarla',
            $investor->fresh()->data['investor_exclusion_intelligence']['excluded_property_types']
        );
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_out_of_scope_profile_cannot_read_or_persist_exclusion_memory(): void
    {
        $foreignUser = User::query()->forceCreate([
            'id' => 41,
            'name' => 'Foreign',
            'email' => 'foreign-exclusion@example.test',
            'password' => Hash::make('test'),
        ]);
        $foreignBot = AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 41,
            'name' => 'Foreign Bot',
            'company_name' => 'Foreign',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'foreign-instance',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 41,
            'organization_id' => null,
            'ai_bot_id' => 36,
            'session_id' => 'foreign-exclusion',
            'whatsapp_number' => '905551399999',
            'tags' => [],
            'next_follow_up_at' => null,
        ]);
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 41,
            'ai_bot_id' => $foreignBot->id,
            'profile_type' => 'investor',
            'data' => ['property_type' => 'arsa, tarla'],
            'valuation' => [],
            'completeness_score' => 20,
            'confidence_score' => 20,
        ]));

        $summary = app(RealEstateInvestorExclusionMemoryService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertArrayNotHasKey(
            'investor_exclusion_intelligence',
            $profile->fresh()->data
        );
    }

    private function customerMessage(ConversationControl $conversation, string $message): ChatMessage
    {
        return ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => $message,
            'message_type' => 'text',
        ]);
    }

    private function profile(
        ConversationControl $conversation,
        string $type,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 80,
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
            'email' => 'exclusion-memory@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'exclusion-memory',
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
