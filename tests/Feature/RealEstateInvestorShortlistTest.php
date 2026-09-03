<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakYatirimciEslesmeleri;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateInvestorShortlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorShortlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_shortlist_resolves_only_isolated_investors_and_keeps_seller_pressure_private(): void
    {
        $owner = $this->seedAccounts();
        $sellerConversation = $this->conversation(40, 37, 35, 'shortlist-seller', 'Satıcı A', '905551110001');
        $readyConversation = $this->conversation(40, 37, 35, 'shortlist-ready', 'Yatırımcı Hazır', '905551110002');
        $buildingConversation = $this->conversation(40, 37, 35, 'shortlist-building', 'Yatırımcı Eksik', '905551110003');
        $foreignConversation = $this->conversation(41, 38, 36, 'shortlist-foreign', 'Yabancı Aday', '905551110004');

        $ready = $this->profile($readyConversation, 40, 35, 'investor', [
            'city'=>'Muğla',
            'district'=>'Marmaris',
            'property_type'=>'arsa',
            'budget_max'=>5_000_000,
            'investment_goal'=>'değer artışı',
            'financing'=>'cash',
            'timeline'=>'1 ay',
            'location_flexibility'=>'same_city',
            'accepts_shared_title'=>false,
            'target_discount_percent'=>20,
        ]);
        $building = $this->profile($buildingConversation, 40, 35, 'investor', [
            'city'=>'Muğla',
            'property_type'=>'arsa',
            'budget_max'=>3_800_000,
        ]);
        $foreign = $this->profile($foreignConversation, 41, 36, 'investor', [
            'city'=>'Muğla',
            'property_type'=>'arsa',
            'budget_max'=>9_000_000,
            'investment_goal'=>'al-sat',
        ]);

        $seller = $this->profile($sellerConversation, 40, 35, 'seller', [
            'city'=>'Muğla',
            'district'=>'Marmaris',
            'property_type'=>'arsa',
            'area_sqm'=>1200,
            'asking_price'=>4_300_000,
            'minimum_price'=>3_100_000,
            'urgency'=>'high',
            'urgency_reason'=>'nakit ihtiyacı',
            'opportunity_matches'=>[
                [
                    'seller_profile_id'=>999999,
                    'candidate_profile_id'=>$ready->id,
                    'investor_profile_id'=>$ready->id,
                    'match_score'=>99,
                    'grade'=>'strong',
                ],
                [
                    'seller_profile_id'=>null,
                    'candidate_profile_id'=>$ready->id,
                    'investor_profile_id'=>$ready->id,
                    'candidate_conversation_id'=>$readyConversation->id,
                    'match_score'=>88,
                    'grade'=>'strong',
                    'reasons'=>['Bütçe hedef fiyatı karşılıyor.','Bölge uygun.'],
                    'risks'=>['İmar teyit edilmeli.'],
                    'criteria_checks'=>['budget_coverage_percent'=>116.3,'hard_filters_passed'=>true],
                ],
                [
                    'candidate_profile_id'=>$building->id,
                    'investor_profile_id'=>$building->id,
                    'match_score'=>73,
                    'grade'=>'good',
                    'reasons'=>['Taşınmaz türü aynı.'],
                    'risks'=>['Yatırım amacı eksik.'],
                ],
                [
                    'candidate_profile_id'=>$foreign->id,
                    'investor_profile_id'=>$foreign->id,
                    'match_score'=>97,
                    'grade'=>'strong',
                ],
            ],
        ]);

        $payload = app(RealEstateInvestorShortlistService::class)->buildForSeller($seller);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertSame(2, $payload['candidate_count']);
        $this->assertSame(1, $payload['ready_to_call_count']);
        $this->assertSame($ready->id, $payload['candidates'][0]['investor_profile_id']);
        $this->assertSame('ready_to_call', $payload['candidates'][0]['readiness']);
        $this->assertTrue($payload['candidates'][0]['mandate_core_ready']);
        $this->assertSame('criteria_incomplete', $payload['candidates'][1]['readiness']);
        $this->assertFalse($payload['guardrails']['automatic_outbound_allowed']);
        $this->assertFalse($payload['guardrails']['automatic_follow_up_allowed']);
        $this->assertFalse($payload['guardrails']['seller_confidential_floor_exposed']);
        $this->assertFalse($payload['guardrails']['seller_urgency_exposed']);
        $this->assertStringNotContainsString('Yabancı Aday', $encoded);
        $this->assertStringNotContainsString('3100000', $encoded);
        $this->assertStringNotContainsString('nakit ihtiyacı', $encoded);
        $this->assertStringNotContainsString('minimum_price', $encoded);
        $this->assertStringNotContainsString('urgency_reason', $encoded);

        $this->actingAs($owner);
        $page = new EmlakYatirimciEslesmeleri;
        $page->selectedSellerProfileId = $seller->id;
        $this->assertTrue(EmlakYatirimciEslesmeleri::canAccess());
        $this->assertSame(2, $page->getShortlistProperty()['candidate_count']);
        $this->assertCount(1, $page->getSellersProperty());
    }

    public function test_foreign_operator_and_non_seller_profiles_are_rejected(): void
    {
        $this->seedAccounts();
        $foreign = User::query()->findOrFail(41);
        $investorConversation = $this->conversation(40, 37, 35, 'shortlist-not-seller', 'Investor', '905551119999');
        $investor = $this->profile($investorConversation, 40, 35, 'investor', [
            'city'=>'İstanbul',
            'budget_max'=>5_000_000,
        ]);

        $this->assertSame([], app(RealEstateInvestorShortlistService::class)->buildForSeller($investor));

        $this->actingAs($foreign);
        $this->assertFalse(EmlakYatirimciEslesmeleri::canAccess());
    }

    private function profile(
        ConversationControl $conversation,
        int $user,
        int $bot,
        string $type,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,
            'user_id'=>$user,
            'ai_bot_id'=>$bot,
            'profile_type'=>$type,
            'data'=>$data,
            'valuation'=>[],
            'completeness_score'=>70,
            'confidence_score'=>75,
        ]));
    }

    private function conversation(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $name,
        string $phone,
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id'=>$user,
            'organization_id'=>$organization,
            'ai_bot_id'=>$bot,
            'session_id'=>$session,
            'whatsapp_number'=>$phone,
            'customer_name'=>$name,
            'notes'=>'Operatör özel notu',
            'tags'=>[],
            'lead_status'=>'new',
            'lead_score'=>0,
            'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,
            'human_takeover'=>false,
        ]);
    }

    private function seedAccounts(): User
    {
        $owner = User::query()->forceCreate([
            'id'=>40,
            'name'=>'Emlak AI',
            'email'=>'shortlist@example.test',
            'password'=>Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id'=>37,
            'owner_user_id'=>40,
            'name'=>'Emlak AI',
            'slug'=>'shortlist',
            'plan'=>'start',
            'seat_limit'=>1,
            'monthly_message_limit'=>1000,
            'status'=>'active',
        ]);
        $owner->organizations()->syncWithoutDetaching([
            37=>['role'=>'owner','status'=>'active','joined_at'=>now()],
        ]);
        AiBot::query()->forceCreate([
            'id'=>35,
            'user_id'=>40,
            'name'=>'Emlak AI',
            'company_name'=>'Asilkan Gayrimenkul',
            'openai_model'=>'gpt-5-mini',
            'status'=>'active',
            'business_sector'=>'real_estate',
            'lead_scoring_profile'=>'real_estate',
            'whatsapp_instance'=>'emlak-ai-35',
            'follow_up_enabled'=>false,
            'second_follow_up_enabled'=>false,
            'ai_enabled'=>true,
        ]);

        $foreign = User::query()->forceCreate([
            'id'=>41,
            'name'=>'Foreign',
            'email'=>'foreign-shortlist@example.test',
            'password'=>Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id'=>38,
            'owner_user_id'=>41,
            'name'=>'Foreign',
            'slug'=>'foreign-shortlist',
            'plan'=>'start',
            'seat_limit'=>1,
            'monthly_message_limit'=>1000,
            'status'=>'active',
        ]);
        $foreign->organizations()->syncWithoutDetaching([
            38=>['role'=>'owner','status'=>'active','joined_at'=>now()],
        ]);
        AiBot::query()->forceCreate([
            'id'=>36,
            'user_id'=>41,
            'name'=>'Foreign',
            'company_name'=>'Foreign',
            'openai_model'=>'gpt-5-mini',
            'status'=>'active',
            'business_sector'=>'real_estate',
            'lead_scoring_profile'=>'real_estate',
            'whatsapp_instance'=>'foreign-shortlist',
            'follow_up_enabled'=>false,
            'second_follow_up_enabled'=>false,
            'ai_enabled'=>true,
        ]);

        return $owner;
    }
}
