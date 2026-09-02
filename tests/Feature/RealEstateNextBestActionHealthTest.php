<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateNextBestActionHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_exposes_isolated_next_action_readiness_and_privacy_safe_telemetry(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'nba-health',
            'whatsapp_number' => '905551111111',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
        $profile = RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => $bot->id,
                'profile_type' => 'seller',
                'data' => [],
                'valuation' => [],
                'completeness_score' => 0,
                'confidence_score' => 0,
            ])
        );

        RealEstateNextBestActionEvent::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'conversation_control_id' => $conversation->id,
            'real_estate_profile_id' => $profile->id,
            'state_key' => hash('sha256', 'nba-health-event'),
            'action_code' => 'complete_property_verification',
            'priority' => 'high',
            'stage' => 'qualified',
            'reason_codes' => ['verification_blocked'],
            'metadata' => [
                'profile_type' => 'seller',
                'blocking' => true,
                'match_count' => 0,
                'contains_raw_customer_message' => false,
                'contains_contact_details' => false,
                'contains_private_seller_floor' => false,
                'follow_up_scheduling_allowed' => false,
            ],
            'occurred_at' => now(),
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response->assertOk()
            ->assertJsonPath('checks.next_best_action_orchestrator_ready', true)
            ->assertJsonPath('next_best_action_telemetry_24h.events', 1)
            ->assertJsonPath('next_best_action_telemetry_24h.blocking', 1)
            ->assertJsonPath('next_best_action_telemetry_24h.high_priority', 1)
            ->assertJsonPath('next_best_action_telemetry_24h.verification_actions', 1)
            ->assertJsonPath('next_best_action_telemetry_24h.valuation_actions', 0)
            ->assertJsonPath('next_best_action_telemetry_24h.match_review_actions', 0)
            ->assertJsonPath('next_best_action_telemetry_24h.unique_profiles', 1)
            ->assertJsonPath('next_best_action_telemetry_24h.unique_conversations', 1);

        $this->assertNotContains(
            'next_best_action_orchestrator_ready',
            $response->json('blocking_checks', [])
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'nba-health@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'nba-health-isolated',
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
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }
}
