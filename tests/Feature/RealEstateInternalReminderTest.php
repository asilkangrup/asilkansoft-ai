<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakIsMerkezi;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\CrmActivity;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateInternalReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_call_creates_internal_reminder_without_whatsapp_follow_up(): void
    {
        $user = $this->seedAccount();
        $sellerConversation = $this->conversation('reminder-seller', 'Satıcı');
        $investorConversation = $this->conversation('reminder-investor', 'Yatırımcı');
        $investor = $this->profile($investorConversation, 'investor', ['preferred_locations'=>['Marmaris']]);
        $seller = $this->profile($sellerConversation, 'seller', [
            'location'=>'Marmaris','property_type'=>'arsa',
            'investor_offer_handoff_intelligence'=>[
                'ready_for_operator_handoff'=>true,
                'candidate_refs'=>[['investor_profile_id'=>$investor->id,'match_score'=>90,'grade'=>'strong']],
            ],
        ]);

        $this->actingAs($user);
        $page = new EmlakIsMerkezi;
        $task = $page->getTasksProperty()->first();
        $due = now()->addDay()->startOfHour();

        $page->callResults[$task['key']] = 'Tekrar ara';
        $page->reminderDates[$task['key']] = $due->format('Y-m-d\TH:i');
        $page->saveCall($task['key']);

        $activity = CrmActivity::query()->where('type','real_estate_operator_call')->latest('id')->firstOrFail();
        $this->assertSame($seller->id, $activity->meta['seller_profile_id']);
        $this->assertSame('Tekrar ara', $activity->meta['outcome']);
        $this->assertTrue($activity->meta['operator_reminder_internal_only']);
        $this->assertFalse($activity->meta['follow_up_scheduling_allowed']);
        $this->assertFalse($activity->meta['automatic_outbound_allowed']);
        $this->assertNotNull($activity->meta['operator_reminder_at']);
        $this->assertNull($sellerConversation->fresh()->next_follow_up_at);
        $this->assertNull($investorConversation->fresh()->next_follow_up_at);
    }

    private function profile(ConversationControl $conversation, string $type, array $data): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,
            'profile_type'=>$type,'data'=>$data,'valuation'=>[],
        ]));
    }

    private function conversation(string $session, string $name): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,'session_id'=>$session,
            'whatsapp_number'=>'905550000009','customer_name'=>$name,'tags'=>[],
            'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }

    private function seedAccount(): User
    {
        $user = User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'internal-reminder@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'internal-reminder','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        $user->organizations()->syncWithoutDetaching([37=>['role'=>'owner','status'=>'active','joined_at'=>now()]]);
        AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        return $user;
    }
}
