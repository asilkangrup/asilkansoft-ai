<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundSafetyEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOutboundSafetyHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_exposes_exact_scope_outbound_safety_readiness_and_telemetry(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'outbound-safety-health@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'outbound-safety-health',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
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

        $conversation = ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'outbound-safety-health',
            'whatsapp_number' => '905551110000',
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        RealEstateOutboundSafetyEvent::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'conversation_control_id' => $conversation->id,
            'real_estate_profile_id' => null,
            'event_key' => hash('sha256', 'safety-health-event'),
            'inbound_message_id_hash' => hash('sha256', 'wamid-safety-health'),
            'response_hash' => hash('sha256', 'unsafe-response'),
            'action' => 'replaced',
            'recipient_role' => 'seller',
            'reasons' => [
                'unsupported_transaction_certainty',
                'unsupported_official_verification',
            ],
            'safe_response_version' => 'real-estate-outbound-safety-v1',
            'detected_at' => now(),
        ]);

        // A foreign-scope row must never contaminate isolated health telemetry.
        RealEstateOutboundSafetyEvent::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => 35,
            'conversation_control_id' => null,
            'real_estate_profile_id' => null,
            'event_key' => hash('sha256', 'foreign-safety-health-event'),
            'inbound_message_id_hash' => hash('sha256', 'foreign-wamid'),
            'response_hash' => hash('sha256', 'foreign-response'),
            'action' => 'replaced',
            'recipient_role' => 'investor',
            'reasons' => ['confidential_seller_floor'],
            'safe_response_version' => 'real-estate-outbound-safety-v1',
            'detected_at' => now(),
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response->assertOk()
            ->assertJsonPath('checks.outbound_safety_firewall_ready', true)
            ->assertJsonPath('outbound_safety_telemetry_24h.events', 1)
            ->assertJsonPath('outbound_safety_telemetry_24h.replaced', 1)
            ->assertJsonPath('outbound_safety_telemetry_24h.unsupported_transaction_certainty', 1)
            ->assertJsonPath('outbound_safety_telemetry_24h.unsupported_official_verification', 1)
            ->assertJsonPath('outbound_safety_telemetry_24h.confidential_seller_floor', 0)
            ->assertJsonPath('outbound_safety_telemetry_24h.unique_conversations', 1);

        $this->assertNotContains(
            'outbound_safety_firewall_ready',
            $response->json('blocking_checks', [])
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }
}
