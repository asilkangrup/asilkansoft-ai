<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_ready_only_when_all_isolated_production_checks_pass(): void
    {
        $this->seedIdentity();
        $this->fakeEvolutionState('open');

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-test-ready-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            // Deliberately stale: live Evolution state must win.
            'whatsapp_status' => 'connecting',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('isolated', true)
            ->assertJsonPath('ready_for_live_traffic', true)
            ->assertJsonPath('whatsapp_connection_state', 'open')
            ->assertJsonPath('whatsapp_connection_source', 'evolution_live')
            ->assertJsonPath('checks.bot_identity_valid', true)
            ->assertJsonPath('checks.instance_valid', true)
            ->assertJsonPath('checks.webhook_auth_configured', true)
            ->assertJsonPath('checks.durable_webhook_receipts_ready', true)
            ->assertJsonPath('checks.outbound_delivery_guard_ready', true)
            ->assertJsonPath('checks.operator_alert_queue_ready', true)
            ->assertJsonPath('checks.evidence_quality_guard_ready', true)
            ->assertJsonPath('checks.fact_consistency_guard_ready', true)
            ->assertJsonPath('checks.openai_api_key_configured', true)
            ->assertJsonPath('checks.openai_api_key_encrypted_at_rest', true)
            ->assertJsonPath('checks.whatsapp_connected', true)
            ->assertJsonPath('checks.follow_ups_disabled', true)
            ->assertJsonPath('checks.active_follow_up_records', 0)
            ->assertJsonPath('checks.unresolved_outbound_deliveries', 0)
            ->assertJsonPath('operator_alert_telemetry.open', 0)
            ->assertJsonPath('operator_alert_telemetry.critical_open', 0)
            ->assertJsonPath('blocking_checks', []);
    }

    public function test_health_names_only_the_real_blockers_before_user_finishes_key_and_qr(): void
    {
        $this->seedIdentity();
        $this->fakeEvolutionState('close');

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            // Deliberately stale in the opposite direction.
            'whatsapp_status' => 'connected',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', false)
            ->assertJsonPath('whatsapp_connection_state', 'close')
            ->assertJsonPath('whatsapp_connection_source', 'evolution_live')
            ->assertJsonPath('checks.bot_identity_valid', true)
            ->assertJsonPath('checks.instance_valid', true)
            ->assertJsonPath('checks.webhook_auth_configured', true)
            ->assertJsonPath('checks.durable_webhook_receipts_ready', true)
            ->assertJsonPath('checks.outbound_delivery_guard_ready', true)
            ->assertJsonPath('checks.operator_alert_queue_ready', true)
            ->assertJsonPath('checks.evidence_quality_guard_ready', true)
            ->assertJsonPath('checks.fact_consistency_guard_ready', true)
            ->assertJsonPath('checks.openai_api_key_configured', false)
            ->assertJsonPath('checks.openai_api_key_encrypted_at_rest', false)
            ->assertJsonPath('checks.whatsapp_connected', false)
            ->assertJsonPath('checks.follow_ups_disabled', true)
            ->assertJsonPath('checks.active_follow_up_records', 0)
            ->assertJsonPath('checks.unresolved_outbound_deliveries', 0)
            ->assertJsonPath('operator_alert_telemetry.open', 0);

        $blocking = $response->json('blocking_checks');

        $this->assertContains('openai_api_key_configured', $blocking);
        $this->assertContains('openai_api_key_encrypted_at_rest', $blocking);
        $this->assertContains('whatsapp_connected', $blocking);
        $this->assertNotContains('operator_alert_queue_ready', $blocking);
        $this->assertNotContains('evidence_quality_guard_ready', $blocking);
        $this->assertNotContains('fact_consistency_guard_ready', $blocking);
        $this->assertNotContains('outbound_delivery_guard_ready', $blocking);
        $this->assertNotContains('unresolved_outbound_deliveries', $blocking);
        $this->assertNotContains('follow_ups_disabled', $blocking);
        $this->assertNotContains('active_follow_up_records', $blocking);
    }

    public function test_health_fails_closed_when_live_evolution_state_is_unreachable(): void
    {
        $this->seedIdentity();
        $this->fakeEvolutionFailure();

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-test-ready-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connected',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', false)
            ->assertJsonPath('whatsapp_connection_state', 'unreachable')
            ->assertJsonPath('checks.whatsapp_connected', false)
            ->assertJsonPath('checks.fact_consistency_guard_ready', true);

        $this->assertContains(
            'whatsapp_connected',
            $response->json('blocking_checks')
        );
    }

    public function test_health_fails_closed_when_evolution_configuration_is_missing(): void
    {
        $this->seedIdentity();
        config([
            'evolution.url' => null,
            'evolution.api_key' => null,
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-test-ready-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connected',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', false)
            ->assertJsonPath('whatsapp_connection_state', 'unconfigured')
            ->assertJsonPath('checks.whatsapp_connected', false);

        $this->assertContains(
            'whatsapp_connected',
            $response->json('blocking_checks')
        );
    }

    private function seedIdentity(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'readiness@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-readiness',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString(
                    'real-estate-readiness-webhook-secret-123456789'
                ),
            ],
        ]);
    }

    private function fakeEvolutionState(string $state): void
    {
        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        Http::fake([
            'https://evolution.example.test/instance/connectionState/emlak-ai-35' =>
                Http::response([
                    'instance' => [
                        'state' => $state,
                    ],
                ], 200),
        ]);
    }

    private function fakeEvolutionFailure(): void
    {
        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        Http::fake([
            'https://evolution.example.test/instance/connectionState/emlak-ai-35' =>
                Http::response(['error' => 'down'], 503),
        ]);
    }
}
