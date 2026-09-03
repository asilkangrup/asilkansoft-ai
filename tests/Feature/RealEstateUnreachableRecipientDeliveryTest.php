<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use App\Services\RealEstateOutboundDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateUnreachableRecipientDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_evolution_exists_false_is_terminal_abandoned_not_uncertain(): void
    {
        $bot = $this->seedScope('905550000091');

        Http::fake([
            '*' => Http::response([
                'status' => 400,
                'error' => 'Bad Request',
                'response' => [
                    'message' => [[
                        'jid' => '905550000091@s.whatsapp.net',
                        'exists' => false,
                        'number' => '905550000091',
                    ]],
                ],
            ], 400),
        ]);

        $service = app(RealEstateOutboundDeliveryService::class);
        $result = $service->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'unreachable-inbound-1',
            sessionId: 'whatsapp:35:905550000091',
            phoneNumber: '905550000091',
            answer: 'Bu cevap yalnız geçerli WhatsApp alıcısına gitmelidir.',
        );

        $delivery = $result['delivery']->fresh();

        $this->assertSame('abandoned', $result['state']);
        $this->assertFalse($result['sent_now']);
        $this->assertSame(
            RealEstateOutboundDeliveryService::RECIPIENT_UNREACHABLE_MARKER,
            $delivery->last_error,
        );
        $this->assertNull($delivery->whatsapp_message_id);
        $this->assertNull($delivery->sent_at);
        $this->assertSame(1, (int) $delivery->attempts);

        // This terminal marker must never enter the operator-confirmed
        // not-sent auto-recovery path.
        $this->assertNull(
            $service->confirmedAbandonedForInbound('unreachable-inbound-1')
        );

        $second = $service->deliver(
            bot: $bot->fresh(),
            instance: 'emlak-ai-35',
            inboundMessageId: 'unreachable-inbound-1',
            sessionId: 'whatsapp:35:905550000091',
            phoneNumber: '905550000091',
            answer: 'Bu ikinci cevap asla gönderilmemeli.',
        );

        $this->assertSame('abandoned', $second['state']);
        $this->assertFalse($second['sent_now']);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_provider_failure_remains_uncertain_for_duplicate_safety(): void
    {
        $bot = $this->seedScope('905550000092');

        Http::fake([
            '*' => Http::response([
                'message' => 'gateway timeout',
            ], 504),
        ]);

        $result = app(RealEstateOutboundDeliveryService::class)->deliver(
            bot: $bot,
            instance: 'emlak-ai-35',
            inboundMessageId: 'ambiguous-inbound-1',
            sessionId: 'whatsapp:35:905550000092',
            phoneNumber: '905550000092',
            answer: 'Belirsiz ağ hatasında duplicate güvenliği korunmalıdır.',
        );

        $delivery = $result['delivery']->fresh();

        $this->assertSame('uncertain', $result['state']);
        $this->assertFalse($result['sent_now']);
        $this->assertNotSame(
            RealEstateOutboundDeliveryService::RECIPIENT_UNREACHABLE_MARKER,
            $delivery->last_error,
        );
        $this->assertNull($delivery->whatsapp_message_id);
        $this->assertNull($delivery->sent_at);
        Http::assertSentCount(1);
    }

    private function seedScope(string $number): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'unreachable-recipient@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'unreachable-recipient',
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
            'whatsapp_status' => 'connected',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);

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

        return $bot;
    }
}
