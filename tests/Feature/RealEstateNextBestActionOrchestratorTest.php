<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateNextBestActionEvent;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateNextBestActionDecisionBridgeService;
use App\Services\RealEstateNextBestActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateNextBestActionOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_core_gap_becomes_one_deterministic_question_and_is_idempotent(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'nba-seller-core');
        $profile = $this->profileQuietly($conversation, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'asking_price' => 5_000_000,
            'urgency' => 'medium',
            'timeline' => '30 gün',
            'decision_intelligence' => [
                'stage' => 'discovery',
                'missing_critical_data' => ['area_sqm'],
                'ready_for_match' => false,
                'next_best_action' => 'Eski aksiyon',
            ],
        ]);

        $service = app(RealEstateNextBestActionService::class);
        $first = $service->process($conversation->fresh());
        $second = $service->process($conversation->fresh());

        $this->assertSame('complete_seller_core_data', $first['action_code']);
        $this->assertSame('high', $first['priority']);
        $this->assertTrue($first['blocking']);
        $this->assertSame('Net veya yaklaşık m² bilgisini sor.', $first['single_question']);
        $this->assertSame($first['updated_at'], $second['updated_at']);
        $this->assertSame(1, RealEstateNextBestActionEvent::query()->count());
        $this->assertSame($first['action_text'], $conversation->fresh()->next_best_action);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse((bool) $conversation->fresh()->human_takeover);

        app(RealEstateNextBestActionDecisionBridgeService::class)
            ->sync($conversation->fresh());
        $decision = $profile->fresh()->data['decision_intelligence'];
        $this->assertSame('complete_seller_core_data', $decision['orchestrated_action_code']);
        $this->assertSame('high', $decision['orchestrated_action_priority']);
        $this->assertTrue($decision['orchestrated_action_blocking']);
        $this->assertSame(
            'Net veya yaklaşık m² bilgisini sor.',
            $decision['orchestrated_single_question']
        );
    }

    public function test_stale_or_missing_valuation_blocks_price_and_matching_before_verification(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'nba-seller-valuation');
        $profile = $this->profileQuietly($conversation, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 5_000_000,
            'urgency' => 'high',
            'timeline' => 'bu ay',
            'block_no' => '123',
            'parcel_no' => '45',
            'seller_motivation_intelligence' => [
                'recommended_next_question' => null,
                'guardrails' => [
                    'follow_up_scheduling_allowed' => false,
                ],
            ],
            'decision_intelligence' => [
                'stage' => 'qualified',
                'missing_critical_data' => [],
                'ready_for_match' => false,
                'valuation_research_needed' => true,
                'valuation_integrity_needed' => true,
                'verification_status' => 'unverified',
                'evidence_sufficient_for_matching' => false,
                'next_best_action' => 'Doğrulama yap',
            ],
        ]);

        $plan = app(RealEstateNextBestActionService::class)
            ->process($conversation->fresh());

        $this->assertSame('refresh_valuation_research', $plan['action_code']);
        $this->assertTrue($plan['blocking']);
        $this->assertContains('valuation_missing_or_stale', $plan['reason_codes']);
        $this->assertStringContainsString('Güncel emsal', $plan['action_text']);
        $this->assertNull($plan['single_question']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_investor_mandate_gap_is_prioritized_as_one_question(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation(
            $bot,
            37,
            'nba-investor-gap',
            ['business:real_estate_investor']
        );
        $this->profileQuietly($conversation, 'investor', [
            'city' => 'Muğla',
            'property_type' => 'arsa',
            'budget_max' => 5_000_000,
            'decision_intelligence' => [
                'stage' => 'qualified',
                'missing_critical_data' => ['investment_goal', 'timeline'],
                'ready_for_match' => true,
                'next_best_action' => 'Portföyleri sırala',
            ],
        ]);

        $plan = app(RealEstateNextBestActionService::class)
            ->process($conversation->fresh());

        $this->assertSame('complete_investor_mandate', $plan['action_code']);
        $this->assertSame('high', $plan['priority']);
        $this->assertTrue($plan['blocking']);
        $this->assertNotNull($plan['single_question']);
        $this->assertStringContainsString('ilçe', mb_strtolower($plan['single_question']));
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_ready_seller_with_safe_match_gets_cautious_match_review_without_private_floor_leak(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = $this->conversation($bot, 37, 'nba-seller-match');
        $profile = $this->profileQuietly($conversation, 'seller', [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'district' => 'Marmaris',
            'area_sqm' => 1200,
            'asking_price' => 5_000_000,
            'minimum_price' => 4_250_000,
            'urgency' => 'medium',
            'timeline' => '30 gün',
            'block_no' => '123',
            'parcel_no' => '45',
            'phone' => '05550000000',
            'email' => 'seller@example.test',
            'seller_motivation_intelligence' => [
                'recommended_next_question' => null,
                'guardrails' => [
                    'follow_up_scheduling_allowed' => false,
                ],
            ],
            'opportunity_matches' => [
                [
                    'candidate_role' => 'investor',
                    'match_score' => 91,
                    'grade' => 'strong',
                ],
            ],
            'decision_intelligence' => [
                'stage' => 'ready',
                'missing_critical_data' => [],
                'ready_for_match' => true,
                'valuation_research_needed' => false,
                'valuation_integrity_needed' => false,
                'verification_status' => 'verified',
                'evidence_sufficient_for_matching' => true,
                'next_best_action' => 'Eski eşleşme aksiyonu',
            ],
        ]);

        $plan = app(RealEstateNextBestActionService::class)
            ->process($conversation->fresh());

        $this->assertSame('review_safe_match_candidates', $plan['action_code']);
        $this->assertSame(1, $plan['match_count']);
        $this->assertFalse($plan['blocking']);
        $this->assertStringContainsString('hazır alıcı', $plan['action_text']);
        $this->assertStringContainsString('kesin teklif', $plan['action_text']);
        $this->assertFalse($plan['guardrails']['seller_private_floor_may_be_disclosed_to_investor']);
        $this->assertFalse($plan['guardrails']['follow_up_scheduling_allowed']);

        $eventJson = json_encode(
            RealEstateNextBestActionEvent::query()->firstOrFail()->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $this->assertStringNotContainsString('4250000', (string) $eventJson);
        $this->assertStringNotContainsString('05550000000', (string) $eventJson);
        $this->assertStringNotContainsString('seller@example.test', (string) $eventJson);
        $this->assertStringNotContainsString('Eski eşleşme aksiyonu', (string) $eventJson);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_same_user_and_bot_in_foreign_organization_fail_closed_without_ledger_write(): void
    {
        $bot = $this->seedIsolatedAccount();
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 40,
            'name' => 'Foreign Org',
            'slug' => 'nba-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $conversation = $this->conversation($bot, 37, 'nba-foreign-scope');
        DB::table('conversation_controls')
            ->where('id', $conversation->id)
            ->update(['organization_id' => 38]);
        $conversation->refresh();

        $this->profileQuietly($conversation, 'investor', [
            'city' => 'Muğla',
            'property_type' => 'arsa',
            'budget_max' => 5_000_000,
            'decision_intelligence' => [
                'stage' => 'ready',
                'missing_critical_data' => [],
                'ready_for_match' => true,
                'next_best_action' => 'Should never run',
            ],
        ]);

        $result = app(RealEstateNextBestActionService::class)
            ->process($conversation);

        $this->assertNull($result);
        $this->assertSame(0, RealEstateNextBestActionEvent::query()->count());
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function profileQuietly(
        ConversationControl $conversation,
        string $profileType,
        array $data,
    ): RealEstateProfile {
        return RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => $profileType,
                'data' => $data,
                'valuation' => [],
                'completeness_score' => 80,
                'confidence_score' => 80,
            ])
        );
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'nba@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'nba-isolated',
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
        int $organizationId,
        string $sessionId,
        array $tags = ['business:real_estate_seller']
    ): ConversationControl {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => $organizationId,
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
}
