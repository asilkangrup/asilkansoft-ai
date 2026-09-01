<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateNegotiationEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateNegotiationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateNegotiationMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_price_positions_are_kept_as_a_durable_private_trajectory(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'negotiation-seller');

        $this->customerMessage(
            $conversation,
            'İstediğim fiyat 5 milyon, 4.5 milyonun altında düşünmüyorum. Telefonum 05550000000.'
        );

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 5000000,
                'minimum_price' => 4500000,
                'urgency' => 'medium',
            ],
            'valuation' => [],
            'completeness_score' => 85,
            'confidence_score' => 80,
        ]);

        $this->assertDatabaseHas('real_estate_negotiation_events', [
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'real_estate_profile_id' => $profile->id,
            'event_type' => 'seller_asking_price',
            'direction' => 'initial',
        ]);

        $floor = RealEstateNegotiationEvent::query()
            ->where('real_estate_profile_id', $profile->id)
            ->where('event_type', 'seller_minimum_price')
            ->firstOrFail();

        $this->assertSame('4500000.00', $floor->numeric_value);
        $this->assertTrue((bool) $floor->metadata['confidential']);
        $this->assertTrue((bool) $floor->metadata['customer_sourced']);

        $this->customerMessage(
            $conversation,
            'Piyasayı düşündüm, ilanı 4.6 milyona çekebilirim; alt sınırım 4 milyon olsun.'
        );

        $data = $profile->fresh()->data;
        $data['asking_price'] = 4600000;
        $data['minimum_price'] = 4000000;
        $data['urgency'] = 'high';
        $profile->update(['data' => $data]);

        $service = app(RealEstateNegotiationMemoryService::class);
        $summary = $service->summaryForProfile($profile->fresh());

        $this->assertSame(-8.0, $summary['seller_asking_trajectory_percent']);
        $this->assertSame(-11.11, $summary['seller_floor_trajectory_percent']);
        $this->assertGreaterThanOrEqual(1, $summary['material_change_count']);
        $this->assertSame(4600000.0, $summary['latest_positions']['seller_asking_price']['value']);
        $this->assertSame(5000000.0, $summary['latest_positions']['seller_asking_price']['previous_value']);
        $this->assertSame('down', $summary['latest_positions']['seller_asking_price']['direction']);
        $this->assertTrue($summary['guardrails']['seller_minimum_price_is_confidential']);

        $eventCount = RealEstateNegotiationEvent::query()
            ->where('real_estate_profile_id', $profile->id)
            ->count();

        $data = $profile->fresh()->data;
        $data['decision_intelligence'] = ['lead_score' => 91];
        $profile->update(['data' => $data]);

        $this->assertSame(
            $eventCount,
            RealEstateNegotiationEvent::query()
                ->where('real_estate_profile_id', $profile->id)
                ->count()
        );

        $prompt = $service->promptFor($conversation->fresh());
        $this->assertStringContainsString('seller_minimum_price', $prompt);
        $this->assertStringContainsString('otomatik olarak açıklanamaz', $prompt);
        $this->assertStringNotContainsString('05550000000', $prompt);

        $storedSummary = $profile->fresh()->data['negotiation_memory_intelligence'];
        $this->assertArrayNotHasKey('raw_message', $storedSummary);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_investor_budget_and_terms_are_remembered_without_becoming_a_binding_offer(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation(
            $bot,
            'negotiation-investor',
            ['business:real_estate_investor']
        );

        $this->customerMessage(
            $conversation,
            'Maksimum bütçem 5 milyon, nakit alırım ve bu ay içinde işlem yaparım.'
        );

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'city' => 'Muğla',
                'property_type' => 'arsa',
                'budget_max' => 5000000,
                'financing' => 'cash',
                'timeline' => 'bu ay',
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]);

        $this->customerMessage(
            $conversation,
            'Uygun fırsatsa 5.5 milyona kadar çıkabilirim ama yine nakit alacağım.'
        );
        $data = $profile->fresh()->data;
        $data['budget_max'] = 5500000;
        $profile->update(['data' => $data]);

        $summary = app(RealEstateNegotiationMemoryService::class)
            ->summaryForProfile($profile->fresh());

        $this->assertSame(10.0, $summary['investor_budget_max_trajectory_percent']);
        $this->assertSame('up', $summary['latest_positions']['investor_budget_max']['direction']);
        $this->assertTrue($summary['guardrails']['positions_are_not_binding_offers']);
        $this->assertTrue($summary['guardrails']['do_not_invent_acceptance_or_counter_offer']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_background_profile_recalculation_without_a_recent_customer_message_cannot_invent_negotiation_events(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'negotiation-background');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'asking_price' => 7000000,
                'minimum_price' => 6500000,
            ],
            'valuation' => [],
            'completeness_score' => 40,
            'confidence_score' => 40,
        ]);

        $this->assertSame(
            0,
            RealEstateNegotiationEvent::query()
                ->where('real_estate_profile_id', $profile->id)
                ->count()
        );
        $this->assertArrayNotHasKey(
            'negotiation_memory_intelligence',
            $profile->fresh()->data
        );
    }

    public function test_negotiation_memory_is_hard_scoped_to_the_fresh_account(): void
    {
        $otherUser = User::query()->create([
            'name' => 'Other',
            'email' => 'negotiation-other@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $otherOrganization = Organization::query()->create([
            'owner_user_id' => $otherUser->id,
            'name' => 'Other Org',
            'slug' => 'negotiation-other-org',
            'status' => 'active',
        ]);
        $otherBot = AiBot::query()->create([
            'user_id' => $otherUser->id,
            'name' => 'Other Bot',
            'company_name' => 'Other',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
        ]);
        $conversation = ConversationControl::query()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
            'ai_bot_id' => $otherBot->id,
            'session_id' => 'negotiation-other',
            'whatsapp_number' => '905559999999',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'next_follow_up_at' => null,
        ]);
        ChatMessage::query()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrganization->id,
            'ai_bot_id' => $otherBot->id,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => '5 milyon istiyorum',
            'message_type' => 'text',
            'status' => 'received',
        ]);
        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => $otherUser->id,
            'ai_bot_id' => $otherBot->id,
            'profile_type' => 'seller',
            'data' => ['asking_price' => 5000000],
            'valuation' => [],
            'completeness_score' => 30,
            'confidence_score' => 30,
        ]);

        $summary = app(RealEstateNegotiationMemoryService::class)->sync($profile);

        $this->assertSame([], $summary);
        $this->assertSame(0, RealEstateNegotiationEvent::query()->count());
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'negotiation@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-negotiation',
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
        string $sessionId,
        array $tags = ['business:real_estate_seller']
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'tags' => $tags,
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function customerMessage(ConversationControl $conversation, string $message): ChatMessage
    {
        return ChatMessage::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $conversation->session_id,
            'role' => 'user',
            'sender_type' => 'customer',
            'message' => $message,
            'message_type' => 'text',
            'status' => 'received',
        ]);
    }
}
