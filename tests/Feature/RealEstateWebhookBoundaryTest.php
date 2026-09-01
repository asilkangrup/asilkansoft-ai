<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateWebhookBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_real_estate_instance_is_rejected_on_generic_webhook(): void
    {
        $this->seedScope();

        $this->postJson('/api/whatsapp/webhook', [
            'event' => 'messages.update',
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => ['id' => 'boundary-test-message'],
                'update' => ['status' => 4],
            ],
        ])->assertStatus(403);
    }

    public function test_production_real_estate_instance_still_requires_signature_on_dedicated_webhook(): void
    {
        $this->seedScope();

        $this->postJson('/api/real-estate/whatsapp/webhook', [
            'event' => 'messages.update',
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => ['id' => 'boundary-test-message'],
                'update' => ['status' => 4],
            ],
        ])->assertStatus(401);
    }

    private function seedScope(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'boundary@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'boundary-real-estate',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString('boundary-test-secret-123456789'),
            ],
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'status' => 'active',
            'ai_enabled' => true,
            'subscription_status' => 'trial',
            'trial_message_limit' => 1000,
            'trial_messages_used' => 0,
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
    }
}
