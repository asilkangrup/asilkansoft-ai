<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateConversationHandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateConversationHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_whatsapp_activity_defers_operator_contact_until_thirty_minutes_idle(): void
    {
        $conversation = $this->seedIsolatedConversation('handoff-active');
        $this->seller($conversation);

        $message = ChatMessage::withoutEvents(fn () => ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$conversation->session_id,'role'=>'user','sender_type'=>'customer',
            'message'=>'Arsamı acil satmak istiyorum','message_type'=>'text','status'=>'received',
        ]));

        $service = app(RealEstateConversationHandoffService::class);
        $active = $service->status($conversation);

        $this->assertTrue($active['supported']);
        $this->assertTrue($active['active_whatsapp_chat']);
        $this->assertFalse($active['operator_contact_eligible']);
        $this->assertSame(30, $active['idle_threshold_minutes']);
        $this->assertSame('whatsapp_active', $active['reason']);

        $message->forceFill([
            'created_at'=>now()->subMinutes(31),
            'updated_at'=>now()->subMinutes(31),
        ])->saveQuietly();

        $idle = $service->status($conversation->fresh());

        $this->assertFalse($idle['active_whatsapp_chat']);
        $this->assertTrue($idle['operator_contact_eligible']);
        $this->assertGreaterThanOrEqual(31, $idle['idle_minutes']);
        $this->assertSame('whatsapp_idle', $idle['reason']);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_no_whatsapp_history_is_eligible_but_foreign_scope_fails_closed(): void
    {
        $conversation = $this->seedIsolatedConversation('handoff-no-history');
        $this->seller($conversation);

        $service = app(RealEstateConversationHandoffService::class);
        $status = $service->status($conversation);

        $this->assertTrue($status['operator_contact_eligible']);
        $this->assertSame('no_whatsapp_history', $status['reason']);

        $foreign = new ConversationControl;
        $foreign->forceFill([
            'user_id'=>40,
            'organization_id'=>999,
            'ai_bot_id'=>35,
            'session_id'=>'foreign-handoff',
        ]);

        $foreignStatus = $service->status($foreign);
        $this->assertFalse($foreignStatus['supported']);
        $this->assertFalse($foreignStatus['operator_contact_eligible']);
        $this->assertSame('out_of_scope', $foreignStatus['reason']);
    }

    public function test_telemetry_counts_only_isolated_profile_conversations(): void
    {
        $activeConversation = $this->seedIsolatedConversation('handoff-telemetry-active');
        $this->seller($activeConversation);
        ChatMessage::withoutEvents(fn () => ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$activeConversation->session_id,'role'=>'assistant','sender_type'=>'ai',
            'message'=>'Konumu da paylaşabilir misiniz?','message_type'=>'text','status'=>'sent',
        ]));

        $idleConversation = ConversationControl::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,'session_id'=>'handoff-telemetry-idle',
            'whatsapp_number'=>'905550000002','tags'=>[],'lead_status'=>'new','lead_score'=>0,
            'lead_temperature'=>'cold','next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
        $this->seller($idleConversation);
        ChatMessage::withoutEvents(fn () => ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$idleConversation->session_id,'role'=>'user','sender_type'=>'customer',
            'message'=>'Tamam','message_type'=>'text','status'=>'received',
            'created_at'=>now()->subMinutes(45),'updated_at'=>now()->subMinutes(45),
        ]));

        $telemetry = app(RealEstateConversationHandoffService::class)->telemetry();

        $this->assertSame(2, $telemetry['scoped_conversations']);
        $this->assertSame(1, $telemetry['active_whatsapp_chats']);
        $this->assertSame(1, $telemetry['operator_contact_eligible']);
        $this->assertFalse($telemetry['follow_up_scheduling_allowed']);
        $this->assertFalse($telemetry['automatic_outbound_allowed']);
    }

    private function seller(ConversationControl $conversation): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id'=>$conversation->id,'user_id'=>40,'ai_bot_id'=>35,
            'profile_type'=>'seller','data'=>['property_type'=>'arsa'],'valuation'=>[],
        ]));
    }

    private function seedIsolatedConversation(string $session): ConversationControl
    {
        if (! User::query()->whereKey(40)->exists()) {
            User::query()->forceCreate([
                'id'=>40,'name'=>'Emlak AI','email'=>'handoff@example.test','password'=>Hash::make('test'),
            ]);
            Organization::query()->forceCreate([
                'id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'handoff','plan'=>'start',
                'seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active',
            ]);
            AiBot::query()->forceCreate([
                'id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul',
                'openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate',
                'lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35',
                'follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true,
            ]);
        }

        return ConversationControl::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,'session_id'=>$session,
            'whatsapp_number'=>'905550000001','tags'=>[],'lead_status'=>'new','lead_score'=>0,
            'lead_temperature'=>'cold','next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }
}
