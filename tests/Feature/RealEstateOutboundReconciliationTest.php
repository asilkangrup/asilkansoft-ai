<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\User;
use App\Services\RealEstateOutboundReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstateOutboundReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_sending_is_confirmed_from_exact_evolution_evidence_without_resend(): void
    {
        $bot = $this->seedScope();
        $startedAt = now()->subMinutes(3)->startOfSecond();
        $answer = 'Emsal araştırmasına göre uygun yatırımcı bandını paylaşabilirim.';
        $delivery = $this->createDelivery(
            status: 'sending',
            answer: $answer,
            phoneNumber: '905550001111',
            sendingStartedAt: $startedAt,
        );

        Http::fake(function ($request) use ($startedAt, $answer) {
            $this->assertStringContainsString(
                '/chat/findMessages/emlak-ai-35',
                $request->url()
            );
            $this->assertStringNotContainsString('/message/sendText/', $request->url());

            return Http::response([
                'messages' => [
                    'records' => [[
                        'key' => [
                            'id' => 'provider-confirmed-1',
                            'fromMe' => true,
                            'remoteJid' => '905550001111@s.whatsapp.net',
                        ],
                        'message' => [
                            'conversation' => $answer,
                        ],
                        'messageTimestamp' => $startedAt->timestamp,
                    ]],
                ],
            ], 200);
        });

        $stats = app(RealEstateOutboundReconciliationService::class)
            ->reconcile(limit: 10, ageSeconds: 120);

        $this->assertSame(1, $stats['examined']);
        $this->assertSame(1, $stats['confirmed_sent']);
        $this->assertSame(0, $stats['quarantined']);
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('provider-confirmed-1', $delivery->fresh()->whatsapp_message_id);
        $this->assertNotNull($delivery->fresh()->sent_at);
        $this->assertNotNull($delivery->fresh()->trial_consumed_at);
        $this->assertSame(1, (int) $bot->fresh()->trial_messages_used);
        $this->assertSame(1, ChatMessage::query()
            ->where('user_id', 40)
            ->where('organization_id', 37)
            ->where('ai_bot_id', 35)
            ->where('sender_type', 'ai')
            ->count());
        Http::assertSentCount(1);
    }

    public function test_stale_sending_without_exact_provider_evidence_is_quarantined_not_resent(): void
    {
        $this->seedScope();
        $delivery = $this->createDelivery(
            status: 'sending',
            answer: 'Bu cevap gönderim sınırında kaldı.',
            phoneNumber: '905550001112',
            sendingStartedAt: now()->subMinutes(3),
        );

        Http::fake([
            'https://evolution.example.test/chat/findMessages/emlak-ai-35' =>
                Http::response(['messages' => ['records' => []]], 200),
        ]);

        $stats = app(RealEstateOutboundReconciliationService::class)
            ->reconcile(limit: 10, ageSeconds: 120);

        $this->assertSame(1, $stats['examined']);
        $this->assertSame(0, $stats['confirmed_sent']);
        $this->assertSame(1, $stats['quarantined']);
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->sent_at);
        $this->assertNull($delivery->fresh()->trial_consumed_at);
        $this->assertSame(0, ChatMessage::query()->count());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            str_contains($request->url(), '/chat/findMessages/emlak-ai-35')
            && ! str_contains($request->url(), '/message/sendText/')
        );
    }

    public function test_existing_uncertain_record_is_left_quarantined_when_evidence_is_ambiguous(): void
    {
        $this->seedScope();
        $delivery = $this->createDelivery(
            status: 'uncertain',
            answer: 'Aynı metin iki kez görünürse otomatik karar verilmemeli.',
            phoneNumber: '905550001113',
            sendingStartedAt: now()->subMinutes(3)->startOfSecond(),
        );
        $timestamp = $delivery->sending_started_at->timestamp;

        Http::fake([
            'https://evolution.example.test/chat/findMessages/emlak-ai-35' =>
                Http::response([
                    'messages' => [
                        'records' => [
                            [
                                'key' => [
                                    'id' => 'ambiguous-1',
                                    'fromMe' => true,
                                    'remoteJid' => '905550001113@s.whatsapp.net',
                                ],
                                'message' => [
                                    'conversation' => $delivery->answer,
                                ],
                                'messageTimestamp' => $timestamp,
                            ],
                            [
                                'key' => [
                                    'id' => 'ambiguous-2',
                                    'fromMe' => true,
                                    'remoteJid' => '905550001113@s.whatsapp.net',
                                ],
                                'message' => [
                                    'conversation' => $delivery->answer,
                                ],
                                'messageTimestamp' => $timestamp + 1,
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $stats = app(RealEstateOutboundReconciliationService::class)
            ->reconcile(limit: 10, ageSeconds: 120);

        $this->assertSame(1, $stats['examined']);
        $this->assertSame(1, $stats['unchanged']);
        $this->assertSame('uncertain', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->whatsapp_message_id);
        Http::assertSentCount(1);
    }

    public function test_fresh_sending_and_foreign_scope_are_never_reconciled(): void
    {
        $this->seedScope();
        $fresh = $this->createDelivery(
            status: 'sending',
            answer: 'Henüz normal ağ çağrısı devam ediyor.',
            phoneNumber: '905550001114',
            sendingStartedAt: now()->subSeconds(20),
        );

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'foreign-outbound-reconcile',
            'status' => 'active',
        ]);
        $foreign = RealEstateOutboundDelivery::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => 35,
            'instance' => 'emlak-ai-35',
            'delivery_key' => hash('sha256', 'foreign-reconcile'),
            'inbound_whatsapp_message_id' => 'foreign-reconcile',
            'session_id' => 'whatsapp:35:905550001115',
            'phone_number' => '905550001115',
            'answer_hash' => hash('sha256', 'Foreign answer'),
            'answer' => 'Foreign answer',
            'status' => 'sending',
            'attempts' => 1,
            'sending_started_at' => now()->subMinutes(5),
        ]);

        Http::fake();

        $stats = app(RealEstateOutboundReconciliationService::class)
            ->reconcile(limit: 10, ageSeconds: 120);

        $this->assertSame(0, $stats['examined']);
        $this->assertSame('sending', $fresh->fresh()->status);
        $this->assertSame('sending', $foreign->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_reconciliation_command_is_registered_and_scheduled_without_follow_up(): void
    {
        $this->seedScope();
        Http::fake();

        $exit = Artisan::call('real-estate:reconcile-outbound', [
            '--limit' => 5,
            '--age' => 120,
        ]);

        $this->assertSame(0, $exit);
        $source = file_get_contents(base_path('routes/console.php'));
        $this->assertIsString($source);
        $this->assertStringContainsString(
            'real-estate:reconcile-outbound --limit=10 --age=120',
            $source
        );
        $this->assertStringNotContainsString(
            'sendText',
            file_get_contents(app_path('Services/RealEstateOutboundReconciliationService.php'))
        );
        Http::assertNothingSent();
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
            'email' => 'outbound-reconcile@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'outbound-reconcile',
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
        string $status,
        string $answer,
        string $phoneNumber,
        mixed $sendingStartedAt,
    ): RealEstateOutboundDelivery {
        $inboundId = 'inbound-'.hash('sha256', $phoneNumber.$answer);

        return RealEstateOutboundDelivery::query()->forceCreate([
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
        ]);
    }
}
