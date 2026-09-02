<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakMusteriDetay;
use App\Models\AiBot;
use App\Models\ChatMessage;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RealEstateSellerNegotiationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_records_a_real_seller_counter_offer_without_outbound_or_follow_up(): void
    {
        $user = $this->seedIsolatedAccount();
        $conversation = $this->conversation(40, 37, 35, 'seller-negotiation');
        $profile = $this->profile($conversation, [
            'property_type' => 'arsa',
            'city' => 'Muğla',
            'area_sqm' => 1200,
            'asking_price' => 4_300_000,
            'minimum_price' => 3_200_000,
        ]);

        $this->actingAs($user);
        Livewire::withQueryParams(['customer' => $conversation->id])
            ->test(EmlakMusteriDetay::class)
            ->set('negotiationOutcome', 'counter_offer')
            ->set('negotiationAmount', '3500000')
            ->set('negotiationNote', 'Tapu kontrolünden sonra yetki verecek.')
            ->call('saveSellerNegotiation')
            ->assertHasNoErrors();

        $activity = $conversation->activities()
            ->where('type', 'real_estate_seller_negotiation')
            ->sole();

        $this->assertSame('counter_offer', data_get($activity->meta, 'outcome'));
        $this->assertSame(3_500_000, data_get($activity->meta, 'amount'));
        $this->assertTrue((bool) data_get($activity->meta, 'operator_entered'));
        $this->assertTrue((bool) data_get($activity->meta, 'seller_floor_is_confidential'));
        $this->assertFalse((bool) data_get($activity->meta, 'automatic_outbound_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'follow_up_scheduling_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'fake_offer_allowed'));
        $this->assertFalse((bool) data_get($activity->meta, 'price_guarantee_allowed'));
        $this->assertNull($conversation->fresh()->next_follow_up_at);
        $this->assertSame(0, ChatMessage::query()
            ->where('session_id', $conversation->session_id)
            ->whereIn('sender_type', ['ai', 'human'])
            ->count());

        $profile->refresh();
        $this->assertSame(
            'counter_offer',
            data_get($profile->data, 'seller_negotiation_workspace.last_outcome')
        );
        $this->assertSame(
            3_500_000,
            data_get($profile->data, 'seller_negotiation_workspace.last_amount')
        );

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $conversation->id;
        $workspace = $detail->getNegotiationWorkspaceProperty();

        $this->assertSame(3_200_000, $workspace['confidential_floor']);
        $this->assertFalse($workspace['valuation_ready']);
        $this->assertFalse($workspace['guardrails']['automatic_outbound_allowed']);
        $this->assertTrue($workspace['guardrails']['seller_floor_is_confidential']);
        $this->assertCount(1, $workspace['history']);
    }

    public function test_counter_offer_requires_amount_and_foreign_tenant_is_hidden(): void
    {
        $user = $this->seedIsolatedAccount();
        $conversation = $this->conversation(40, 37, 35, 'seller-negotiation-required');
        $this->profile($conversation, ['property_type' => 'arsa']);

        $this->actingAs($user);
        Livewire::withQueryParams(['customer' => $conversation->id])
            ->test(EmlakMusteriDetay::class)
            ->set('negotiationOutcome', 'counter_offer')
            ->set('negotiationAmount', null)
            ->call('saveSellerNegotiation')
            ->assertHasErrors(['negotiationAmount']);

        $this->assertSame(0, $conversation->activities()
            ->where('type', 'real_estate_seller_negotiation')
            ->count());

        $foreign = $this->conversation(41, 38, 36, 'foreign-negotiation');
        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $foreign->id,
            'user_id' => 41,
            'ai_bot_id' => 36,
            'profile_type' => 'seller',
            'data' => ['minimum_price' => 1_000_000],
            'valuation' => [],
        ]));

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $foreign->id;
        $this->assertNull($detail->getCustomerProperty());
        $this->assertSame([], $detail->getNegotiationWorkspaceProperty());
    }

    private function profile(ConversationControl $conversation, array $data): RealEstateProfile
    {
        return RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $conversation->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => $data,
            'valuation' => [],
            'completeness_score' => 50,
            'confidence_score' => 50,
        ]));
    }

    private function conversation(int $user, int $organization, int $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id' => $user,
            'organization_id' => $organization,
            'ai_bot_id' => $bot,
            'session_id' => $session,
            'whatsapp_number' => '905550000001',
            'customer_name' => 'Test Satıcı',
            'tags' => [],
            'lead_status' => 'new',
            'lead_score' => 0,
            'lead_temperature' => 'cold',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);
    }

    private function seedIsolatedAccount(): User
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'seller-negotiation@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'seller-negotiation',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $user->organizations()->syncWithoutDetaching([
            37 => ['role' => 'owner', 'status' => 'active', 'joined_at' => now()],
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

        User::query()->forceCreate([
            'id' => 41,
            'name' => 'Foreign',
            'email' => 'seller-negotiation-foreign@example.test',
            'password' => Hash::make('test'),
        ]);
        Organization::query()->forceCreate([
            'id' => 38,
            'owner_user_id' => 41,
            'name' => 'Foreign',
            'slug' => 'seller-negotiation-foreign',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        AiBot::query()->forceCreate([
            'id' => 36,
            'user_id' => 41,
            'name' => 'Foreign',
            'company_name' => 'Foreign',
            'openai_model' => 'gpt-5-mini',
            'status' => 'active',
            'business_sector' => 'real_estate',
            'lead_scoring_profile' => 'real_estate',
            'whatsapp_instance' => 'foreign-negotiation',
            'follow_up_enabled' => false,
            'second_follow_up_enabled' => false,
            'ai_enabled' => true,
        ]);

        return $user;
    }
}
