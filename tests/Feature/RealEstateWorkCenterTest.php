<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakIsMerkezi;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateWorkCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_mobile_operator_call_task_without_leaking_private_floor(): void
    {
        $this->seedAccount();

        $sellerConversation = $this->conversation('seller-work', '905550000001', 'Satıcı');
        $investorConversation = $this->conversation('investor-work', '905550000002', 'Ahmet Bey');

        $investor = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => ['preferred_locations' => ['Marmaris']],
            'valuation' => [],
        ]));

        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'location' => 'Marmaris Hisarönü',
                'property_type' => 'arsa',
                'area_sqm' => 1200,
                'private_seller_floor' => 2100000,
                'investor_offer_handoff_intelligence' => [
                    'ready_for_operator_handoff' => true,
                    'candidate_refs' => [[
                        'investor_profile_id' => $investor->id,
                        'match_score' => 91,
                        'grade' => 'strong',
                    ]],
                ],
            ],
            'valuation' => [],
        ]));

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('investor', $task['kind']);
        $this->assertSame('Ahmet Bey', $task['target_name']);
        $this->assertStringContainsString('Marmaris Hisarönü arsa', $task['script']);
        $this->assertStringNotContainsString('2100000', $task['script']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    private function conversation(string $session, string $phone, string $name): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $session,
            'whatsapp_number' => $phone,
            'customer_name' => $name,
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedAccount(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'work-center@example.test',
            'password' => Hash::make('test-password'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'work-center',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        AiBot::query()->forceCreate([
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
