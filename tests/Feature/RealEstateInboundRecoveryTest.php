<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateOutboundDelivery;
use App\Models\RealEstateWebhookReceipt;
use App\Services\MemoryService;
use App\Services\RealEstateInboundRecoveryService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOpenAIService;
use App\Services\RealEstateOutboundDeliveryService;
use App\Services\RealEstateWebhookReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RealEstateInboundRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_latest_customer_turn_is_recovered_as_a_reply_not_a_follow_up(): void
    {
        $this->seedBot();
        $conversation = $this->conversation();
        $message = $this->customerMessage($conversation, 'recover-1');
        $receipt = $this->failedReceipt('recover-1');

        $memory = Mockery::mock(MemoryService::class);
        $memory->shouldReceive('openAIMesajlariHazirla')
            ->once()
            ->with(40, $conversation->session_id, 20)
            ->andReturn([['role'=>'user','content'=>'Merhaba']]);

        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $openAi->shouldReceive('cevapVer')
            ->once()
            ->andReturn('Merhaba, gayrimenkulünüzle ilgili yardımcı olabilirim.');

        $delivery = new RealEstateOutboundDelivery([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'instance'=>'emlak-ai-35','inbound_message_id'=>'recover-1',
            'session_id'=>$conversation->session_id,'phone_number'=>'905550000001',
            'status'=>'sent','answer'=>'Merhaba, gayrimenkulünüzle ilgili yardımcı olabilirim.',
        ]);

        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
        $outbound->shouldReceive('deliver')
            ->once()
            ->withArgs(fn ($bot, $instance, $inboundId, $sessionId, $phone, $answer): bool =>
                (int) $bot->id === 35
                && $instance === 'emlak-ai-35'
                && $inboundId === 'recover-1'
                && $sessionId === $conversation->session_id
                && $phone === '905550000001'
                && $answer !== ''
            )
            ->andReturn([
                'delivery'=>$delivery,
                'state'=>'sent',
                'sent_now'=>true,
                'answer'=>$delivery->answer,
                'whatsapp_message_id'=>'out-recover-1',
            ]);
        $outbound->shouldReceive('persistAssistantMessage')->once()->with($delivery)->andReturn(null);
        $outbound->shouldReceive('consumeTrialOnce')->once()->andReturn(true);

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
        $this->assertSame('recover-1', $message->whatsapp_message_id);
    }

    public function test_recovery_never_answers_an_old_turn_after_a_newer_customer_message(): void
    {
        $this->seedBot();
        $conversation = $this->conversation();
        $this->customerMessage($conversation, 'recover-old');
        $this->customerMessage($conversation, 'recover-new');
        $receipt = $this->failedReceipt('recover-old');

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

        $this->assertSame(1, $stats['superseded']);
        $this->assertSame('ignored', $receipt->fresh()->status);
        $this->assertSame('superseded_by_newer_customer_message', $receipt->fresh()->last_error);
    }

    public function test_foreign_receipts_never_enter_isolated_recovery(): void
    {
        $this->seedBot();

        RealEstateWebhookReceipt::query()->forceCreate([
            'user_id'=>1,'organization_id'=>1,'ai_bot_id'=>1,
            'instance'=>'gulten-sirketi-1','event'=>'messages.upsert',
            'receipt_key'=>hash('sha256', 'foreign-receipt'),
            'whatsapp_message_id'=>'foreign-message','phone_number'=>'905550000099',
            'status'=>'failed','attempts'=>1,
            'processing_started_at'=>now()->subMinutes(3),
            'processed_at'=>now()->subMinutes(2),
        ]);

        $memory = Mockery::mock(MemoryService::class);
        $openAi = Mockery::mock(RealEstateOpenAIService::class);
        $outbound = Mockery::mock(RealEstateOutboundDeliveryService::class);
        $memory->shouldNotReceive('openAIMesajlariHazirla');
        $openAi->shouldNotReceive('cevapVer');
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
    }

    public function test_recovery_is_scheduled_as_inbound_watchdog_not_follow_up(): void
    {
        $routes = file_get_contents(base_path('routes/console.php'));
        $command = file_get_contents(app_path('Console/Commands/RecoverRealEstateInbound.php'));
        $service = file_get_contents(app_path('Services/RealEstateInboundRecoveryService.php'));

        $this->assertIsString($routes);
        $this->assertIsString($command);
        $this->assertIsString($service);
        $this->assertStringContainsString(
            "Schedule::command('real-estate:recover-inbound --limit=5 --age=90')",
            $routes
        );
        $this->assertStringContainsString("'automatic_follow_up' => false", $command);
        $this->assertStringContainsString("->where('user_id', RealEstateIsolationService::USER_ID)", $service);
        $this->assertStringContainsString("->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)", $service);
        $this->assertStringContainsString("->where('ai_bot_id', RealEstateIsolationService::BOT_ID)", $service);
        $this->assertStringContainsString("->where('instance', RealEstateIsolationService::INSTANCE)", $service);
        $this->assertStringContainsString("|| (bool) \$bot->follow_up_enabled", $service);
        $this->assertStringContainsString("|| (bool) \$bot->second_follow_up_enabled", $service);
    }

    private function seedBot(): AiBot
    {
        return AiBot::query()->forceCreate([
            'id'=>35,
            'user_id'=>40,
            'name'=>'Emlak AI',
            'company_name'=>'Asilkan Gayrimenkul',
            'openai_model'=>'gpt-5-mini',
            'openai_api_key'=>'test-dedicated-key',
            'status'=>'active',
            'business_sector'=>'real_estate',
            'lead_scoring_profile'=>'real_estate',
            'whatsapp_instance'=>'emlak-ai-35',
            'ai_enabled'=>true,
            'subscription_status'=>'trial',
            'trial_message_limit'=>1000,
            'trial_messages_used'=>0,
            'follow_up_enabled'=>false,
            'second_follow_up_enabled'=>false,
        ]);
    }

    private function conversation(): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>40,
            'organization_id'=>37,
            'ai_bot_id'=>35,
            'session_id'=>'whatsapp:35:905550000001',
            'whatsapp_number'=>'905550000001',
            'customer_name'=>'Test Müşteri',
            'tags'=>[],
            'lead_status'=>'new',
            'lead_score'=>0,
            'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,
            'human_takeover'=>false,
        ]);
    }

    private function customerMessage(
        ConversationControl $conversation,
        string $messageId,
    ): ChatMessage {
        return ChatMessage::query()->forceCreate([
            'user_id'=>40,
            'organization_id'=>37,
            'ai_bot_id'=>35,
            'session_id'=>$conversation->session_id,
            'role'=>'user',
            'sender_type'=>'customer',
            'message'=>'Merhaba',
            'message_type'=>'text',
            'whatsapp_message_id'=>$messageId,
            'status'=>'received',
        ]);
    }

    private function failedReceipt(string $messageId): RealEstateWebhookReceipt
    {
        return RealEstateWebhookReceipt::query()->forceCreate([
            'user_id'=>40,
            'organization_id'=>37,
            'ai_bot_id'=>35,
            'instance'=>'emlak-ai-35',
            'event'=>'messages.upsert',
            'receipt_key'=>hash('sha256', 'emlak-ai-35|messages.upsert|'.$messageId),
            'whatsapp_message_id'=>$messageId,
            'phone_number'=>'905550000001',
            'status'=>'failed',
            'attempts'=>2,
            'last_error'=>'temporary failure',
            'processing_started_at'=>now()->subMinutes(3),
            'processed_at'=>now()->subMinutes(2),
        ]);
    }
}
