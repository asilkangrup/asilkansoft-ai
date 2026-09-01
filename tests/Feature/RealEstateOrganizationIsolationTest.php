<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\MemoryService;
use App\Services\RealEstateDecisionService;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateMatchService;
use App\Services\RealEstateOpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_user_and_bot_profile_in_foreign_organization_cannot_be_matched(): void
    {
        $bot = $this->seedScope();
        $sellerConversation = $this->conversation($bot, 'org37-seller', '905550000101');
        $foreignConversation = $this->conversation($bot, 'org38-investor', '905550000102');
        $foreignConversation->forceFill(['organization_id' => 38])->saveQuietly();
        $foreignConversation->refresh();

        $seller = RealEstateProfile::query()->create([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 4000000,
                'title_deed_type' => 'müstakil',
                'zoning_status' => 'konut',
            ],
            'valuation' => [
                'market_min' => 3800000,
                'market_max' => 4300000,
                'investor_buy_max' => 3600000,
                'confidence_score' => 85,
            ],
            'completeness_score' => 100,
            'confidence_score' => 85,
        ]);

        $foreignInvestor = RealEstateProfile::query()->create([
            'conversation_control_id' => $foreignConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'budget_max' => 5000000,
                'financing' => 'cash',
                'investment_goal' => 'değer artışı',
                'timeline' => 'hemen',
            ],
            'valuation' => [],
            'completeness_score' => 100,
            'confidence_score' => 90,
        ]);

        $this->assertSame(37, (int) $sellerConversation->organization_id);
        $this->assertSame(38, (int) $foreignConversation->organization_id);
        $this->assertTrue($seller->belongsToIsolatedProductionScope());
        $this->assertFalse($foreignInvestor->belongsToIsolatedProductionScope());
        $this->assertSame(1, RealEstateProfile::query()->isolatedProduction()->count());

        $matches = app(RealEstateMatchService::class)->process($sellerConversation);

        $this->assertSame([], $matches);
        $this->assertSame([], $seller->fresh()->data['opportunity_matches']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($foreignConversation->fresh()->next_follow_up_at);
    }

    public function test_foreign_organization_conversation_cannot_run_decision_intelligence(): void
    {
        $bot = $this->seedScope();
        $conversation = $this->conversation($bot, 'org38-decision', '905550000103');
        $conversation->forceFill([
            'organization_id' => 38,
            'lead_score' => 7,
            'next_best_action' => 'unchanged',
        ])->saveQuietly();
        $conversation->refresh();

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
                'asking_price' => 4000000,
            ],
            'valuation' => ['market_min' => 3500000, 'market_max' => 4200000],
            'completeness_score' => 100,
            'confidence_score' => 90,
        ]);

        $result = app(RealEstateDecisionService::class)->process($conversation);

        $this->assertNull($result);
        $this->assertSame(7, (int) $conversation->fresh()->lead_score);
        $this->assertSame('unchanged', $conversation->fresh()->next_best_action);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_real_estate_history_and_delete_are_scoped_to_org37_and_bot35(): void
    {
        $bot = $this->seedScope();
        $sessionId = 'whatsapp:35:905550000104';
        $this->conversation($bot, $sessionId, '905550000104');

        $exact = ChatMessage::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $sessionId,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => 'Exact production message',
            'message_type' => 'text',
            'status' => 'received',
        ]);

        $foreign = ChatMessage::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => 35,
            'session_id' => $sessionId,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => 'Foreign organization message',
            'message_type' => 'text',
            'status' => 'received',
        ]);

        $otherBot = AiBot::query()->create([
            'user_id' => 40,
            'name' => 'Other Bot',
            'company_name' => 'Other',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
        ]);
        $other = ChatMessage::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $otherBot->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => 'Other bot message',
            'message_type' => 'text',
            'status' => 'received',
        ]);

        $memory = app(MemoryService::class);
        $history = $memory->gecmisiGetir(40, $sessionId, 20);

        $this->assertSame([$exact->id], $history->pluck('id')->all());

        $memory->sohbetiTemizle(40, $sessionId);

        $this->assertDatabaseMissing('chat_messages', ['id' => $exact->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $foreign->id]);
        $this->assertDatabaseHas('chat_messages', ['id' => $other->id]);
    }

    public function test_bot35_metadata_drift_still_cannot_fall_back_to_shared_openai(): void
    {
        $bot = $this->seedScope();
        $bot->forceFill(['business_sector' => 'unexpected-drift']);

        $isolation = app(RealEstateIsolationService::class);

        $this->assertTrue($isolation->dedicatedOpenAiOnlyForBot($bot));
        $this->assertFalse($isolation->supportsBotIdentity($bot));
        $this->assertSame(
            'Emlak danışmanlığı yapılandırması doğrulanamadı. Lütfen daha sonra tekrar deneyin.',
            app(RealEstateOpenAIService::class)->cevapVer('Merhaba', $bot)
        );
    }

    private function seedScope(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'org-isolation@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'org-isolation-exact',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'org-isolation-foreign',
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
