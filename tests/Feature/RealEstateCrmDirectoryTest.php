<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakCrm;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateCrmDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_isolated_sellers_and_investors_with_safe_next_actions(): void
    {
        $this->seedIsolatedAccount();

        $sellerConversation = $this->conversation(40, 37, 35, 'seller-directory', 'Satıcı Ayşe');
        $investorConversation = $this->conversation(40, 37, 35, 'investor-directory', 'Yatırımcı Mehmet');

        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $sellerConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'location' => 'Marmaris Hisarönü',
                'property_type' => 'arsa',
                'asking_price' => 3500000,
                'private_seller_floor' => 2600000,
                'investor_offer_handoff_intelligence' => [
                    'status' => 'ready',
                    'ready_for_operator_handoff' => true,
                    'candidate_count' => 1,
                    'recommended_operator_action' => 'En güçlü yatırımcı adayını ara.',
                ],
            ],
            'valuation' => [],
            'completeness_score' => 92,
            'confidence_score' => 85,
        ]));

        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $investorConversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => [
                'preferred_locations' => ['Marmaris'],
                'preferred_property_types' => ['arsa'],
                'max_budget' => 3000000,
            ],
            'valuation' => [],
            'completeness_score' => 80,
            'confidence_score' => 80,
        ]));

        $page = new EmlakCrm;
        $this->assertSame(1, $page->getStatsProperty()['ready']);

        $portfolio = $page->getRecordsProperty()->first();
        $this->assertSame('Satıcı Ayşe', $portfolio['name']);
        $this->assertSame('Yatırımcıya hazır', $portfolio['status_label']);
        $this->assertSame(3500000, $portfolio['price']);
        $this->assertStringNotContainsString('2600000', json_encode($portfolio));

        $page->setTab('investors');
        $investor = $page->getRecordsProperty()->first();
        $this->assertSame('Yatırımcı Mehmet', $investor['name']);
        $this->assertSame('Aktif yatırımcı', $investor['status_label']);
        $this->assertSame(3000000, $investor['price']);
    }

    private function conversation(int $user, int $organization, int $bot, string $session, string $name): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => '90555'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'customer_name' => $name,
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedIsolatedAccount(): void
    {
        User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'crm-directory@example.test',
            'password' => Hash::make('test-password'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'crm-directory',
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
