<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakTeklifler;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\CrmActivity;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOfferPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_pipeline_groups_pair_history_and_points_to_the_next_party(): void
    {
        $this->seedAccount();
        $sellerConversation = $this->conversation('pipeline-seller', 'Satıcı');
        $investorConversation = $this->conversation('pipeline-investor', 'Yatırımcı');
        $seller = $this->profile($sellerConversation, 'seller', [
            'location' => 'Marmaris Hisarönü',
            'property_type' => 'arsa',
            'private_seller_floor' => 2100000,
        ]);
        $investor = $this->profile($investorConversation, 'investor', []);

        $this->activity($investorConversation, $seller, $investor, 'investor', 'Teklif verdi', 3000000);
        $this->activity($sellerConversation, $seller, $investor, 'seller_offer', 'Karşı teklif', 3400000);

        $page = new EmlakTeklifler;
        $deal = $page->getDealsProperty()->first();

        $this->assertSame('waiting_investor', $deal['stage']);
        $this->assertSame('Yatırımcı cevabı bekleniyor', $deal['stage_label']);
        $this->assertSame(3400000, $deal['amount']);
        $this->assertCount(2, $deal['timeline']);
        $this->assertStringNotContainsString('2100000', json_encode($deal));
        $this->assertSame(1, $page->getStatsProperty()['waiting_investor']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    private function activity(ConversationControl $conversation, RealEstateProfile $seller, RealEstateProfile $investor, string $kind, string $outcome, ?int $amount): void
    {
        CrmActivity::query()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'conversation_control_id' => $conversation->id,
            'type' => 'real_estate_operator_call',
            'title' => 'Operatör görüşmesi',
            'description' => 'Güvenli test kaydı',
            'new_value' => $outcome,
            'meta' => [
                'scope' => 'isolated_real_estate',
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'contact_profile_id' => $kind === 'seller_offer' ? $seller->id : $investor->id,
                'task_kind' => $kind,
                'outcome' => $outcome,
                'offer_amount' => $amount,
                'follow_up_scheduling_allowed' => false,
                'automatic_outbound_allowed' => false,
                'contains_private_seller_floor' => false,
            ],
        ]);
    }

    private function profile(ConversationControl $conversation, string $type, array $data): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => $type,
            'data' => $data,
            'valuation' => [],
        ]));
    }

    private function conversation(string $session, string $name): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => 40, 'organization_id' => 37, 'ai_bot_id' => 35,
            'session_id' => $session, 'whatsapp_number' => '905550000000',
            'customer_name' => $name, 'tags' => [], 'lead_status' => 'new',
            'lead_score' => 0, 'lead_temperature' => 'cold',
            'next_follow_up_at' => null, 'human_takeover' => false,
        ]);
    }

    private function seedAccount(): void
    {
        User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'offer-pipeline@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'offer-pipeline','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
    }
}
