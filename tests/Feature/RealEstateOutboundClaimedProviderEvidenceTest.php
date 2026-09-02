<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use App\Services\RealEstateOutboundReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateOutboundClaimedProviderEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_claimed_duplicate_provider_message_is_excluded_and_unique_unclaimed_match_reconciles(): void
    {
        $bot = $this->seedScope();
        $startedAt = now()->subMinutes(3)->startOfSecond();
        $answer = 'Dosyanızı yatırımcı değerlendirmesine hazırlamak için bilgileri tamamlayalım.';
        $phone = '905550009901';

        $target = $this->createDelivery(
            inboundId: 'inbound-target',
            status: 'uncertain',
            answer: $answer,
            phoneNumber: $phone,
            sendingStartedAt: $startedAt,
        );

        $claimed = $this->createDelivery(
            inboundId: 'inbound-later',
            status: 'sent',
            answer: $answer,
            phoneNumber: $phone,
            sendingStartedAt: $startedAt->copy()->addSeconds(42),
            providerMessageId: 'provider-already-claimed',
        );

        Http::fake(function ($request) use ($startedAt, $answer, $phone) {
            $this->assertStringContainsString(
                '/chat/findMessages/emlak-ai-35',
                $request->url()
            );
            $this->assertStringNotContainsString('/message/sendText/', $request->url());

            return Http::response([
                'messages' => [
                    'records' => [
                        [
                            'key' => [
                                'id' => 'provider-target',
                                'fromMe' => true,
                                'remoteJid' => $phone.'@s.whatsapp.net',
                            ],
                            'message' => [
                                'conversation' => $answer,
                            ],
                            'messageTimestamp' => $startedAt->copy()->addSecond()->timestamp,
                        ],
                        [
                            'key' => [
                                'id' => 'provider-already-claimed',
                                'fromMe' => true,
                                'remoteJid' => $phone.'@s.whatsapp.net',
                            ],
                            'message' => [
                                'conversation' => $answer,
                            ],
                            'messageTimestamp' => $startedAt->copy()->addSeconds(42)->timestamp,
                        ],
                    ],
                ],
            ], 200);
        });

        $stats = app(RealEstateOutboundReconciliationService::class)
            ->reconcile(limit: 10, ageSeconds: 120);

        $this->assertSame(1, $stats['examined']);
        $this->assertSame(1, $stats['confirmed_sent']);
        $this->assertSame(0, $stats['quarantined']);
        $this->assertSame(0, $stats['unchanged']);

        $target->refresh();
        $claimed->refresh();

        $this->assertSame('sent', $target->status);
        $this->assertSame('provider-target', $target->whatsapp_message_id);
        $this->assertNotNull($target->sent_at);
        $this->assertNotNull($target->trial_consumed_at);
        $this->assertSame('sent', $claimed->status);
        $this->assertSame('provider-already-claimed', $claimed->whatsapp_message_id);
        $this->assertSame(1, (int) $bot->fresh()->trial_messages_used);

        $this->assertSame(1, ChatMessage::query()
            ->where('user_id', 40)
            ->where('organization_id', 37)
            ->where('ai_bot_id', 35)
            ->where('sender_type', 'ai')
            ->where('whatsapp_message_id', 'provider-target')
            ->count());

        Http::assertSentCount(1);
    }

    private function seedScope(): AiBot
    {
        config([
            'evolution.url' => 'https://evolution.example.test',
            'evolution.api_key' => 'evolution-test-key',
        ]);

        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'claimed-provider-evidence@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'claimed-provider-evidence',
            'status' => 'active',
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
            'whatsapp_status' => 'connected',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
        ]);
    }

    private function createDelivery(
        string $inboundId,
        string $status,
        string $answer,
        string $phoneNumber,
        mixed $sendingStartedAt,
        ?string $providerMessageId = null,
    ): RealEstateOutboundDelivery {
        $attributes = [
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'emlak-ai-35|'.$inboundId),
            'inbound_whatsapp_message_id' => $inboundId,
            'session_id' => 'whatsapp:35:'.$phoneNumber,
            'phone_number' => $phoneNumber,
            'answer_hash' => hash('sha256', trim($answer)),
            'answer' => trim($answer),
            'status' => $status,
            'attempts' => 1,
            'sending_started_at' => $sendingStartedAt,
        ];

        if ($providerMessageId !== null) {
            $attributes['whatsapp_message_id'] = $providerMessageId;
            $attributes['sent_at'] = $sendingStartedAt;
        }

        return RealEstateOutboundDelivery::query()->forceCreate($attributes);
    }
}
