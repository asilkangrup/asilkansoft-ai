<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\Organization;
use App\Models\RealEstateWebhookReceipt;
use App\Models\User;
use App\Services\RealEstateWebhookAuthService;
use App\Services\RealEstateWebhookReceiptService;
use App\Services\RealEstateWhatsAppInboundService;
use App\Services\RealEstateWhatsAppMessageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class RealEstateInboundResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_durable_receipt_blocks_completed_duplicate(): void
    {
        $this->seedScope();
        $service = app(RealEstateWebhookReceiptService::class);

        $first = $service->begin(
            instance: 'emlak-ai-35',
            event: 'messages.upsert',
            messageId: 'durable-message-1',
            phoneNumber: '905550000111',
        );

        $this->assertTrue($first['should_process']);
        $this->assertNotNull($first['receipt']);

        $service->complete($first['receipt'], [
            'success' => true,
            'ignored' => true,
            'reason' => 'human_takeover',
        ]);

        // Resolve a fresh service instance to prove idempotency is persisted in
        // the database rather than depending on an in-memory/cache key.
        $second = app()->make(RealEstateWebhookReceiptService::class)->begin(
            instance: 'emlak-ai-35',
            event: 'messages.upsert',
            messageId: 'durable-message-1',
            phoneNumber: '905550000111',
        );

        $this->assertFalse($second['should_process']);
        $this->assertSame('duplicate_ignored', $second['reason']);
        $this->assertSame(1, RealEstateWebhookReceipt::query()->count());
        $this->assertSame(1, $second['receipt']->attempts);
    }

    public function test_failed_receipt_is_retriable_without_duplicate_row(): void
    {
        $this->seedScope();
        $service = app(RealEstateWebhookReceiptService::class);

        $first = $service->begin(
            instance: 'emlak-ai-35',
            event: 'messages.upsert',
            messageId: 'retry-message-1',
            phoneNumber: '905550000112',
        );

        $service->fail($first['receipt'], new RuntimeException('temporary failure'));

        $retry = $service->begin(
            instance: 'emlak-ai-35',
            event: 'messages.upsert',
            messageId: 'retry-message-1',
            phoneNumber: '905550000112',
        );

        $this->assertTrue($retry['should_process']);
        $this->assertNull($retry['reason']);
        $this->assertSame(2, $retry['receipt']->attempts);
        $this->assertSame('processing', $retry['receipt']->status);
        $this->assertSame(1, RealEstateWebhookReceipt::query()->count());
    }

    public function test_lid_remote_jid_uses_phone_number_alternative_for_reply(): void
    {
        $method = new ReflectionMethod(
            RealEstateWhatsAppInboundService::class,
            'resolvePhoneNumber'
        );

        $resolved = $method->invoke(
            app(RealEstateWhatsAppInboundService::class),
            [
                'data' => [
                    'key' => [
                        'remoteJid' => '126817583255713@lid',
                        'remoteJidAlt' => '905550001122@s.whatsapp.net',
                    ],
                ],
            ]
        );

        $this->assertSame('905550001122', $resolved);
    }

    public function test_unmapped_lid_is_not_treated_as_a_phone_number(): void
    {
        $method = new ReflectionMethod(
            RealEstateWhatsAppInboundService::class,
            'resolvePhoneNumber'
        );

        $resolved = $method->invoke(
            app(RealEstateWhatsAppInboundService::class),
            [
                'data' => [
                    'key' => [
                        'remoteJid' => '126817583255713@lid',
                    ],
                ],
            ]
        );

        $this->assertNull($resolved);
    }

    public function test_standard_phone_jid_remains_supported(): void
    {
        $method = new ReflectionMethod(
            RealEstateWhatsAppInboundService::class,
            'resolvePhoneNumber'
        );

        $resolved = $method->invoke(
            app(RealEstateWhatsAppInboundService::class),
            [
                'data' => [
                    'key' => [
                        'remoteJid' => '905550009988@s.whatsapp.net',
                    ],
                ],
            ]
        );

        $this->assertSame('905550009988', $resolved);
    }

    public function test_parser_turns_whatsapp_location_into_persistent_map_context(): void
    {
        $parser = app(RealEstateWhatsAppMessageParser::class);

        [$message, $context] = $parser->extract([
            'data' => [
                'message' => [
                    'locationMessage' => [
                        'degreesLatitude' => 36.8551,
                        'degreesLongitude' => 28.2742,
                        'name' => 'Hisarönü',
                        'address' => 'Marmaris, Muğla',
                    ],
                ],
            ],
        ], 'emlak-ai-35', 'location-1');

        $this->assertSame('location', $context['type']);
        $this->assertSame('location-1', $context['message_id']);
        $this->assertStringContainsString('Hisarönü', $message);
        $this->assertStringContainsString('Marmaris, Muğla', $message);
        $this->assertStringContainsString('36.8551', $message);
        $this->assertStringStartsWith('https://www.google.com/maps?q=', $context['url']);
        $this->assertStringContainsString($context['url'], $message);
    }

    public function test_parser_unwraps_nested_view_once_media(): void
    {
        $parser = app(RealEstateWhatsAppMessageParser::class);

        [$message, $context] = $parser->extract([
            'data' => [
                'message' => [
                    'ephemeralMessage' => [
                        'message' => [
                            'viewOnceMessageV2' => [
                                'message' => [
                                    'imageMessage' => [
                                        'url' => 'https://example.test/image',
                                        'mimetype' => 'image/jpeg',
                                        'caption' => 'Tapu görseli',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 'emlak-ai-35', 'view-once-1');

        $this->assertSame('Tapu görseli', $message);
        $this->assertSame('image', $context['type']);
        $this->assertSame('image/jpeg', $context['mime_type']);
        $this->assertSame('view-once-1', $context['message_id']);
    }

    public function test_audio_placeholder_never_claims_audio_was_understood(): void
    {
        $parser = app(RealEstateWhatsAppMessageParser::class);

        [$message, $context] = $parser->extract([
            'data' => [
                'message' => [
                    'audioMessage' => [
                        'url' => 'https://example.test/audio',
                        'mimetype' => 'audio/ogg; codecs=opus',
                    ],
                ],
            ],
        ], 'emlak-ai-35', 'audio-1');

        $this->assertSame('audio', $context['type']);
        $this->assertStringContainsString('henüz metne çevrilmedi', $message);
    }

    public function test_webhook_secret_is_read_only_from_organization_37(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'auth-scope@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 36,
            'owner_user_id' => 40,
            'name' => 'Wrong Organization',
            'slug' => 'wrong-org',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString('wrong-secret'),
            ],
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'isolated-org',
            'status' => 'active',
            'settings' => [
                'real_estate_webhook_secret' => Crypt::encryptString('correct-secret'),
            ],
        ]);

        $this->assertSame(
            'correct-secret',
            app(RealEstateWebhookAuthService::class)->secret()
        );
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'resilience@example.test',
            'password' => Hash::make('password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'resilience',
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
}
