<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\RealEstateWebhookReceipt;
use App\Models\User;
use App\Services\MemoryService;
use App\Services\RealEstateInboundRecoveryService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOpenAIService;
use App\Services\RealEstateOutboundDeliveryService;
use App\Services\RealEstateOutboundSafetyService;
use App\Services\RealEstateWebhookReceiptService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RealEstateAbandonedInboundRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_confirmed_unsent_processed_receipt_can_recover_latest_unanswered_turn(): void
    {
        $bot = $this->seedBot();
        $conversation = $this->conversation();
        $message = $this->customerMessage($conversation, 'abandoned-recover-1');
        $receipt = $this->processedReceipt('abandoned-recover-1');
        $abandoned = $this->abandonedDelivery(
            $conversation,
            'abandoned-recover-1',
            RealEstateOutboundDeliveryService::CONFIRMED_NOT_SENT_MARKER,
        );

        $memory = Mockery::mock(MemoryService::class);
        $memory->shouldReceive('openAIMesajlariHazirla')
            ->once()
            ->with(40, $conversation->session_id, 20)
            ->andReturn([['role' => 'user', 'content' => 'Fiyatı için cevap bekliyorum']]);

        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $openAi->shouldReceive('cevapVer')
            ->once()
            ->andReturn('Elbette. Taşınmaz bilgilerini not ettim; hızlı nakit için son beklentinizi paylaşır mısınız?');

        $sent = clone $abandoned;
        $sent->forceFill([
            'status' => 'sent',
            'answer' => 'Elbette. Taşınmaz bilgilerini not ettim; hızlı nakit için son beklentinizi paylaşır mısınız?',
            'whatsapp_message_id' => 'out-abandoned-recover-1',
            'sent_at' => now(),
        ]);

        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
        $outbound->shouldReceive('confirmedAbandonedForInbound')
            ->once()
            ->with('abandoned-recover-1')
            ->andReturn($abandoned);
        $outbound->shouldReceive('reopenConfirmedAbandonedForRecovery')
            ->once()
            ->withArgs(fn ($delivery, $answer): bool =>
                (int) $delivery->id === (int) $abandoned->id
                && str_contains($answer, 'hızlı nakit')
            )
            ->andReturn($abandoned->forceFill([
                'status' => 'reserved',
                'answer' => 'Elbette. Taşınmaz bilgilerini not ettim; hızlı nakit için son beklentinizi paylaşır mısınız?',
                'last_error' => null,
            ]));
        $outbound->shouldReceive('deliver')
            ->once()
            ->withArgs(fn ($actualBot, $instance, $inboundId, $sessionId, $phone, $answer): bool =>
                (int) $actualBot->id === (int) $bot->id
                && $instance === 'emlak-ai-35'
                && $inboundId === 'abandoned-recover-1'
                && $sessionId === $conversation->session_id
                && $phone === '905550000001'
                && str_contains($answer, 'hızlı nakit')
            )
            ->andReturn([
                'delivery' => $sent,
                'state' => 'sent',
                'sent_now' => true,
                'answer' => (string) $sent->answer,
                'whatsapp_message_id' => 'out-abandoned-recover-1',
            ]);
        $outbound->shouldReceive('persistAssistantMessage')->once()->with($sent)->andReturn(null);
        $outbound->shouldReceive('consumeTrialOnce')->once()->with($sent, Mockery::type(AiBot::class))->andReturn(true);

        $service = new RealEstateInboundRecoveryService(
            app(RealEstateIsolationService::class),
            $memory,
            $openAi,
            app(RealEstateWebhookReceiptService::class),
            $outbound,
        );

        $stats = $service->recover(limit: 5, failedAgeSeconds: 60);

        $this->assertSame(1, $stats['candidates']);
        $this->assertSame(1, $stats['replied']);
        $this->assertSame('replied', $receipt->fresh()->status);
        $this->assertNotNull($receipt->fresh()->replied_at);
        $this->assertSame('abandoned-recover-1', $message->whatsapp_message_id);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_processed_receipt_without_exact_confirmed_not_sent_marker_remains_terminal(): void
    {
        $this->seedBot();
        $conversation = $this->conversation();
        $this->customerMessage($conversation, 'abandoned-not-confirmed');
        $receipt = $this->processedReceipt('abandoned-not-confirmed');
        $this->abandonedDelivery(
            $conversation,
            'abandoned-not-confirmed',
            'Belirsiz ağ sonucu; otomatik tekrar yasak.',
        );

        $memory = Mockery::mock(MemoryService::class);
        $memory->shouldNotReceive('openAIMesajlariHazirla');
        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $openAi->shouldNotReceive('cevapVer');
        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
        $outbound->shouldNotReceive('confirmedAbandonedForInbound');
        $outbound->shouldNotReceive('reopenConfirmedAbandonedForRecovery');
        $outbound->shouldNotReceive('deliver');

        $service = new RealEstateInboundRecoveryService(
            app(RealEstateIsolationService::class),
            $memory,
            $openAi,
            app(RealEstateWebhookReceiptService::class),
            $outbound,
        );

        $stats = $service->recover(limit: 5, failedAgeSeconds: 60);

        $this->assertSame(0, $stats['candidates']);
        $this->assertSame(0, $stats['replied']);
        $this->assertSame('processed', $receipt->fresh()->status);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_generic_delivery_call_never_revives_abandoned_row(): void
    {
        $bot = $this->seedBot();
        $conversation = $this->conversation();
        $this->customerMessage($conversation, 'abandoned-terminal');
        $delivery = $this->abandonedDelivery(
            $conversation,
            'abandoned-terminal',
            RealEstateOutboundDeliveryService::CONFIRMED_NOT_SENT_MARKER,
        );

        $safety = Mockery::mock(RealEstateOutboundSafetyService::class);
        $safety->shouldReceive('protect')
            ->once()
            ->andReturn(['answer' => 'Yeni cevap']);
        $whatsapp = Mockery::mock(WhatsAppService::class);
        $whatsapp->shouldNotReceive('sendText');

        $service = new RealEstateOutboundDeliveryService(
            app(RealEstateIsolationService::class),
            $safety,
            $whatsapp,
        );

        $result = $service->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'abandoned-terminal',
            sessionId: $conversation->session_id,
            phoneNumber: '905550000001',
            answer: 'Yeni cevap',
        );

        $this->assertSame('abandoned', $result['state']);
        $this->assertFalse($result['sent_now']);
        $this->assertSame('abandoned', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->whatsapp_message_id);
    }

    public function test_confirmed_abandoned_row_requires_explicit_reopen_before_one_send(): void
    {
        $bot = $this->seedBot();
        $conversation = $this->conversation();
        $this->customerMessage($conversation, 'abandoned-explicit-reopen');
        $delivery = $this->abandonedDelivery(
            $conversation,
            'abandoned-explicit-reopen',
            RealEstateOutboundDeliveryService::CONFIRMED_NOT_SENT_MARKER,
        );

        $safety = Mockery::mock(RealEstateOutboundSafetyService::class);
        $safety->shouldReceive('protect')
            ->twice()
            ->andReturn(['answer' => 'Güncel ve güvenli cevap']);
        $whatsapp = Mockery::mock(WhatsAppService::class);
        $whatsapp->shouldReceive('sendText')
            ->once()
            ->with('emlak-ai-35', '905550000001', 'Güncel ve güvenli cevap')
            ->andReturn(['key' => ['id' => 'provider-one-send']]);

        $service = new RealEstateOutboundDeliveryService(
            app(RealEstateIsolationService::class),
            $safety,
            $whatsapp,
        );

        $reopened = $service->reopenConfirmedAbandonedForRecovery(
            $delivery,
            'Güncel ve güvenli cevap',
        );
        $this->assertSame('reserved', $reopened->status);
        $this->assertSame('Güncel ve güvenli cevap', $reopened->answer);

        $result = $service->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'abandoned-explicit-reopen',
            sessionId: $conversation->session_id,
            phoneNumber: '905550000001',
            answer: 'Güncel ve güvenli cevap',
        );

        $this->assertSame('sent', $result['state']);
        $this->assertTrue($result['sent_now']);
        $this->assertSame('provider-one-send', $result['delivery']->whatsapp_message_id);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    private function seedBot(): AiBot
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI Test',
            'email' => 'abandoned-recovery@example.test',
            'password' => Hash::make('test'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI Test',
            'slug' => 'abandoned-recovery-test',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $user->organizations()->syncWithoutDetaching([
            37 => [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'test-dedicated-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'ai_enabled' => true,
            'subscription_status' => 'trial',
            'trial_message_limit' => 1000,
            'trial_messages_used' => 0,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
    }

    private function conversation(): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'whatsapp:35:905550000001',
            'whatsapp_number' => '905550000001',
            'customer_name' => 'Test Müşteri',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function customerMessage(
        ConversationControl $conversation,
        string $messageId,
    ): ChatMessage {
        return ChatMessage::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => 'Fiyatı için cevap bekliyorum',
            'message_type' => 'text',
            'whatsapp_message_id' => $messageId,
            'status' => 'received',
        ]);
    }

    private function processedReceipt(string $messageId): RealEstateWebhookReceipt
    {
        return RealEstateWebhookReceipt::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'event' => 'messages.upsert',
            'receipt_key' => hash('sha256', 'emlak-ai-35|messages.upsert|'.$messageId),
            'whatsapp_message_id' => $messageId,
            'phone_number' => '905550000001',
            'status' => 'processed',
            'attempts' => 1,
            'last_error' => 'Outbound WhatsApp teslimatı doğrulanamadı; duplicate riski nedeniyle otomatik tekrar engellendi.',
            'processing_started_at' => now()->subMinutes(4),
            'processed_at' => now()->subMinutes(3),
            'replied_at' => null,
        ]);
    }

    private function abandonedDelivery(
        ConversationControl $conversation,
        string $inboundMessageId,
        string $lastError,
    ): RealEstateOutboundDelivery {
        return RealEstateOutboundDelivery::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'emlak-ai-35|'.$inboundMessageId),
            'inbound_whatsapp_message_id' => $inboundMessageId,
            'session_id' => $conversation->session_id,
            'phone_number' => '905550000001',
            'answer_hash' => hash('sha256', 'Eski yoğunluk cevabı'),
            'answer' => 'Eski yoğunluk cevabı',
            'status' => 'abandoned',
            'attempts' => 1,
            'sending_started_at' => now()->subMinutes(5),
            'whatsapp_message_id' => null,
            'sent_at' => null,
            'last_error' => $lastError,
        ]);
    }
}
