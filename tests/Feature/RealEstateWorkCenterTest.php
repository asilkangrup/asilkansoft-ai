<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakIsMerkezi;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\CrmActivity;
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
        $investor = $this->investor($investorConversation);
        $seller = $this->seller($sellerConversation, [[
            'investor_profile_id' => $investor->id,
            'match_score' => 91,
            'grade' => 'strong',
        ]]);

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('investor', $task['kind']);
        $this->assertSame('Ahmet Bey', $task['target_name']);
        $this->assertSame($investor->id, $task['investor_profile_id']);
        $this->assertStringContainsString('Marmaris Hisarönü arsa', $task['script']);
        $this->assertStringNotContainsString('2100000', $task['script']);
        $this->assertStringNotContainsString('2.100.000', $task['script']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
        $this->assertSame($seller->id, $task['seller_profile_id']);
    }

    public function test_active_whatsapp_chat_waits_for_thirty_minutes_of_silence_before_call_task(): void
    {
        $this->seedAccount();

        $sellerConversation = $this->conversation('seller-idle-gate', '905550000031', 'Satıcı');
        $investorConversation = $this->conversation('investor-idle-gate', '905550000032', 'Aktif Yatırımcı');
        $investor = $this->investor($investorConversation);
        $this->seller($sellerConversation, [[
            'investor_profile_id' => $investor->id,
            'match_score' => 92,
            'grade' => 'strong',
        ]]);

        $message = ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$investorConversation->session_id,
            'role'=>'user','sender_type'=>'customer','message'=>'Detayları gönderir misiniz?',
            'message_type'=>'text','status'=>'received',
        ]);

        $this->assertCount(0, (new EmlakIsMerkezi)->getTasksProperty());

        $message->forceFill([
            'created_at'=>now()->subMinutes(31),
            'updated_at'=>now()->subMinutes(31),
        ])->saveQuietly();

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('investor', $task['kind']);
        $this->assertSame('Aktif Yatırımcı', $task['target_name']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    public function test_unsuitable_investor_is_skipped_and_next_candidate_is_recommended(): void
    {
        $this->seedAccount();

        $sellerConversation = $this->conversation('seller-skip', '905550000011', 'Satıcı');
        $firstConversation = $this->conversation('investor-skip-a', '905550000012', 'Birinci Yatırımcı');
        $secondConversation = $this->conversation('investor-skip-b', '905550000013', 'İkinci Yatırımcı');
        $first = $this->investor($firstConversation);
        $second = $this->investor($secondConversation);
        $seller = $this->seller($sellerConversation, [
            [
                'investor_profile_id' => $first->id,
                'match_score' => 96,
                'grade' => 'strong',
            ],
            [
                'investor_profile_id' => $second->id,
                'match_score' => 84,
                'grade' => 'good',
            ],
        ]);

        $this->operatorActivity(
            conversation: $firstConversation,
            seller: $seller,
            investor: $first,
            kind: 'investor',
            outcome: 'Uygun değil',
        );

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('investor', $task['kind']);
        $this->assertSame('İkinci Yatırımcı', $task['target_name']);
        $this->assertSame($second->id, $task['investor_profile_id']);
        $this->assertSame(84, $task['match_score']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($firstConversation->fresh()->next_follow_up_at);
        $this->assertNull($secondConversation->fresh()->next_follow_up_at);
    }

    public function test_offer_and_counter_offer_results_drive_the_next_person_to_call(): void
    {
        $this->seedAccount();

        $sellerConversation = $this->conversation('seller-offer-flow', '905550000021', 'Mehmet Satıcı');
        $investorConversation = $this->conversation('investor-offer-flow', '905550000022', 'Ayşe Yatırımcı');
        $investor = $this->investor($investorConversation);
        $seller = $this->seller($sellerConversation, [[
            'investor_profile_id' => $investor->id,
            'match_score' => 93,
            'grade' => 'strong',
        ]]);

        $investorOffer = $this->operatorActivity(
            conversation: $investorConversation,
            seller: $seller,
            investor: $investor,
            kind: 'investor',
            outcome: 'Teklif verdi',
            amount: 3_000_000,
        );

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('seller_offer', $task['kind']);
        $this->assertSame('Mehmet Satıcı', $task['target_name']);
        $this->assertSame(3_000_000, $task['offer_amount']);
        $this->assertStringContainsString('3.000.000 TL', $task['script']);

        $sellerCounter = $this->operatorActivity(
            conversation: $sellerConversation,
            seller: $seller,
            investor: $investor,
            kind: 'seller_offer',
            outcome: 'Karşı teklif',
            amount: 3_400_000,
        );
        $this->assertGreaterThan($investorOffer->id, $sellerCounter->id);

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('investor_counter', $task['kind']);
        $this->assertSame('Ayşe Yatırımcı', $task['target_name']);
        $this->assertSame(3_400_000, $task['offer_amount']);
        $this->assertStringContainsString('3.400.000 TL', $task['script']);

        $counterAccepted = $this->operatorActivity(
            conversation: $investorConversation,
            seller: $seller,
            investor: $investor,
            kind: 'investor_counter',
            outcome: 'Kabul etti',
        );
        $this->assertGreaterThan($sellerCounter->id, $counterAccepted->id);

        $task = (new EmlakIsMerkezi)->getTasksProperty()->first();

        $this->assertSame('seller_final', $task['kind']);
        $this->assertSame('Mehmet Satıcı', $task['target_name']);
        $this->assertStringContainsString('kabul edildi', mb_strtolower($task['script']));
        $this->assertStringNotContainsString('2100000', $task['script']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    private function operatorActivity(
        ConversationControl $conversation,
        RealEstateProfile $seller,
        RealEstateProfile $investor,
        string $kind,
        string $outcome,
        ?int $amount = null,
    ): CrmActivity {
        return CrmActivity::query()->create([
            'user_id' => 40,
            'ai_bot_id' => 35,
            'conversation_control_id' => $conversation->id,
            'performed_by_user_id' => null,
            'type' => 'real_estate_operator_call',
            'title' => 'Test operatör görüşmesi',
            'description' => 'PII-safe test event',
            'old_value' => null,
            'new_value' => $outcome,
            'meta' => [
                'scope' => 'isolated_real_estate',
                'seller_profile_id' => $seller->id,
                'investor_profile_id' => $investor->id,
                'contact_profile_id' => $kind === 'seller_offer' || $kind === 'seller_final'
                    ? $seller->id
                    : $investor->id,
                'task_kind' => $kind,
                'outcome' => $outcome,
                'offer_amount' => $amount,
                'follow_up_scheduling_allowed' => false,
                'automatic_outbound_allowed' => false,
                'contains_private_seller_floor' => false,
            ],
        ]);
    }

    private function investor(ConversationControl $conversation): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'investor',
            'data' => ['preferred_locations' => ['Marmaris']],
            'valuation' => [],
        ]));
    }

    private function seller(ConversationControl $conversation, array $candidates): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'location' => 'Marmaris Hisarönü',
                'property_type' => 'arsa',
                'area_sqm' => 1200,
                'private_seller_floor' => 2_100_000,
                'investor_offer_handoff_intelligence' => [
                    'ready_for_operator_handoff' => true,
                    'candidate_refs' => $candidates,
                ],
            ],
            'valuation' => [],
        ]));
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
