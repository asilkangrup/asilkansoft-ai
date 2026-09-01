<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateMediaAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RealEstateMediaPostProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_media_row_removes_immediately_preceding_legacy_placeholder(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'media-dedupe-video');

        ChatMessage::query()->create([
            'user_id' => 40,
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

    public function test_successful_media_analysis_recomputes_verification_on_same_message(): void
    {
        $bot = $this->seedRealEstateBot();
        $conversation = $this->conversation($bot, 'media-verification-same-turn');

        RealEstateProfile::query()->create([
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
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
                'asking_price' => 4000000,
                'media_findings' => [[
                    'message_id' => 'wamid-conflict',
                    'document_type' => 'tapu',
                    'city' => 'Muğla',
                    'district' => 'Bodrum',
                    'area_sqm' => 1000,
                    'block_no' => '10',
                    'parcel_no' => '20',
                    'title_deed_type' => 'müstakil',
                    'zoning_status' => 'konut',
                    'confidence_score' => 92,
                ]],
            ],
            'valuation' => [
                'market_min' => 3800000,
                'market_max' => 4300000,
                'investor_buy_max' => 3600000,
                'confidence_score' => 80,
            ],
            'completeness_score' => 95,
            'confidence_score' => 85,
        ]);

        $mock = Mockery::mock(RealEstateMediaAnalysisService::class);
        $mock->shouldReceive('process')
            ->once()
            ->andReturn([
                'message_id' => 'wamid-conflict',
                'document_type' => 'tapu',
            ]);
        $this->app->instance(RealEstateMediaAnalysisService::class, $mock);

        ChatMessage::query()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '[Fotoğraf]',
            'message_type' => 'image',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => 'tapu.jpg',
            'whatsapp_message_id' => 'wamid-conflict',
            'status' => 'received',
        ]);

        $profile = RealEstateProfile::query()
            ->where('conversation_control_id', $conversation->id)
            ->firstOrFail();
        $conversation->refresh();

        $this->assertSame(
            'blocked',
            $profile->data['verification_intelligence']['status']
        );
        $this->assertGreaterThanOrEqual(
            85,
            $profile->data['verification_intelligence']['risk_score']
        );
        $this->assertFalse(
            $profile->data['decision_intelligence']['ready_for_match']
        );
        $this->assertContains(
            'real_estate:verification:blocked',
            $conversation->etiketler()
        );
        $this->assertStringContainsString(
            'ilçe çelişkisini',
            (string) $conversation->next_best_action
        );
        $this->assertNull($conversation->next_follow_up_at);
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
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '905550003333',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
    }
}
