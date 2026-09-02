<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\User;
use App\Services\RealEstateChatBoundaryGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateChatBoundaryHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_guard_reports_customer_chat_as_dedicated_key_and_structured_research_only(): void
    {
        $snapshot = app(RealEstateChatBoundaryGuardService::class)->snapshot();

        $this->assertTrue($snapshot['ready']);
        $this->assertFalse($snapshot['customer_external_tools_allowed']);
        $this->assertSame('structured_services_only', $snapshot['research_boundary']);
        $this->assertTrue($snapshot['dedicated_openai_key_only']);
        $this->assertSame('disabled', $snapshot['follow_up_capability']);

        foreach ($snapshot['checks'] as $check) {
            $this->assertTrue($check);
        }
    }

    public function test_health_exposes_chat_boundary_without_relaxing_live_traffic_blockers(): void
    {
        $this->seedScope();

        $response = $this->getJson('/api/real-estate/health');

        $response->assertOk();
        $response->assertJsonPath('user_id', 40);
        $response->assertJsonPath('organization_id', 37);
        $response->assertJsonPath('bot_id', 35);
        $response->assertJsonPath('instance', 'emlak-ai-35');
        $response->assertJsonPath('checks.chat_research_boundary_ready', true);
        $response->assertJsonPath(
            'chat_research_boundary.customer_external_tools_allowed',
            false
        );
        $response->assertJsonPath(
            'chat_research_boundary.research_boundary',
            'structured_services_only'
        );
        $response->assertJsonPath(
            'chat_research_boundary.dedicated_openai_key_only',
            true
        );
        $response->assertJsonPath('follow_up_runtime_blocked', true);
        $response->assertJsonPath('checks.follow_ups_disabled', true);
        $response->assertJsonPath('checks.openai_api_key_configured', false);
        $response->assertJsonPath('ready_for_live_traffic', false);
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'chat-boundary-health@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'chat-boundary-health-org37',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5.4',
            'openai_api_key' => null,
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connecting',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }
}
