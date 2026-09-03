<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use App\Services\RealEstateOutboundDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateUnreachableRecipientHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_unreachable_recipient_is_observable_without_blocking_live_traffic(): void
    {
        $this->seedReadyScope();

        RealEstateOutboundDelivery::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'unreachable-health-1'),
            'inbound_whatsapp_message_id' => 'unreachable-health-inbound-1',
            'session_id' => 'whatsapp:35:905550000099',
            'phone_number' => '905550000099',
            'answer_hash' => hash('sha256', 'Gönderilmemiş test cevabı.'),
            'answer' => 'Gönderilmemiş test cevabı.',
            'status' => 'abandoned',
            'attempts' => 1,
            'sending_started_at' => now()->subMinute(),
            'last_error' => RealEstateOutboundDeliveryService::RECIPIENT_UNREACHABLE_MARKER,
        ]);

        $response = $this->getJson('/api/real-estate/health');

        $response
            ->assertOk()
            ->assertJsonPath('ready_for_live_traffic', true)
            ->assertJsonPath('checks.unresolved_outbound_deliveries', 0)
            ->assertJsonPath('outbound_reconciliation.uncertain', 0)
            ->assertJsonPath('outbound_reconciliation.recipient_unreachable_abandoned', 1)
            ->assertJsonPath('outbound_reconciliation.blocking', 0);

        $payload = json_encode($response->json());

        $this->assertIsString($payload);
        $this->assertStringNotContainsString('905550000099', $payload);
        $this->assertStringNotContainsString('Gönderilmemiş test cevabı.', $payload);
        $this->assertNotContains(
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
            'email' => 'unreachable-health@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'unreachable-health',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString(
                    'real-estate-unreachable-health-secret-123456789'
                ),
            ],
        ]);

        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-unreachable-health-key',
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
}
