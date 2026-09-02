<?php
namespace Tests\Feature;
use App\Models\{AiBot,ConversationControl,Organization,RealEstateProfile,User};
use App\Services\{RealEstateOpenAIClient,RealEstateValuationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateAutomaticValuationResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_confirmation_runs_pending_research_and_reuses_fresh_result(): void
    {
        [, $conversation] = $this->seedSeller();
        $client = new class extends RealEstateOpenAIClient {
            public int $calls = 0; public array $requests = [];
            public function createResponse(AiBot $aiBot, array $request): mixed {
                $this->calls++; $this->requests[] = $request;
                return new class {
                    public string $status = 'completed';
                    public string $outputText;
                    public function __construct() { $this->outputText = json_encode([
                        'market_min'=>4250000,'market_max'=>5250000,
                        'realistic_sale_min'=>4100000,'realistic_sale_max'=>4750000,
                        'quick_sale_min'=>3600000,'quick_sale_max'=>4100000,
                        'investor_buy_min'=>3900000,'investor_buy_max'=>4200000,
                        'confidence_score'=>72,'market_gap_percent'=>-9,'summary'=>'Test','next_best_action'=>'Pazarlık','missing_data'=>[],
                        'sources'=>['https://example.test/a','https://example.test/b'],
                        'comparables'=>[
                            ['source'=>'A','url'=>'https://example.test/a','listing_price'=>4250000,'area_sqm'=>400,'location'=>'İstanbul, Silivri','property_type'=>'arsa','observed_at'=>'2026-09-01'],
                            ['source'=>'B','url'=>'https://example.test/b','listing_price'=>4900000,'area_sqm'=>405,'location'=>'İstanbul, Silivri','property_type'=>'arsa','observed_at'=>'2026-09-01']
                        ]
                    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
                    public function toArray(): array { return ['id'=>'resp_auto','model'=>'gpt-5.4','usage'=>['input_tokens'=>100,'output_tokens'=>250,'total_tokens'=>350]]; }
                };
            }
        };
        $this->app->instance(RealEstateOpenAIClient::class, $client);
        $result = app(RealEstateValuationService::class)->process($conversation, 'Olur');
        $this->assertNotNull($result);
        $this->assertSame(1, $client->calls);
        $this->assertSame(4000, $client->requests[0]['max_output_tokens']);
        $this->assertSame('low', $client->requests[0]['reasoning']['effort']);
        $this->assertSame('web_search_preview', $client->requests[0]['tools'][0]['type']);
        $this->assertEquals(3800000.0, $result['investor_buy_max']);
        $this->assertLessThanOrEqual(3800000.0, $result['investor_buy_min']);
        $this->assertSame('fresh', $result['freshness_status']);
        $this->assertTrue($result['usable_for_decision']);
        $this->assertTrue($result['usable_for_matching']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertNotNull(app(RealEstateValuationService::class)->process($conversation, 'Olur'));
        $this->assertSame(1, $client->calls);
    }

    public function test_short_confirmation_without_research_readiness_does_not_search(): void
    {
        [, $conversation, $profile] = $this->seedSeller();
        $data=$profile->data; unset($data['asking_price']);
        $data['next_best_action_intelligence']['action_code']='complete_seller_core_data';
        $profile->forceFill(['data'=>$data])->saveQuietly();
        $conversation->forceFill(['next_best_action'=>'Eksik temel bilgiyi tamamla.'])->saveQuietly();
        $client = new class extends RealEstateOpenAIClient { public int $calls=0; public function createResponse(AiBot $aiBot,array $request): mixed { $this->calls++; throw new \RuntimeException('Unexpected web search'); } };
        $this->app->instance(RealEstateOpenAIClient::class,$client);
        $this->assertNull(app(RealEstateValuationService::class)->process($conversation,'Olur'));
        $this->assertSame(0,$client->calls);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedSeller(): array
    {
        User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'auto-valuation@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'auto-valuation','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        $bot=AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','openai_api_key'=>'sk-test-dedicated','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        $conversation=ConversationControl::query()->forceCreate(['user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,'session_id'=>'auto-valuation','whatsapp_number'=>'905550000035','tags'=>['business:real_estate_seller'],'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold','next_follow_up_at'=>null,'human_takeover'=>false]);
        $profile=RealEstateProfile::query()->create(['conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,'profile_type'=>'seller','data'=>['property_type'=>'arsa','city'=>'İstanbul','district'=>'Silivri','neighborhood'=>'Büyükçavuşlu','area_sqm'=>400.01,'asking_price'=>4300000],'valuation'=>[],'completeness_score'=>90,'confidence_score'=>80]);
        $data=$profile->fresh()->data; $data['next_best_action_intelligence']=['action_code'=>'refresh_valuation_research','priority'=>'high','action_text'=>'Güncel emsal araştırmasını yenile.','single_question'=>null,'blocking'=>true,'reason_codes'=>['valuation_missing_or_stale']];
        $profile->forceFill(['data'=>$data])->saveQuietly();
        $conversation->forceFill(['next_best_action'=>'Güncel emsal araştırmasını yenile.'])->saveQuietly();
        return [$bot,$conversation->fresh(),$profile->fresh()];
    }
}
