<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateDecisionService;
use App\Services\RealEstateWhatsAppProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealEstateProductionFlowTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'real-estate-webhook-test-secret-123456789';

    public function test_isolated_real_estate_webhook_rejects_missing_signature(): void
    {
        $this->seedRealEstateBot();

        $this->postJson('/api/real-estate/whatsapp/webhook', [
            'event' => 'messages.upsert',
            'instance' => 'emlak-ai-test',
        ])->assertStatus(401);
    }

    public function test_isolated_real_estate_webhook_rejects_foreign_instance(): void
    {
        $this->seedRealEstateBot();

        $response = $this->withToken($this->jwt(self::WEBHOOK_SECRET))
            ->postJson('/api/real-estate/whatsapp/webhook', [
                'event' => 'messages.upsert',
                'instance' => 'foreign-instance',
            ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_isolated_real_estate_webhook_accepts_own_signed_instance_and_queues_processing(): void
    {
        $this->seedRealEstateBot();
        Queue::fake();

        $response = $this->withToken($this->jwt(self::WEBHOOK_SECRET))
            ->postJson('/api/real-estate/whatsapp/webhook', [
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

    public function test_secure_provisioning_uses_current_evolution_webhook_schema_and_jwt_key(): void
    {
        $this->seedRealEstateBot();

        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        Http::fake([
            'https://evolution.example.test/webhook/set/emlak-ai-test' =>
                Http::response(['ok' => true], 200),
        ]);

        app(RealEstateWhatsAppProvisioningService::class)->configureWebhook(
            'emlak-ai-test',
            'https://wai.example.test/api/real-estate/whatsapp/webhook'
        );

        Http::assertSent(function ($request): bool {
            $webhook = $request['webhook'] ?? [];

            return $request->url() === 'https://evolution.example.test/webhook/set/emlak-ai-test'
                && $request->hasHeader('apikey', 'evolution-test-key')
                && ($webhook['enabled'] ?? null) === true
                && ($webhook['byEvents'] ?? null) === false
                && ($webhook['base64'] ?? null) === false
                && ($webhook['headers']['jwt_key'] ?? null) === self::WEBHOOK_SECRET
                && ($webhook['events'] ?? []) === [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                ];
        });
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

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-test',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString(self::WEBHOOK_SECRET),
            ],
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

    private function jwt(string $secret): string
    {
        $now = time();
        $header = $this->base64Url(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'iat' => $now,
            'exp' => $now + 600,
            'app' => 'evolution',
            'action' => 'webhook',
        ], JSON_THROW_ON_ERROR));
        $signature = $this->base64Url(
            hash_hmac('sha256', $header.'.'.$payload, $secret, true)
        );

        return $header.'.'.$payload.'.'.$signature;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
