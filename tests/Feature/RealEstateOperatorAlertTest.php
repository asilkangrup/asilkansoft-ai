<?php

namespace Tests\Feature;

use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateOperatorAlert;
use App\Models\RealEstateProfile;
use App\Models\User;
use App\Services\RealEstateOperatorAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateOperatorAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_risk_alert_is_not_created_for_verification_conflict(): void
    {
        [$bot, $conversation] = $this->seedIsolatedAccount('operator-risk-disabled');

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'verification_intelligence' => [
                    'status' => 'blocked',
                    'risk_score' => 92,
                    'safe_to_match' => false,
                    'next_best_action' => 'İlçe çelişkisini netleştir.',
                    'conflicts' => [['field' => 'district', 'severity' => 'critical']],
                ],
            ],
            'valuation' => [],
            'completeness_score' => 70,
            'confidence_score' => 65,
        ]);

        $this->assertDatabaseMissing('real_estate_operator_alerts', [
            'real_estate_profile_id' => $profile->id,
            'type' => 'verification_risk',
            'status' => 'open',
        ]);
        $this->assertFalse($conversation->fresh()->human_takeover);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertSame(35, $bot->id);
    }

    public function test_hot_seller_opens_hot_lead_and_valuation_attention_without_customer_follow_up(): void
    {
        [, $conversation] = $this->seedIsolatedAccount('operator-hot');
        $conversation->update([
            'lead_score' => 91,
            'lead_temperature' => 'hot',
            'lead_status' => 'qualified',
            'next_best_action' => 'Satıcıyla fiyat esnekliğini netleştir.',
            'next_follow_up_at' => null,
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 4500000,
                'decision_intelligence' => [
                    'lead_score' => 91,
                    'lead_temperature' => 'hot',
                    'stage' => 'ready',
                    'ready_for_match' => true,
                    'next_best_action' => 'Satıcıyla fiyat esnekliğini netleştir.',
                ],
            ],
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 80,
        ]);

        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id' => $profile->id,
            'type' => 'hot_lead',
            'severity' => 'high',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id' => $profile->id,
            'type' => 'valuation_attention',
            'severity' => 'high',
            'status' => 'open',
        ]);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertFalse($conversation->fresh()->human_takeover);
    }

    public function test_active_whatsapp_chat_resolves_contact_alert_until_idle_without_hiding_internal_attention(): void
    {
        [, $conversation] = $this->seedIsolatedAccount('operator-active-chat');
        $conversation->update([
            'lead_score' => 93,
            'lead_temperature' => 'hot',
            'lead_status' => 'qualified',
            'next_follow_up_at' => null,
        ]);

        $profile = RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [
                'property_type' => 'arsa',
                'city' => 'Muğla',
                'district' => 'Marmaris',
                'asking_price' => 4200000,
                'decision_intelligence' => [
                    'lead_score' => 93,
                    'lead_temperature' => 'hot',
                    'stage' => 'ready',
                    'ready_for_match' => true,
                ],
            ],
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 80,
        ]);

        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id'=>$profile->id,'type'=>'hot_lead','status'=>'open',
        ]);

        $message = ChatMessage::withoutEvents(fn () => ChatMessage::query()->forceCreate([
            'user_id'=>40,'organization_id'=>37,'ai_bot_id'=>35,
            'session_id'=>$conversation->session_id,'role'=>'assistant','sender_type'=>'ai',
            'message'=>'Tapu görselini de gönderir misiniz?','message_type'=>'text','status'=>'sent',
        ]));

        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());

        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id'=>$profile->id,'type'=>'hot_lead','status'=>'resolved',
        ]);
        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id'=>$profile->id,'type'=>'valuation_attention','status'=>'open',
        ]);

        $message->forceFill([
            'created_at'=>now()->subMinutes(31),
            'updated_at'=>now()->subMinutes(31),
        ])->saveQuietly();

        app(RealEstateOperatorAlertService::class)->sync($profile->fresh());

        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'real_estate_profile_id'=>$profile->id,'type'=>'hot_lead','status'=>'open',
        ]);
        $this->assertNull($conversation->fresh()->next_follow_up_at);
    }

    public function test_historical_person_risk_alert_is_resolved_on_sync(): void
    {
        [, $conversation] = $this->seedIsolatedAccount('operator-resolve');

        $profile = RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => [],
            'valuation' => [],
            'completeness_score' => 50,
            'confidence_score' => 50,
        ]));

        RealEstateOperatorAlert::query()->create([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'conversation_control_id' => $conversation->id,
            'real_estate_profile_id' => $profile->id,
            'alert_key' => 'verification_risk:'.$profile->id,
            'type' => 'verification_risk',
            'severity' => 'critical',
            'status' => 'open',
            'title' => 'Eski risk kaydı',
            'message' => 'Eski risk kaydı',
            'payload' => [],
            'opened_at' => now(),
        ]);

        app(RealEstateOperatorAlertService::class)->sync($profile);

        $this->assertDatabaseHas('real_estate_operator_alerts', [
            'alert_key' => 'verification_risk:'.$profile->id,
            'status' => 'resolved',
        ]);
    }

    public function test_out_of_scope_profile_never_creates_operator_alerts(): void
    {
        $other = User::query()->create([
            'name' => 'Other',
            'email' => 'operator-other@example.test',
            'password' => Hash::make('test-password'),
        ]);
        $bot = AiBot::query()->create([
            'user_id' => $other->id,
            'name' => 'Other Bot',
            'company_name' => 'Other',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
        ]);
        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => $other->id,
            'organization_id' => null,
            'ai_bot_id' => $bot->id,
            'session_id' => 'operator-other',
            'whatsapp_number' => '905559999999',
            'lead_status' => 'qualified',
            'lead_score' => 95,
            'lead_temperature' => 'hot',
            'tags' => [],
            'next_follow_up_at' => null,
        ]);

        RealEstateProfile::query()->create([
            'conversation_control_id' => $conversation->id,
            'user_id' => $other->id,
            'ai_bot_id' => $bot->id,
            'profile_type' => 'seller',
            'data' => [
                'decision_intelligence' => [
                    'lead_score' => 95,
                    'lead_temperature' => 'hot',
                ],
            ],
            'valuation' => [],
            'completeness_score' => 90,
            'confidence_score' => 90,
        ]);

        $this->assertDatabaseCount('real_estate_operator_alerts', 0);
    }

    private function seedIsolatedAccount(string $sessionId): array
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => $sessionId.'@example.test',
            'password' => Hash::make('test-password'),
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-ai-'.$sessionId,
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);

        $bot = AiBot::query()->forceCreate([
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

        $conversation = ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => $sessionId,
            'whatsapp_number' => '905551234567',
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'tags' => ['business:real_estate_seller'],
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        return [$bot, $conversation, $user];
    }
}
