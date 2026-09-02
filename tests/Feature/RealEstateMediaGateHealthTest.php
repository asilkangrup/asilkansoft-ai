<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateMediaGateHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_health_reports_media_admission_gate_ready(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'media-gate-health@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'media-gate-health',
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
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response->assertOk()
            ->assertJsonPath('checks.media_admission_gate_ready', true)
            ->assertJsonPath('follow_up_runtime_blocked', true)
            ->assertJsonPath('checks.follow_ups_disabled', true);

        $this->assertNotContains(
            'media_admission_gate_ready',
            $response->json('blocking_checks', [])
        );
    }
}
