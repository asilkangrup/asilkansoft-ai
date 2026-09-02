<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Observers\ChatMessageObserver;
use App\Services\RealEstateValuationFreshnessService;
use App\Services\RealEstateVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateResidualTenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_organization_media_is_ignored_before_cleanup_or_analysis(): void
    {
        $bot = $this->seedScope();
        $sessionId = 'whatsapp:35:905550000201';
        $foreignConversation = $this->conversation($bot, $sessionId, '905550000201');
        $foreignConversation->forceFill(['organization_id' => 38])->saveQuietly();
        $foreignConversation->refresh();

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $foreignConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => ['media_findings' => []],
            'valuation' => [],
            'completeness_score' => 0,
            'confidence_score' => 0,
        ]);

        $productionPlaceholder = ChatMessage::withoutEvents(fn () =>
            ChatMessage::query()->forceCreate([
                'user_id' => 40,
                'organization_id' => 37,
                'ai_bot_id' => 35,
                'session_id' => $sessionId,
                'role' => 'user',
                'sender_type' => 'customer',
                'message' => '[Görsel]',
                'message_type' => 'text',
                'status' => 'received',
            ])
        );

        $foreignMedia = ChatMessage::withoutEvents(fn () =>
            ChatMessage::query()->forceCreate([
                'user_id' => 40,
                'organization_id' => 38,
                'ai_bot_id' => 35,
                'session_id' => $sessionId,
                'role' => 'user',
                'sender_type' => 'customer',
                'message' => '[Görsel]',
                'message_type' => 'image',
                'media_mime_type' => 'image/jpeg',
                'whatsapp_message_id' => 'foreign-media-201',
                'status' => 'received',
            ])
        );

        app(ChatMessageObserver::class)->created($foreignMedia);

        $this->assertDatabaseHas('chat_messages', ['id' => $productionPlaceholder->id]);
        $this->assertSame([], $profile->fresh()->data['media_findings']);
        $this->assertNull($foreignConversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_verification_cannot_mutate_profile_or_tags(): void
    {
        $bot = $this->seedScope();
        $conversation = $this->conversation($bot, 'org38-verification', '905550000202');
        $conversation->forceFill([
            'organization_id' => 38,
            'tags' => ['keep-me'],
        ])->saveQuietly();
        $conversation->refresh();

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'district' => 'Marmaris',
                'media_findings' => [[
                    'district' => 'Bodrum',
                    'confidence_score' => 95,
                ]],
            ],
            'valuation' => [],
            'completeness_score' => 20,
            'confidence_score' => 80,
        ]);

        $before = $profile->data;
        $result = app(RealEstateVerificationService::class)->process($conversation);

        $this->assertNull($result);
        $this->assertSame($before, $profile->fresh()->data);
        $this->assertSame(['keep-me'], $conversation->fresh()->etiketler());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_profile_cannot_reuse_fresh_valuation(): void
    {
        $bot = $this->seedScope();
        $conversation = $this->conversation($bot, 'org38-freshness', '905550000203');
        $conversation->forceFill(['organization_id' => 38])->saveQuietly();
        $conversation->refresh();

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
            ],
            'valuation' => [
                'market_min' => 3500000,
                'market_max' => 4200000,
                'confidence_score' => 90,
                'researched_at' => now()->toIso8601String(),
                'sources' => ['https://example.test/1'],
                'comparables' => [
                    ['url' => 'https://example.test/1', 'price' => 3900000, 'area_sqm' => 1000],
                    ['url' => 'https://example.test/2', 'price' => 4100000, 'area_sqm' => 1050],
                ],
            ],
            'completeness_score' => 90,
            'confidence_score' => 90,
        ]);

        $before = $profile->valuation;
        $freshness = app(RealEstateValuationFreshnessService::class);
        $assessment = $freshness->assess($profile);
        $refreshed = $freshness->refreshMetadata($profile);

        $this->assertSame('out_of_scope', $assessment['status']);
        $this->assertSame(['out_of_scope'], $assessment['reasons']);
        $this->assertFalse($assessment['usable_for_decision']);
        $this->assertFalse($assessment['usable_for_matching']);
        $this->assertSame('out_of_scope', $refreshed['status']);
        $this->assertSame($before, $profile->fresh()->valuation);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'residual-boundary@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'residual-boundary-exact',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'residual-boundary-foreign',
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
            'whatsapp_status' => 'connecting',
            'group_routing_enabled' => false,
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);
    }

    private function conversation(
        AiBot $bot,
        string $sessionId,
        string $number,
    ): ConversationControl {
        return ConversationControl::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => $number,
            'tags' => [],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
