<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakYatirimciSunumlari;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateInvestorPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInvestorPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_operator_gets_printable_privacy_safe_investor_presentation(): void
    {
        $user = $this->seedAccounts();
        $seller = $this->conversation(40, 37, 35, 'presentation-seller', 'Gizli Satıcı', '905551112233');
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$seller->id,
            'user_id'=>40,
            'ai_bot_id'=>35,
            'profile_type'=>'seller',
            'data'=>[
                'city'=>'Muğla',
                'district'=>'Marmaris',
                'neighborhood'=>'Hisarönü',
                'property_type'=>'arsa',
                'area_sqm'=>1200,
                'block_no'=>'123',
                'parcel_no'=>'45',
                'zoning_status'=>'Konut',
                'asking_price'=>4_300_000,
                'minimum_price'=>3_100_000,
                'seller_offer_packet_intelligence'=>['status'=>'ready'],
            ],
            'valuation'=>[],
        ]));

        $this->actingAs($user);
        $presentation = app(RealEstateInvestorPresentationService::class)->build($profile);
        $encoded = json_encode($presentation, JSON_UNESCAPED_UNICODE);

        $this->assertTrue(EmlakYatirimciSunumlari::canAccess());
        $this->assertSame('Muğla', $presentation['facts']['İl']);
        $this->assertSame('123', $presentation['facts']['Ada']);
        $this->assertStringContainsString('%2', $presentation['buyer_fee_note']);
        $this->assertFalse($presentation['guardrails']['automatic_outbound_allowed']);
        $this->assertFalse($presentation['guardrails']['seller_confidential_floor_included']);
        $this->assertStringNotContainsString('Gizli Satıcı', $encoded);
        $this->assertStringNotContainsString('905551112233', $encoded);
        $this->assertStringNotContainsString('4300000', $encoded);
        $this->assertStringNotContainsString('3100000', $encoded);

        $page = new EmlakYatirimciSunumlari;
        $page->selectedProfileId = $profile->id;
        $this->assertSame($profile->id, $page->getSelectedProfileProperty()?->id);
        $this->assertCount(1, $page->getProfilesProperty());
    }

    public function test_foreign_and_investor_profiles_never_enter_seller_presentations(): void
    {
        $this->seedAccounts();
        $foreignConversation = $this->conversation(41, 38, 36, 'foreign-seller', 'Foreign', '905559999999');
        $foreign = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$foreignConversation->id,
            'user_id'=>41,
            'ai_bot_id'=>36,
            'profile_type'=>'seller',
            'data'=>['city'=>'İstanbul','property_type'=>'daire'],
            'valuation'=>[],
        ]));
        $investorConversation = $this->conversation(40, 37, 35, 'presentation-investor', 'Yatırımcı', '905557777777');
        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$investorConversation->id,
            'user_id'=>40,
            'ai_bot_id'=>35,
            'profile_type'=>'investor',
            'data'=>['city'=>'İstanbul'],
            'valuation'=>[],
        ]));

        $this->assertSame([], app(RealEstateInvestorPresentationService::class)->build($foreign));

        $this->actingAs(User::query()->findOrFail(40));
        $this->assertCount(0, (new EmlakYatirimciSunumlari)->getProfilesProperty());

        $this->actingAs(User::query()->findOrFail(41));
        $this->assertFalse(EmlakYatirimciSunumlari::canAccess());
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
            'notes'=>'Gizli müşteri notu',
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
            'email'=>'presentation@example.test',
            'password'=>Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id'=>37,
            'owner_user_id'=>40,
            'name'=>'Emlak AI',
            'slug'=>'presentation',
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
            'email'=>'foreign-presentation@example.test',
            'password'=>Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id'=>38,
            'owner_user_id'=>41,
            'name'=>'Foreign',
            'slug'=>'foreign-presentation',
            'plan'=>'start',
            'seat_limit'=>1,
            'monthly_message_limit'=>1000,
            'status'=>'active',
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
            'whatsapp_instance'=>'foreign-presentation',
            'follow_up_enabled'=>false,
            'second_follow_up_enabled'=>false,
            'ai_enabled'=>true,
        ]);

        return $owner;
    }
}
