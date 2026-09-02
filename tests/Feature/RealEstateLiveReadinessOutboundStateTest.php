<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateLiveReadinessOutboundStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_fresh_network_send_does_not_make_live_service_unhealthy(): void
    {
        $this->seedReadyScope();
        $this->delivery(now()->subSeconds(20));

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', true)
            ->assertJsonPath('checks.unresolved_outbound_deliveries', 0)
            ->assertJsonPath('outbound_reconciliation.fresh_sending', 1)
            ->assertJsonPath('outbound_reconciliation.stale_sending', 0)
            ->assertJsonPath('outbound_reconciliation.uncertain', 0)
            ->assertJsonPath('outbound_reconciliation.blocking', 0);

        $this->assertNotContains(
            'unresolved_outbound_deliveries',
            $response->json('blocking_checks')
        );
    }

    public function test_stale_network_boundary_send_remains_a_live_readiness_blocker(): void
    {
        $this->seedReadyScope();
        $this->delivery(now()->subMinutes(3));

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', false)
            ->assertJsonPath('checks.unresolved_outbound_deliveries', 1)
            ->assertJsonPath('outbound_reconciliation.fresh_sending', 0)
            ->assertJsonPath('outbound_reconciliation.stale_sending', 1)
            ->assertJsonPath('outbound_reconciliation.blocking', 1);

        $this->assertContains(
            'unresolved_outbound_deliveries',
            $response->json('blocking_checks')
        );
    }

    private function seedReadyScope(): void
    {
        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        Http::fake([
            'https://evolution.example.test/instance/connectionState/emlak-ai-35' =>
                Http::response([
                    'instance' => ['state' => 'open'],
                ], 200),
        ]);

        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'live-outbound-state@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'live-outbound-state',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString(
                    'real-estate-live-outbound-secret-123456789'
                ),
            ],
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-live-outbound-state-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connected',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
            'subscription_status' => 'active',
            'group_routing_enabled' => false,
        ]);
    }

    private function delivery(mixed $startedAt): void
    {
        RealEstateOutboundDelivery::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'live-readiness-'.$startedAt),
            'inbound_whatsapp_message_id' => hash('sha256', 'inbound-'.$startedAt),
            'session_id' => 'whatsapp:35:905550009999',
            'phone_number' => '905550009999',
            'answer_hash' => hash('sha256', 'Canlı outbound testi.'),
            'answer' => 'Canlı outbound testi.',
            'status' => 'sending',
            'attempts' => 1,
            'sending_started_at' => $startedAt,
        ]);
    }
}
