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

    public function test_isolated_operator_gets_printable_privacy_safe_investor_presentation_only_when_authorized(): void
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
                'media_findings'=>[[
                    'media_category'=>'property_photo',
                    'document_type'=>'Taşınmaz fotoğrafı',
                    'confidence_score'=>0,
                ]],
                'authorization_control'=>$this->readyAuthorization(),
            ],
            'valuation'=>[],
        ]));

        $this->actingAs($user);
        $presentation = app(RealEstateInvestorPresentationService::class)->build($profile);
        $encoded = json_encode($presentation, JSON_UNESCAPED_UNICODE);

        $this->assertTrue(EmlakYatirimciSunumlari::canAccess());
        $this->assertSame('Muğla', $presentation['facts']['İl']);
        $this->assertSame('123', $presentation['facts']['Ada']);
        $this->assertTrue($presentation['export_allowed']);
        $this->assertTrue($presentation['offer_collection_allowed']);
        $this->assertSame('ready_for_authorized_presentation', $presentation['status']);
        $this->assertSame('ready', $presentation['authorization_status']);
        $this->assertStringContainsString('%2', $presentation['buyer_fee_note']);
        $this->assertFalse($presentation['guardrails']['automatic_outbound_allowed']);
        $this->assertFalse($presentation['guardrails']['automatic_customer_follow_up_allowed']);
        $this->assertFalse($presentation['guardrails']['seller_confidential_floor_included']);
        $this->assertTrue($presentation['guardrails']['authorization_required_before_external_presentation']);
        $this->assertStringNotContainsString('Gizli Satıcı', $encoded);
        $this->assertStringNotContainsString('905551112233', $encoded);
        $this->assertStringNotContainsString('4300000', $encoded);
        $this->assertStringNotContainsString('3100000', $encoded);

        $page = new EmlakYatirimciSunumlari;
        $page->selectedProfileId = $profile->id;
        $this->assertSame($profile->id, $page->getSelectedProfileProperty()?->id);
        $this->assertCount(1, $page->getProfilesProperty());
    }

    public function test_complete_seller_packet_without_authorization_is_draft_only_and_cannot_be_exported(): void
    {
        $this->seedAccounts();
        $seller = $this->conversation(40, 37, 35, 'presentation-blocked', 'Gizli Satıcı 2', '905551112244');
        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$seller->id,
            'user_id'=>40,
            'ai_bot_id'=>35,
            'profile_type'=>'seller',
            'data'=>[
                'city'=>'Muğla',
                'district'=>'Bodrum',
                'property_type'=>'villa',
                'area_sqm'=>280,
                'asking_price'=>12_000_000,
                'block_no'=>'10',
                'parcel_no'=>'20',
                'media_findings'=>[[
                    'media_category'=>'property_photo',
                    'document_type'=>'Taşınmaz fotoğrafı',
                    'confidence_score'=>0,
                ]],
            ],
            'valuation'=>[],
        ]));

        $presentation = app(RealEstateInvestorPresentationService::class)->build($profile);
        $encoded = json_encode($presentation, JSON_UNESCAPED_UNICODE);

        $this->assertFalse($presentation['export_allowed']);
        $this->assertFalse($presentation['offer_collection_allowed']);
        $this->assertSame('draft_blocked', $presentation['status']);
        $this->assertSame('incomplete', $presentation['authorization_status']);
        $this->assertContains('mandate_missing', $presentation['authorization_blocking_reason_codes']);
        $this->assertStringContainsString('yalnız operatör taslağıdır', $presentation['offer_note']);
        $this->assertStringContainsString('Yetkilendirme', $presentation['authorization_note']);
        $this->assertFalse($presentation['guardrails']['export_allowed']);
        $this->assertFalse($presentation['guardrails']['offer_collection_allowed']);
        $this->assertStringNotContainsString('Gizli Satıcı 2', $encoded);
        $this->assertStringNotContainsString('905551112244', $encoded);
        $this->assertStringNotContainsString('12000000', $encoded);
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

    private function readyAuthorization(): array
    {
        return [
            'status'=>'ready',
            'mandate_type'=>'exclusive',
            'signed_at'=>now()->toDateString(),
            'expires_at'=>now()->addMonths(3)->toDateString(),
            'seller_presentation_consent'=>true,
            'commission_terms_acknowledged'=>true,
            'title_owner_confirmed'=>true,
            'authorization_document_present'=>true,
            'legal_review_required'=>false,
            'history'=>[],
        ];
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
            'whatsapp_instance'=>'foreign-authorization',
            'follow_up_enabled'=>false,
            'second_follow_up_enabled'=>false,
            'ai_enabled'=>true,
        ]);

        return $owner;
    }
}
