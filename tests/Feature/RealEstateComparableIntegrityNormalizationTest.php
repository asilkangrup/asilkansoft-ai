<?php
namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateComparableIntegrityService;
use App\Services\RealEstateValuationResearchOutputGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateComparableIntegrityNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualified_arsa_labels_survive_guard_and_become_integrity_usable(): void
    {
        $profile = $this->seedProfile();
        $valuation = app(RealEstateValuationResearchOutputGuardService::class)->buildSanitizedValuation([
            'market_min'=>4200000,'market_max'=>5250000,
            'realistic_sale_min'=>3900000,'realistic_sale_max'=>4650000,
            'quick_sale_min'=>3450000,'quick_sale_max'=>3950000,
            'investor_buy_min'=>3000000,'investor_buy_max'=>3600000,
            'confidence_score'=>74,
            'sources'=>['https://one.example/a','https://two.example/b','https://three.example/c'],
            'comparables'=>[
                ['url'=>'https://one.example/a','listing_price'=>4250000,'area_sqm'=>400,'location'=>'İstanbul Silivri Büyükçavuşlu','property_type'=>'Konut imarlı arsa'],
                ['url'=>'https://two.example/b','listing_price'=>4700000,'area_sqm'=>410,'location'=>'İstanbul Silivri Büyükçavuşlu','property_type'=>'Satılık arsa'],
                ['url'=>'https://three.example/c','listing_price'=>5000000,'area_sqm'=>430,'location'=>'İstanbul Silivri Büyükçavuşlu','property_type'=>'arsa'],
            ],
        ], $profile->data);

        $profile->forceFill(['valuation'=>$valuation])->saveQuietly();
        $profile->refresh();
        $integrity = app(RealEstateComparableIntegrityService::class)->assess($profile);

        $this->assertSame('arsa', $valuation['comparables'][0]['property_type']);
        $this->assertSame('arsa', $valuation['comparables'][1]['property_type']);
        $this->assertSame(3_900_000.0, $valuation['realistic_sale_min']);
        $this->assertSame(4_650_000.0, $valuation['realistic_sale_max']);
        $this->assertSame(3, $integrity['usable_comparable_count']);
        $this->assertTrue($integrity['sufficient_for_decision']);
        $this->assertTrue($integrity['sufficient_for_matching']);
        $this->assertNull($profile->conversation()->first()->next_follow_up_at);
    }

    public function test_unrelated_property_type_still_fails_closed(): void
    {
        $profile = $this->seedProfile();
        $valuation = app(RealEstateValuationResearchOutputGuardService::class)->buildSanitizedValuation([
            'comparables'=>[[
                'url'=>'https://one.example/a','listing_price'=>4250000,'area_sqm'=>400,
                'location'=>'İstanbul Silivri Büyükçavuşlu','property_type'=>'daire',
            ]],
        ], $profile->data);
        $this->assertSame('mismatch', $valuation['comparables'][0]['property_type']);
    }

    private function seedProfile(): RealEstateProfile
    {
        User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'integrity-normalization@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'integrity-normalization','status'=>'active']);
        $bot=AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        $conversation=ConversationControl::query()->create(['user_id'=>40,'organization_id'=>37,'ai_bot_id'=>$bot->id,'session_id'=>'integrity-normalization','whatsapp_number'=>'905550000035','tags'=>['business:real_estate_seller'],'lead_status'=>'new','next_follow_up_at'=>null]);
        return RealEstateProfile::query()->create(['conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,'profile_type'=>'seller','data'=>['property_type'=>'arsa','city'=>'İstanbul','district'=>'Silivri','neighborhood'=>'Büyükçavuşlu','area_sqm'=>400.01,'asking_price'=>4300000],'valuation'=>[],'completeness_score'=>90,'confidence_score'=>80]);
    }
}
