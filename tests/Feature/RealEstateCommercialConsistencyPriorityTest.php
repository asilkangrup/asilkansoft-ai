<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateCommercialConsistencyGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateCommercialConsistencyPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_fact_confirmation_stays_customer_priority_while_commercial_conflict_still_blocks_matching(): void
    {
        $bot = $this->seedIsolatedAccount();
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => $bot->id,
            'session_id' => 'commercial-priority',
            'whatsapp_number' => '905551234567',
            'tags' => ['business:real_estate_seller'],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_best_action' => 'İlçe bilgisini netleştirelim.',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
        $profile = RealEstateProfile::withoutEvents(fn (): RealEstateProfile =>
            RealEstateProfile::query()->forceCreate([
                'conversation_control_id' => $conversation->id,
                'user_id' => 40,
                'ai_bot_id' => 35,
                'profile_type' => 'seller',
                'data' => [
                    'asking_price' => 4_000_000,
                    'minimum_price' => 4_500_000,
                    'fact_consistency_intelligence' => [
                        'status' => 'confirmation_required',
                        'pending_count' => 1,
                        'highest_priority_field' => 'district',
                        'confirmation_question' => 'İlçe bilgisini netleştirelim.',
                    ],
                    'next_best_action_intelligence' => [
                        'action_code' => 'confirm_property_fact_conflict',
                        'priority' => 'high',
                        'action_text' => 'İlçe bilgisini netleştirelim.',
                        'single_question' => 'İlçe bilgisini netleştirelim.',
                        'blocking' => true,
                        'reason_codes' => ['conflict_district'],
                        'match_count' => 1,
                    ],
                    'opportunity_matches' => [[
                        'candidate_profile_id' => 99,
                        'match_score' => 91,
                    ]],
                    'investor_offer_handoff_intelligence' => [
                        'status' => 'ready',
                        'ready_for_operator_handoff' => true,
                        'checks' => ['authorization_ready' => true],
                        'guardrails' => ['investor_presentation_export_allowed' => true],
                    ],
                ],
                'valuation' => [],
                'completeness_score' => 90,
                'confidence_score' => 90,
            ])
        );

        $summary = app(RealEstateCommercialConsistencyGuardService::class)->sync($profile);
        $data = $profile->fresh()->data;

        $this->assertSame('confirmation_required', $summary['status']);
        $this->assertSame([], $data['opportunity_matches']);
        $this->assertSame(
            'commercial_confirmation_required',
            $data['investor_offer_handoff_intelligence']['status']
        );
        $this->assertFalse(
            $data['investor_offer_handoff_intelligence']['ready_for_operator_handoff']
        );
        $this->assertSame(
            'confirm_property_fact_conflict',
            $data['next_best_action_intelligence']['action_code']
        );
        $this->assertSame(
            'İlçe bilgisini netleştirelim.',
            $conversation->fresh()->next_best_action
        );
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    private function seedIsolatedAccount(): AiBot
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'commercial-priority@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'commercial-priority-isolated',
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
}
