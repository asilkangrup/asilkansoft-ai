<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateCaseEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCaseLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateCaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_case_lifecycle_records_material_state_changes_without_followups(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 'case-lifecycle-seller');

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
                'notes' => 'Müşteri telefonu 05550000000, kişisel not.',
                'decision_intelligence' => [
                    'lead_score' => 55,
                    'lead_temperature' => 'warm',
                    'stage' => 'discovery',
                    'ready_for_valuation' => true,
                    'ready_for_match' => false,
                ],
                'opportunity_match_summary' => [
                    'count' => 0,
                    'strongest_grade' => null,
                ],
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 70,
        ]);

        $service = app(RealEstateCaseLifecycleService::class);

        $this->assertDatabaseHas('real_estate_case_events', [
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'real_estate_profile_id' => $profile->id,
            'event_type' => 'case_opened',
            'stage' => 'discovery',
            'ready_for_match' => false,
        ]);

        $count = RealEstateCaseEvent::query()
            ->where('real_estate_profile_id', $profile->id)
            ->count();

        $service->sync($profile->fresh());

        $this->assertSame(
            $count,
            RealEstateCaseEvent::query()
                ->where('real_estate_profile_id', $profile->id)
                ->count()
        );

        $data = $profile->fresh()->data;
        $data['decision_intelligence'] = [
            'lead_score' => 82,
            'lead_temperature' => 'hot',
            'stage' => 'ready',
            'ready_for_valuation' => true,
            'ready_for_match' => true,
        ];
        $data['opportunity_match_summary'] = [
            'count' => 2,
            'strongest_grade' => 'strong',
        ];

        $profile->update([
            'data' => $data,
            'valuation' => [
                'market_min' => 4700000,
                'market_max' => 5200000,
                'quick_sale_max' => 4900000,
                'investor_buy_max' => 4800000,
            ],
        ]);

        $latest = RealEstateCaseEvent::query()
            ->where('real_estate_profile_id', $profile->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('match_ready', $latest->event_type);
        $this->assertSame('ready', $latest->stage);
        $this->assertSame(82, $latest->lead_score);
        $this->assertSame('hot', $latest->lead_temperature);
        $this->assertTrue($latest->ready_for_match);
        $this->assertTrue($latest->valuation_present);
        $this->assertSame(2, $latest->match_count);
        $this->assertSame('strong', $latest->strongest_match_grade);
        $this->assertFalse($latest->metadata['guardrails']['schedules_follow_up']);
        $this->assertFalse($latest->metadata['guardrails']['sends_outbound_message']);

        $serialized = json_encode($latest->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('05550000000', $serialized);
        $this->assertStringNotContainsString('4500000', $serialized);
        $this->assertStringNotContainsString('kişisel not', $serialized);

        $data = $profile->fresh()->data;
        $data['decision_intelligence']['ready_for_match'] = false;
        $profile->update(['data' => $data]);

        $this->assertSame(
            'match_blocked',
            RealEstateCaseEvent::query()
                ->where('real_estate_profile_id', $profile->id)
                ->latest('id')
                ->value('event_type')
        );

        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);
    }

    public function test_foreign_organization_with_same_user_and_bot_cannot_enter_case_ledger(): void
    {
        $bot = $this->seedIsolatedAccount();

        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'foreign-real-estate-case-org',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 38,
            'ai_bot_id' => $bot->id,
            'session_id' => 'foreign-case-lifecycle',
            'whatsapp_number' => '905559999998',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'decision_intelligence' => [
                    'lead_score' => 90,
                    'lead_temperature' => 'hot',
                    'stage' => 'ready',
                    'ready_for_valuation' => true,
                    'ready_for_match' => true,
                ],
            ],
            'valuation' => ['market_min' => 1000000],
            'completeness_score' => 90,
            'confidence_score' => 90,
        ]);

        $event = app(RealEstateCaseLifecycleService::class)
            ->sync($profile->fresh());

        $this->assertNull($event);
        $this->assertSame(0, RealEstateCaseEvent::query()->count());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'case-lifecycle@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-case-lifecycle',
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

    private function conversation(AiBot $bot, string $sessionId): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => $sessionId,
            'whatsapp_number' => '905551234567',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }
}
