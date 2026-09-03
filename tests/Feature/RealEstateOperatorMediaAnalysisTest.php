<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakMedyaAnalizi;
use App\Jobs\RunRealEstateOperatorMediaAnalysis;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOutboundDelivery;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMediaAnalysisService;
use App\Services\RealEstateOperatorMediaAnalysisService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RealEstateOperatorMediaAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_is_exactly_isolated_prioritized_and_privacy_safe(): void
    {
        [$owner] = $this->seedAccounts();
        $target = $this->seller(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'operator-media-target',
            name: 'Gizli Satıcı',
            phone: '905559999981',
            data: [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'minimum_price' => 1_987_654,
                'decision_intelligence' => [
                    'lead_score' => 94,
                    'lead_temperature' => 'hot',
                ],
            ],
        );
        $targetMessage = $this->media(
            user: 40,
            organization: 37,
            bot: 35,
            session: 'operator-media-target',
            type: 'document',
            messageId: 'wamid-operator-media-target',
            mime: 'application/pdf',
            filename: 'TC-123-secret.pdf',
            caption: 'Telefon 0555 999 99 81 gizli açıklama',
        );
        $this->addPlaceholder($target, $targetMessage);

        $foreign = $this->seller(
            user: 41,
            organization: 38,
            bot: 36,
            session: 'operator-media-foreign',
            name: 'Foreign Seller',
            phone: '905559999982',
            data: ['decision_intelligence' => ['lead_score' => 100, 'lead_temperature' => 'hot']],
        );
        $foreignMessage = $this->media(
            user: 41,
            organization: 38,
            bot: 36,
            session: 'operator-media-foreign',
            type: 'document',
            messageId: 'wamid-foreign-secret',
            mime: 'application/pdf',
            filename: 'foreign-secret.pdf',
            caption: 'Foreign private text',
        );
        $this->addPlaceholder($foreign, $foreignMessage);

        $service = app(RealEstateOperatorMediaAnalysisService::class);
        $items = $service->queueItems();
        $summary = $service->summary();

        $this->assertCount(1, $items);
        $this->assertSame($targetMessage->id, $items->first()['chat_message_id']);
        $this->assertSame($target->id, $items->first()['profile_id']);
        $this->assertSame('document', $items->first()['media_type']);
        $this->assertGreaterThanOrEqual(354, $items->first()['priority_score']);
        $this->assertFalse($items->first()['automatic_analysis_allowed']);
        $this->assertFalse($items->first()['automatic_outbound_allowed']);
        $this->assertFalse($items->first()['customer_follow_up_allowed']);
        $this->assertFalse($items->first()['contains_customer_pii']);
        $this->assertFalse($items->first()['contains_media_content']);
        $this->assertTrue($summary['ready']);
        $this->assertTrue($summary['bot']['dedicated_openai_key_configured']);
        $this->assertTrue($summary['bot']['dedicated_openai_key_only']);
        $this->assertTrue($summary['bot']['follow_ups_disabled']);
        $this->assertFalse($summary['automatic_media_analysis_allowed']);
        $this->assertTrue($summary['operator_trigger_required']);

        $payload = json_encode([
            'summary' => $summary,
            'items' => $items->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Gizli Satıcı', $payload);
        $this->assertStringNotContainsString('905559999981', $payload);
        $this->assertStringNotContainsString('1987654', $payload);
        $this->assertStringNotContainsString('TC-123-secret.pdf', $payload);
        $this->assertStringNotContainsString('gizli açıklama', $payload);
        $this->assertStringNotContainsString('wamid-operator-media-target', $payload);
        $this->assertStringNotContainsString('Foreign Seller', $payload);
        $this->assertStringNotContainsString('wamid-foreign-secret', $payload);

        $this->actingAs($owner);
        $this->assertTrue(EmlakMedyaAnalizi::canAccess());
        $this->assertSame(1, (new EmlakMedyaAnalizi)->getItemsProperty()->count());
    }

    public function test_only_exact_operator_and_exact_media_can_request_analysis(): void
    {
        [$owner, $foreignOwner] = $this->seedAccounts();
        $targetProfile = $this->seller(40, 37, 35, 'media-request-target', 'Target', '905559999983', []);
        $target = $this->media(40, 37, 35, 'media-request-target', 'image', 'wamid-request-target', 'image/jpeg');
        $this->addPlaceholder($targetProfile, $target);
        $foreignProfile = $this->seller(41, 38, 36, 'media-request-foreign', 'Foreign', '905559999984', []);
        $foreign = $this->media(41, 38, 36, 'media-request-foreign', 'image', 'wamid-request-foreign', 'image/jpeg');
        $this->addPlaceholder($foreignProfile, $foreign);
        $service = app(RealEstateOperatorMediaAnalysisService::class);

        $state = $service->request($target->id, $owner->id);
        $this->assertSame('queued', $state['status']);
        $this->assertTrue($state['dedicated_openai_key_only']);
        $this->assertFalse($state['automatic_analysis_allowed']);
        $this->assertFalse($state['automatic_outbound_allowed']);
        $this->assertFalse($state['customer_follow_up_allowed']);

        try {
            $service->request($target->id, $foreignOwner->id);
            $this->fail('Foreign operator request should have failed closed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->expectException(ModelNotFoundException::class);
        $service->request($foreign->id, $owner->id);
    }

    public function test_operator_run_replaces_crm_placeholder_recomputes_memory_and_sends_nothing(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(
            40,
            37,
            35,
            'media-run-success',
            'Media Seller',
            '905559999985',
            [
                'property_type' => 'arsa',
                'decision_intelligence' => ['lead_score' => 88, 'lead_temperature' => 'hot'],
            ],
        );
        $message = $this->media(
            40,
            37,
            35,
            'media-run-success',
            'document',
            'wamid-run-success',
            'application/pdf',
        );
        $this->addPlaceholder($profile, $message);
        $service = app(RealEstateOperatorMediaAnalysisService::class);
        $service->request($message->id, $owner->id);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($conversation, string $instance, array $mediaContext) use ($profile, $message): bool {
                $fresh = $profile->fresh();
                $finding = collect((array) data_get($fresh->data, 'media_findings', []))
                    ->first(fn ($item): bool => is_array($item)
                        && ($item['message_id'] ?? null) === $message->whatsapp_message_id);

                $this->assertNull($finding, 'CRM-only placeholder must not short-circuit the explicit paid analysis.');
                $this->assertSame(40, (int) $conversation->user_id);
                $this->assertSame(37, (int) $conversation->organization_id);
                $this->assertSame(35, (int) $conversation->ai_bot_id);
                $this->assertSame('emlak-ai-35', $instance);
                $this->assertSame('document', $mediaContext['type']);
                $this->assertSame('wamid-run-success', $mediaContext['message_id']);

                return true;
            })
            ->andReturn([
                'message_id' => 'wamid-run-success',
                'media_category' => 'parcel_document',
                'document_type' => 'parsel',
                'summary' => 'Parsel belgesi gözlemi.',
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'block_no' => '12',
                'parcel_no' => '34',
                'warnings' => [],
                'confidence_score' => 91,
                'analyzed_at' => now()->toIso8601String(),
            ]);
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);

        $result = $service->run($message->id, $owner->id);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('parcel_document', $result['media_category']);
        $this->assertSame(91, $result['confidence_score']);
        $this->assertTrue($result['dedicated_openai_key_only']);
        $this->assertSame(0, RealEstateOutboundDelivery::query()->count());
        $this->assertSame(1, ChatMessage::query()->where('sender_type', 'customer')->count());
        $this->assertSame(0, ChatMessage::query()->whereIn('sender_type', ['ai', 'human'])->count());

        $fresh = $profile->fresh();
        $finding = collect((array) data_get($fresh->data, 'media_findings', []))
            ->first(fn ($item): bool => is_array($item)
                && ($item['message_id'] ?? null) === 'wamid-run-success');
        $this->assertIsArray($finding);
        $this->assertTrue((bool) ($finding['vision_analyzed'] ?? false));
        $this->assertFalse((bool) ($finding['legal_verification'] ?? true));
        $this->assertSame('operator_media_analysis', $finding['source']);
        $this->assertSame(91, $finding['confidence_score']);

        $states = (array) data_get($fresh->data, 'operator_media_analysis', []);
        $this->assertCount(1, $states);
        $this->assertSame('completed', array_values($states)[0]['status']);
        $this->assertFalse(array_values($states)[0]['customer_follow_up_allowed']);
        $this->assertNull($fresh->conversation?->next_follow_up_at);
        $bot = AiBot::query()->findOrFail(35);
        $this->assertFalse((bool) $bot->follow_up_enabled);
        $this->assertFalse((bool) $bot->second_follow_up_enabled);
    }

    public function test_analysis_failure_restores_crm_only_placeholder_without_retry_or_outbound(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(40, 37, 35, 'media-run-failure', 'Failure', '905559999986', []);
        $message = $this->media(40, 37, 35, 'media-run-failure', 'image', 'wamid-run-failure', 'image/jpeg');
        $this->addPlaceholder($profile, $message);
        $service = app(RealEstateOperatorMediaAnalysisService::class);
        $service->request($message->id, $owner->id);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldReceive('process')->once()->andReturn(null);
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);

        $result = $service->run($message->id, $owner->id);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('analysis_not_generated', $result['failure_code']);
        $fresh = $profile->fresh();
        $finding = collect((array) data_get($fresh->data, 'media_findings', []))
            ->first(fn ($item): bool => is_array($item)
                && ($item['message_id'] ?? null) === 'wamid-run-failure');
        $this->assertSame('crm_only_media_registration', $finding['source'] ?? null);
        $this->assertFalse((bool) ($finding['vision_analyzed'] ?? true));
        $this->assertSame(0, RealEstateOutboundDelivery::query()->count());
        $this->assertNull($fresh->conversation?->next_follow_up_at);
    }

    public function test_follow_up_drift_blocks_before_media_download_or_model_call(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(40, 37, 35, 'media-followup-drift', 'Drift', '905559999987', []);
        $message = $this->media(40, 37, 35, 'media-followup-drift', 'image', 'wamid-media-drift', 'image/jpeg');
        $this->addPlaceholder($profile, $message);
        $service = app(RealEstateOperatorMediaAnalysisService::class);
        $service->request($message->id, $owner->id);

        DB::table('ai_bots')->where('id', 35)->update(['second_follow_up_enabled' => true]);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);

        $result = $service->run($message->id, $owner->id);

        $this->assertSame('blocked', $result['status']);
        $this->assertSame('isolation_or_bot_guard_failed', $result['failure_code']);
        $this->assertSame('crm_only_media_registration', data_get($profile->fresh()->data, 'media_findings.0.source'));
        $this->assertSame(0, RealEstateOutboundDelivery::query()->count());
    }

    public function test_already_analyzed_media_is_idempotent_and_not_queued_for_paid_work(): void
    {
        [$owner] = $this->seedAccounts();
        $profile = $this->seller(40, 37, 35, 'media-already-done', 'Done', '905559999988', []);
        $message = $this->media(40, 37, 35, 'media-already-done', 'image', 'wamid-already-done', 'image/jpeg');
        $data = (array) $profile->data;
        $data['media_findings'] = [[
            'message_id' => 'wamid-already-done',
            'media_category' => 'property_photo',
            'confidence_score' => 82,
            'analyzed_at' => now()->toIso8601String(),
            'vision_analyzed' => true,
            'source' => 'operator_media_analysis',
        ]];
        $profile->forceFill(['data' => $data])->saveQuietly();
        $service = app(RealEstateOperatorMediaAnalysisService::class);

        $this->assertCount(0, $service->queueItems());
        $state = $service->request($message->id, $owner->id);
        $this->assertSame('completed', $state['status']);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);
        $result = $service->run($message->id, $owner->id);
        $this->assertSame('completed', $result['status']);
    }

    public function test_job_is_unique_per_media_and_never_declares_hidden_retry_spend(): void
    {
        $job = new RunRealEstateOperatorMediaAnalysis(321, 40);

        $this->assertSame('isolated-real-estate-operator-media:321', $job->uniqueId());
        $this->assertSame(1, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertSame(600, $job->uniqueFor);
    }

    private function addPlaceholder(RealEstateProfile $profile, ChatMessage $message): void
    {
        $data = is_array($profile->data) ? $profile->data : [];
        $findings = is_array($data['media_findings'] ?? null) ? $data['media_findings'] : [];
        $findings[] = [
            'message_id' => $message->whatsapp_message_id,
            'media_category' => $message->message_type === 'image' ? 'property_photo' : 'parcel_document',
            'summary' => 'Yazılı teyit bekliyor.',
            'confidence_score' => 0,
            'operator_classified' => false,
            'legal_verification' => false,
            'vision_analyzed' => false,
            'source' => 'crm_only_media_registration',
            'registered_at' => now()->toIso8601String(),
        ];
        $data['media_findings'] = $findings;
        $profile->forceFill(['data' => $data])->saveQuietly();
    }

    private function media(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $type,
        string $messageId,
        string $mime,
        string $filename = 'property-media',
        string $caption = '',
    ): ChatMessage {
        return ChatMessage::withoutEvents(fn () => ChatMessage::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => $type === 'document' ? '[Belge]' : '[Fotoğraf]',
            'message_type' => $type,
            'media_url' => null,
            'media_mime_type' => $mime,
            'media_filename' => $filename,
            'media_caption' => $caption,
            'media_size' => 1024,
            'whatsapp_message_id' => $messageId,
            'status' => 'received',
        ]));
    }

    private function seller(
        int $user,
        int $organization,
        int $bot,
        string $session,
        string $name,
        string $phone,
        array $data,
    ): RealEstateProfile {
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => $phone,
            'customer_name' => $name,
            'notes' => 'Gizli konuşma notu',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => (int) data_get($data, 'decision_intelligence.lead_score', 0),
            'lead_temperature' => (string) data_get($data, 'decision_intelligence.lead_temperature', 'cold'),
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => $user,
            'ai_bot_id' => $bot,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 60,
            'confidence_score' => 50,
            'last_extracted_at' => now(),
        ]));
    }

    /** @return array{0:User,1:User} */
    private function seedAccounts(): array
    {
        $owner = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'operator-media@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'operator-media',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $owner->organizations()->syncWithoutDetaching([
            37 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
        ]);
        AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-isolated-media-test-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        $foreign = User::query()->forceCreate([
            'id' => 41,
            'name' => 'Foreign',
            'email' => 'foreign-operator-media@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'foreign-operator-media',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $foreign->organizations()->syncWithoutDetaching([
            38 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
        ]);
        AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 41,
            'name' => 'Foreign',
            'company_name' => 'Foreign',
            'openai_model' => 'gpt-5-mini',
            'openai_api_key' => 'sk-proj-foreign-media-test-key',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'foreign-media',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return [$owner, $foreign];
    }
}
