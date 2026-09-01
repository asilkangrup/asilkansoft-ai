<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealEstateProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_real_estate_webhook_rejects_foreign_instance(): void
    {
        $this->seedRealEstateBot();

        $response = $this->postJson('/api/real-estate/whatsapp/webhook', [
            'event' => 'messages.upsert',
            'instance' => 'foreign-instance',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_isolated_real_estate_webhook_accepts_own_instance_and_queues_processing(): void
    {
        $this->seedRealEstateBot();
        Queue::fake();

        $response = $this->postJson('/api/real-estate/whatsapp/webhook', [
            'event' => 'messages.upsert',
            'instance' => 'emlak-ai-test',
            'data' => [
                'key' => [
                    'id' => 'wamid-test-1',
                    'fromMe' => false,
                    'remoteJid' => '905551112233@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'Merhaba, arsamı satmak istiyorum.',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'queued' => true,
            ]);

        Queue::assertPushed(ProcessWhatsAppWebhook::class);
    }

    public function test_seller_decision_intelligence_updates_crm_without_scheduling_follow_up(): void
    {
        $bot = $this->seedRealEstateBot();

        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'ai_bot_id' => $bot->id,
            'session_id' => 'seller-session',
            'whatsapp_number' => '905551112233',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);

        RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => $bot->id,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'asking_price' => 5000000,
                'urgency' => 'medium',
                'location_url' => 'https://maps.example/property',
            ],
            'valuation' => [
                'market_min' => 3500000,
                'market_max' => 4000000,
                'quick_sale_min' => 3100000,
                'quick_sale_max' => 3500000,
                'investor_buy_min' => 2800000,
                'investor_buy_max' => 3300000,
                'confidence_score' => 78,
            ],
            'completeness_score' => 100,
            'confidence_score' => 78,
        ]);

        $decision = app(RealEstateDecisionService::class)->process($conversation);

        $this->assertNotNull($decision);
        $this->assertSame('seller', $decision['profile_type']);
        $this->assertSame('reframe_high_ask', $decision['negotiation_posture']);
        $this->assertTrue($decision['ready_for_valuation']);
        $this->assertTrue($decision['ready_for_match']);
        $this->assertGreaterThanOrEqual(70, $decision['lead_score']);

        $conversation->refresh();
        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->firstOrFail();

        $this->assertSame('hot', $conversation->lead_temperature);
        $this->assertSame('qualified', $conversation->lead_status);
        $this->assertNull($conversation->next_follow_up_at);
        $this->assertNotEmpty($conversation->next_best_action);
        $this->assertContains('real_estate:state:hot', $conversation->etiketler());
        $this->assertContains('real_estate:state:ready_for_match', $conversation->etiketler());
        $this->assertSame(
            'reframe_high_ask',
            $profile->data['decision_intelligence']['negotiation_posture']
        );
    }

    public function test_decision_intelligence_does_not_touch_other_wai_accounts(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other WAI User',
            'email' => 'other@example.test',
            'password' => Hash::make('test-password'),
        ]);

        $otherBot = AiBot::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Bot',
            'company_name' => 'Other Company',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'ecommerce',
        ]);

        $conversation = ConversationControl::query()->create([
            'user_id' => $otherUser->id,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'other-session',
            'whatsapp_number' => '905550000000',
            'lead_status' => 'new',
            'lead_score' => 7,
        ]);

        $result = app(RealEstateDecisionService::class)->process($conversation);

        $this->assertNull($result);

        $conversation->refresh();
        $this->assertSame(7, $conversation->lead_score);
        $this->assertNull($conversation->next_best_action);
    }

    private function seedRealEstateBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'emlak-ai@example.test',
            'password' => Hash::make('test-password'),
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
            'whatsapp_instance' => 'emlak-ai-test',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }
}
