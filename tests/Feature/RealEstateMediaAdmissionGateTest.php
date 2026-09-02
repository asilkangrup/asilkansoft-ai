<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateMediaProcessingEvent;
use App\Models\User;
use App\Services\EvolutionMediaService;
use App\Services\RealEstateGuardedMediaAnalysisService;
use App\Services\RealEstateMediaAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class RealEstateMediaAdmissionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_media_context_is_ignored_before_download_and_ledger(): void
    {
        $bot = $this->seedScope();
        $conversation = $this->conversation($bot, 'non-media-admission');

        $media = Mockery::mock(EvolutionMediaService::class);
        $media->shouldNotReceive('downloadBase64');
        $this->app->instance(EvolutionMediaService::class, $media);

        $service = app(RealEstateMediaAnalysisService::class);
        $this->assertInstanceOf(RealEstateGuardedMediaAnalysisService::class, $service);

        foreach (['text', 'location', 'audio', 'video'] as $type) {
            $result = $service->process(
                conversation: $conversation,
                instanceName: 'emlak-ai-35',
                mediaContext: [
                    'type' => $type,
                    'mime_type' => null,
                    'message_id' => 'wamid-'.$type,
                    'message_envelope' => ['key' => ['id' => 'wamid-'.$type]],
                ],
            );

            $this->assertNull($result);
        }

        $this->assertSame(0, RealEstateMediaProcessingEvent::query()->count());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_real_image_candidate_still_uses_fail_closed_media_firewall(): void
    {
        $bot = $this->seedScope();
        $conversation = $this->conversation($bot, 'real-media-admission');

        $media = Mockery::mock(EvolutionMediaService::class);
        $media->shouldNotReceive('downloadBase64');
        $this->app->instance(EvolutionMediaService::class, $media);

        $result = app(RealEstateMediaAnalysisService::class)->process(
            conversation: $conversation,
            instanceName: 'emlak-ai-35',
            mediaContext: [
                'type' => 'image',
                'mime_type' => 'image/svg+xml',
                'size' => 1024,
                'message_id' => 'wamid-svg-still-rejected',
                'message_envelope' => ['key' => ['id' => 'wamid-svg-still-rejected']],
            ],
        );

        $this->assertNull($result);
        $this->assertDatabaseHas('real_estate_media_processing_events', [
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'outcome' => 'rejected',
            'reason' => 'unsupported_mime',
        ]);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'media-admission@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'media-admission',
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

    private function conversation(AiBot $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $session,
            'whatsapp_number' => '905550009999',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
