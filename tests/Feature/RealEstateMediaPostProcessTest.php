<?php

namespace Tests\Feature;

use App\Jobs\RegisterRealEstateMediaCrmFinding;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMediaAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RealEstateMediaPostProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_media_row_removes_immediately_preceding_legacy_placeholder(): void
    {
        Queue::fake();
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'media-dedupe-video');

        ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '[Video]',
            'message_type' => 'text',
            'status' => 'received',
        ]);

        $media = ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '[Video]',
            'message_type' => 'video',
            'media_mime_type' => 'video/mp4',
            'media_filename' => 'Video',
            'whatsapp_message_id' => 'wamid-video-dedupe',
            'status' => 'received',
        ]);

        $this->assertSame(1, ChatMessage::query()
            ->where('user_id', 40)
            ->where('ai_bot_id', 35)
            ->where('session_id', $conversation->session_id)
            ->count());
        $this->assertDatabaseHas('chat_messages', [
            'id' => $media->id,
            'message_type' => 'video',
            'whatsapp_message_id' => 'wamid-video-dedupe',
        ]);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_media_observer_registers_crm_work_without_automatic_analysis_or_decision_recompute(): void
    {
        Queue::fake();
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'media-crm-only-same-turn');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'area_sqm' => 1000,
                'block_no' => '10',
                'parcel_no' => '20',
                'asking_price' => 4000000,
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 70,
        ]);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);

        $message = ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '[Fotoğraf]',
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => 'arsa.jpg',
            'whatsapp_message_id' => 'wamid-crm-only',
            'status' => 'received',
        ]);

        Queue::assertPushed(
            RegisterRealEstateMediaCrmFinding::class,
            fn (RegisterRealEstateMediaCrmFinding $job): bool => $job->chatMessageId === $message->id
        );

        $profile->refresh();
        $this->assertArrayNotHasKey('verification_intelligence', $profile->data);
        $this->assertArrayNotHasKey('decision_intelligence', $profile->data);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_media_analysis_returns_existing_finding_without_external_call(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'media-idempotent');
        $existing = [
            'message_id' => 'wamid-existing',
            'document_type' => 'tapu',
            'summary' => 'Daha önce analiz edildi.',
            'confidence_score' => 88,
        ];

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'media_findings' => [$existing],
            ],
            'valuation' => [],
            'completeness_score' => 10,
            'confidence_score' => 88,
        ]);

        $result = app(RealEstateMediaAnalysisService::class)->process(
            conversation: $conversation,
            instanceName: 'emlak-ai-35',
            mediaContext: [
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'message_id' => 'wamid-existing',
                'message_envelope' => [
                    'key' => ['id' => 'wamid-existing'],
                ],
            ],
        );

        $this->assertSame($existing, $result);
        $profile->refresh();
        $this->assertCount(1, $profile->data['media_findings']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedRealEstateBot(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'media-postprocess@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-media-postprocess',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        return AiBot::query()->forceCreate([
            'id' => 35,
            'user_id' => 40,
            'name' => 'Emlak AI',
            'company_name' => 'Asilkan Gayrimenkul',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'emlak-ai-35',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }

    private function conversation(
        AiBot $bot,
        string $sessionId
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '905550003333',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
    }
}
