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
use App\Services\RealEstateWebhookReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RealEstateInboundRecoveryFairnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_failed_turn_is_not_starved_by_old_irrelevant_ignored_receipts(): void
    {
        $this->seedBot();

        for ($index = 1; $index <= 25; $index++) {
            RealEstateWebhookReceipt::query()->forceCreate([
                'user_id' => 40,
                'organization_id' => 37,
                'ai_bot_id' => 35,
                'instance' => 'emlak-ai-35',
                'event' => 'messages.upsert',
                'receipt_key' => hash('sha256', 'old-orphan-'.$index),
                'whatsapp_message_id' => 'old-orphan-'.$index,
                'phone_number' => '905550099999',
                'status' => 'ignored',
                'attempts' => 1,
                'processed_at' => now()->subMinutes(20),
            ]);
        }

        $conversation = $this->conversation('905550000101');
        $this->customerMessage($conversation, 'current-failed');
        $receipt = $this->receipt(
            messageId: 'current-failed',
            status: 'failed',
            phoneNumber: '905550000101',
            lastError: 'temporary provider failure',
        );

        $service = $this->successfulRecoveryService(
            conversation: $conversation,
            expectedInboundId: 'current-failed',
            expectedPhone: '905550000101',
        );

        $stats = $service->recover(limit: 5, failedAgeSeconds: 60);

        $this->assertSame(1, $stats['candidates']);
        $this->assertSame(1, $stats['replied']);
        $this->assertSame('replied', $receipt->fresh()->status);
    }

    public function test_latest_original_ignored_debounce_receipt_can_be_claimed_and_replied(): void
    {
        $this->seedBot();
        $conversation = $this->conversation('905550000102');
        $this->customerMessage($conversation, 'ignored-final-turn');
        $receipt = $this->receipt(
            messageId: 'ignored-final-turn',
            status: 'ignored',
            phoneNumber: '905550000102',
            lastError: null,
        );

        $service = $this->successfulRecoveryService(
            conversation: $conversation,
            expectedInboundId: 'ignored-final-turn',
            expectedPhone: '905550000102',
        );

        $stats = $service->recover(limit: 5, failedAgeSeconds: 60);

        $this->assertSame(1, $stats['candidates']);
        $this->assertSame(1, $stats['replied']);
        $this->assertSame('replied', $receipt->fresh()->status);
        $this->assertGreaterThanOrEqual(2, (int) $receipt->fresh()->attempts);
    }

    public function test_terminal_ignored_recovery_decision_is_not_scanned_again(): void
    {
        $this->seedBot();
        $conversation = $this->conversation('905550000103');
        $this->customerMessage($conversation, 'terminal-ignored');
        $receipt = $this->receipt(
            messageId: 'terminal-ignored',
            status: 'ignored',
            phoneNumber: '905550000103',
            lastError: 'superseded_by_newer_customer_message',
        );

        $memory = Mockery::mock(MemoryService::class);
        $memory->shouldNotReceive('openAIMesajlariHazirla');
        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $openAi->shouldNotReceive('cevapVer');
        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
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
        $this->assertSame('ignored', $receipt->fresh()->status);
        $this->assertSame(
            'superseded_by_newer_customer_message',
            $receipt->fresh()->last_error
        );
    }

    public function test_recovery_source_prioritizes_newest_and_transaction_locks_claim(): void
    {
        $source = file_get_contents(
            app_path('Services/RealEstateInboundRecoveryService.php')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString("->orderByDesc('id')", $source);
        $this->assertStringContainsString("->whereNull('last_error')", $source);
        $this->assertStringContainsString("->whereIn('whatsapp_message_id', \$recoverableMessageIds)", $source);
        $this->assertStringContainsString('claimForRecovery', $source);
        $this->assertStringContainsString('lockForUpdate()', $source);
        $this->assertStringNotContainsString(
            "'status' => 'failed',\n                'last_error' => 'safe_unanswered_recovery'",
            $source
        );
        $this->assertStringNotContainsString('follow_up_enabled = true', $source);
    }

    private function successfulRecoveryService(
        ConversationControl $conversation,
        string $expectedInboundId,
        string $expectedPhone,
    ): RealEstateInboundRecoveryService {
        $memory = Mockery::mock(MemoryService::class);
        $memory->shouldReceive('openAIMesajlariHazirla')
            ->once()
            ->with(40, $conversation->session_id, 20)
            ->andReturn([['role' => 'user', 'content' => 'Merhaba']]);

        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $openAi->shouldReceive('cevapVer')
            ->once()
            ->andReturn('Mesajınızı aldım, gayrimenkul konusunda yardımcı olabilirim.');

        $delivery = new RealEstateOutboundDelivery([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'inbound_message_id' => $expectedInboundId,
            'session_id' => $conversation->session_id,
            'phone_number' => $expectedPhone,
            'status' => 'sent',
            'answer' => 'Mesajınızı aldım, gayrimenkul konusunda yardımcı olabilirim.',
        ]);

        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
        $outbound->shouldReceive('deliver')
            ->once()
            ->withArgs(fn ($bot, $instance, $inboundId, $sessionId, $phone, $answer): bool =>
                (int) $bot->id === 35
                && $instance === 'emlak-ai-35'
                && $inboundId === $expectedInboundId
                && $sessionId === $conversation->session_id
                && $phone === $expectedPhone
                && $answer !== ''
            )
            ->andReturn([
                'delivery' => $delivery,
                'state' => 'sent',
                'sent_now' => true,
                'answer' => $delivery->answer,
                'whatsapp_message_id' => 'provider-'.$expectedInboundId,
            ]);
        $outbound->shouldReceive('persistAssistantMessage')
            ->once()
            ->with($delivery)
            ->andReturn(null);
        $outbound->shouldReceive('consumeTrialOnce')
            ->once()
            ->andReturn(true);

        return new RealEstateInboundRecoveryService(
            app(RealEstateIsolationService::class),
            $memory,
            $openAi,
            app(RealEstateWebhookReceiptService::class),
            $outbound,
        );
    }

    private function seedBot(): AiBot
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI Recovery Fairness',
            'email' => 'recovery-fairness@example.test',
            'password' => Hash::make('test'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI Recovery Fairness',
            'slug' => 'emlak-ai-recovery-fairness',
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

    private function conversation(string $phone): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'whatsapp:35:'.$phone,
            'whatsapp_number' => $phone,
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
            'message' => 'Merhaba',
            'message_type' => 'text',
            'whatsapp_message_id' => $messageId,
            'status' => 'received',
        ]);
    }

    private function receipt(
        string $messageId,
        string $status,
        string $phoneNumber,
        ?string $lastError,
    ): RealEstateWebhookReceipt {
        return RealEstateWebhookReceipt::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'event' => 'messages.upsert',
            'receipt_key' => hash('sha256', 'emlak-ai-35|messages.upsert|'.$messageId),
            'whatsapp_message_id' => $messageId,
            'phone_number' => $phoneNumber,
            'status' => $status,
            'attempts' => 1,
            'last_error' => $lastError,
            'processing_started_at' => now()->subMinutes(3),
            'processed_at' => now()->subMinutes(2),
        ]);
    }
}
