<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use App\Services\MemoryService;
use App\Services\RealEstateOutboundDeliveryService;
use App\Services\RealEstateWhatsAppInboundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateOutboundDeliveryGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_inbound_message_can_send_only_one_whatsapp_reply(): void
    {
        $bot = $this->seedScope();
        Http::fake([
            '*' => Http::response(['key' => ['id' => 'outbound-wa-1']], 200),
        ]);

        $service = app(RealEstateOutboundDeliveryService::class);

        $first = $service->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'inbound-wa-1',
            sessionId: 'whatsapp:35:905550000001',
            phoneNumber: '905550000001',
            answer: 'İlk ve kalıcı cevap.',
        );

        $second = $service->deliver(
            bot: $bot->fresh(),
            instance: 'emlak-ai-35',
            inboundMessageId: 'inbound-wa-1',
            sessionId: 'whatsapp:35:905550000001',
            phoneNumber: '905550000001',
            answer: 'Retry sırasında üretilmiş farklı cevap gönderilmemeli.',
        );

        $this->assertTrue($first['sent_now']);
        $this->assertFalse($second['sent_now']);
        $this->assertSame('sent', $second['state']);
        $this->assertSame('İlk ve kalıcı cevap.', $second['answer']);
        $this->assertSame(1, RealEstateOutboundDelivery::query()->count());
        $this->assertSame(1, RealEstateOutboundDelivery::query()->value('attempts'));
        Http::assertSentCount(1);

        $service->persistAssistantMessage($first['delivery']->fresh());
        $service->persistAssistantMessage($second['delivery']->fresh());

        $this->assertSame(1, ChatMessage::query()
            ->where('user_id', 40)
            ->where('organization_id', 37)
            ->where('ai_bot_id', 35)
            ->where('role', 'assistant')
            ->count());

        $this->assertTrue($service->consumeTrialOnce($first['delivery']->fresh(), $bot->fresh()));
        $this->assertFalse($service->consumeTrialOnce($first['delivery']->fresh(), $bot->fresh()));
        $this->assertSame(1, (int) $bot->fresh()->trial_messages_used);
    }

    public function test_uncertain_network_delivery_is_quarantined_instead_of_automatically_retried(): void
    {
        $bot = $this->seedScope();
        Http::fake([
            '*' => Http::response(['message' => 'gateway timeout'], 504),
        ]);

        $service = app(RealEstateOutboundDeliveryService::class);

        $first = $service->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'inbound-uncertain-1',
            sessionId: 'whatsapp:35:905550000002',
            phoneNumber: '905550000002',
            answer: 'Bu cevap ağ sınırına ulaştı.',
        );

        $second = $service->deliver(
            bot: $bot->fresh(),
            instance: 'emlak-ai-35',
            inboundMessageId: 'inbound-uncertain-1',
            sessionId: 'whatsapp:35:905550000002',
            phoneNumber: '905550000002',
            answer: 'Bu ikinci cevap asla gönderilmemeli.',
        );

        $this->assertSame('uncertain', $first['state']);
        $this->assertSame('uncertain', $second['state']);
        $this->assertFalse($first['sent_now']);
        $this->assertFalse($second['sent_now']);
        $this->assertSame(1, RealEstateOutboundDelivery::query()->count());
        $this->assertSame(1, RealEstateOutboundDelivery::query()->value('attempts'));
        Http::assertSentCount(1);
    }

    public function test_isolated_inbound_memory_is_idempotent_by_whatsapp_message_id(): void
    {
        $this->seedScope();
        $memory = app(MemoryService::class);
        $context = [
            'type' => 'text',
            'message_id' => 'inbound-memory-1',
            'instance_name' => 'emlak-ai-35',
        ];

        $first = $memory->mesajKaydet(
            userId: 40,
            aiBotId: 35,
            sessionId: 'whatsapp:35:905550000003',
            role: 'user',
            message: 'Marmaris Hisarönü tarafında arsam var.',
            senderType: 'customer',
            mediaContext: $context,
        );

        $second = $memory->mesajKaydet(
            userId: 40,
            aiBotId: 35,
            sessionId: 'whatsapp:35:905550000003',
            role: 'user',
            message: 'Retry kopyası.',
            senderType: 'customer',
            mediaContext: $context,
        );

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($second->wasRecentlyCreated);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Marmaris Hisarönü tarafında arsam var.', $second->message);
        $this->assertSame(1, ChatMessage::query()
            ->where('user_id', 40)
            ->where('organization_id', 37)
            ->where('ai_bot_id', 35)
            ->where('whatsapp_message_id', 'inbound-memory-1')
            ->count());
    }

    public function test_inbound_without_message_id_is_ignored_before_ai_or_delivery(): void
    {
        $this->seedScope();
        Http::fake();

        $result = app(RealEstateWhatsAppInboundService::class)->process([
            'event' => 'messages.upsert',
            'instance' => 'emlak-ai-35',
            'data' => [
                'key' => [
                    'fromMe' => false,
                    'remoteJid' => '905550000004@s.whatsapp.net',
                ],
                'message' => [
                    'conversation' => 'Merhaba',
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['ignored']);
        $this->assertSame('missing_message_id', $result['reason']);
        $this->assertSame(0, RealEstateOutboundDelivery::query()->count());
        $this->assertSame(0, ChatMessage::query()->count());
        Http::assertNothingSent();
    }

    public function test_uncertain_delivery_can_be_abandoned_without_resending(): void
    {
        $this->seedScope();
        Http::fake();

        $delivery = RealEstateOutboundDelivery::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'emlak-ai-35|manual-resolution-1'),
            'inbound_whatsapp_message_id' => 'manual-resolution-1',
            'session_id' => 'whatsapp:35:905550000005',
            'phone_number' => '905550000005',
            'answer_hash' => hash('sha256', 'Belirsiz teslim edilmiş cevap.'),
            'answer' => 'Belirsiz teslim edilmiş cevap.',
            'status' => 'uncertain',
            'attempts' => 1,
            'sending_started_at' => now(),
        ]);

        $exit = Artisan::call('real-estate:resolve-outbound', [
            'id' => $delivery->id,
            'resolution' => 'abandoned',
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('abandoned', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->sent_at);
        Http::assertNothingSent();
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'outbound-guard@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'outbound-guard',
            'status' => 'active',
        ]);

        $bot = AiBot::query()->forceCreate([
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

        foreach (range(1, 5) as $suffix) {
            $number = '90555000000'.$suffix;
            ConversationControl::query()->create([
                'user_id' => 40,
                'organization_id' => 37,
                'ai_bot_id' => 35,
                'session_id' => 'whatsapp:35:'.$number,
                'whatsapp_number' => $number,
                'lead_status' => 'new',
                'next_follow_up_at' => null,
                'human_takeover' => false,
            ]);
        }

        return $bot;
    }
}
