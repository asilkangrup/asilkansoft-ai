<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\ConversationFollowUp;
use App\Models\FinanceLead;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateRuntimeIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_follow_up_records_can_never_be_activated(): void
    {
        $this->seedScope();

        $followUp = ConversationFollowUp::query()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'session_id' => 'whatsapp:35:905550000001',
            'whatsapp_number' => '905550000001',
            'last_customer_message_at' => now(),
            'is_active' => true,
        ]);

        $this->assertFalse($followUp->fresh()->is_active);
    }

    public function test_real_estate_status_update_cannot_touch_another_bot_message(): void
    {
        $this->seedScope();

        $conversation = $this->conversation('whatsapp:35:905550000002');

        $realEstateMessage = ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => 'Emlak cevabı',
            'message_type' => 'text',
            'whatsapp_message_id' => 'same-message-id',
            'status' => 'sent',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other',
            'email' => 'other-runtime@example.test',
            'password' => Hash::make('password'),
        ]);

        $otherBot = AiBot::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Bot',
            'company_name' => 'Other Company',
            'business_sector' => 'ecommerce',
            'status' => 'active',
        ]);

        $otherMessage = ChatMessage::query()->create([
            'user_id' => $otherUser->id,
            'organization_id' => null,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'other-session',
            'role' => 'assistant',
            'sender_type' => 'ai',
            'message' => 'Other reply',
            'message_type' => 'text',
            'whatsapp_message_id' => 'same-message-id',
            'status' => 'sent',
        ]);

        $result = app(RealEstateWhatsAppInboundService::class)->handleStatusUpdate([
            'event' => 'messages.update',
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => ['id' => 'same-message-id'],
                'update' => ['status' => 4],
            ],
        ]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('read', $realEstateMessage->fresh()->status);
        $this->assertSame('sent', $otherMessage->fresh()->status);
    }

    public function test_real_estate_purchase_language_never_enters_generic_order_finance_or_follow_up_pipeline(): void
    {
        $this->seedScope();

        $conversation = $this->conversation('whatsapp:35:905550000003');
        $conversation->update(['human_takeover' => true]);

        $result = app(RealEstateWhatsAppInboundService::class)->process([
            'event' => 'messages.upsert',
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => [
                    'id' => 'real-estate-buy-intent-1',
                    'fromMe' => false,
                    'remoteJid' => '905550000003@s.whatsapp.net',
                ],
                'pushName' => 'Test Investor',
                'message' => [
                    'conversation' => 'Bu arsayı almak istiyorum, fiyatı nedir?',
                ],
            ],
        ]);

        $this->assertSame('human_takeover', $result['reason']);
        $this->assertSame(0, Order::query()->where('ai_bot_id', 35)->count());
        $this->assertSame(0, FinanceLead::query()->where('ai_bot_id', 35)->count());
        $this->assertSame(0, ConversationFollowUp::query()->where('ai_bot_id', 35)->count());
        $this->assertSame(
            1,
            ChatMessage::query()
                ->where('ai_bot_id', 35)
                ->where('sender_type', 'customer')
                ->where('whatsapp_message_id', 'real-estate-buy-intent-1')
                ->count()
        );
    }

    public function test_isolation_guard_requires_exact_production_scope(): void
    {
        $bot = $this->seedScope();
        $guard = app(RealEstateIsolationService::class);

        $this->assertTrue($guard->supportsBotIdentity($bot));
        $this->assertTrue($guard->supportsProductionBot($bot));
        $this->assertTrue($guard->organizationValid());

        $bot->whatsapp_instance = 'gulten-sirketi-1';

        $this->assertFalse($guard->supportsProductionBot($bot));
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'isolated-runtime@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'isolated-runtime',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString('test-secret-1234567890'),
            ],
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'ai_enabled' => true,
            'subscription_status' => 'trial',
            'trial_message_limit' => 1000,
            'trial_messages_used' => 0,
            'whatsapp_instance' => 'emlak-ai-35',
            'whatsapp_status' => 'connecting',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
    }

    private function conversation(string $sessionId): ConversationControl
    {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $sessionId,
            'whatsapp_number' => explode(':', $sessionId)[2],
            'unread_count' => 0,
            'human_takeover' => false,
            'lead_status' => 'new',
        ]);
    }
}
