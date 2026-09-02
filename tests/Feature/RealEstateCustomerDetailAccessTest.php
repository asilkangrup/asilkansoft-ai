<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakCrm;
use App\Filament\Pages\EmlakMusteriDetay;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\RealEstateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateCustomerDetailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_account_can_open_only_its_real_estate_customer_detail(): void
    {
        $user = $this->seedAccount();
        $customer = $this->conversation(40, 37, 35, 'allowed-customer');
        $foreign = $this->conversation(41, 38, 36, 'foreign-customer');

        RealEstateProfile::withoutEvents(fn () => RealEstateProfile::query()->forceCreate([
            'conversation_control_id' => $customer->id,
            'user_id' => 40,
            'ai_bot_id' => 35,
            'profile_type' => 'seller',
            'data' => ['location' => 'Marmaris', 'property_type' => 'arsa'],
            'valuation' => [],
        ]));

        $this->actingAs($user);
        $this->assertTrue(EmlakMusteriDetay::canAccess());

        $detail = new EmlakMusteriDetay;
        $detail->customerId = $customer->id;
        $this->assertSame($customer->id, $detail->getCustomerProperty()?->id);

        $detail->customerId = $foreign->id;
        $this->assertNull($detail->getCustomerProperty());

        $record = (new EmlakCrm)->getRecordsProperty()->first();
        $this->assertStringContainsString('/admin/emlak-musteri-detay?customer='.$customer->id, $record['customer_url']);
        $this->assertStringNotContainsString('/admin/musteriler?', $record['customer_url']);
    }

    private function conversation(int $user, int $organization, int $bot, string $session): ConversationControl
    {
        return ConversationControl::query()->forceCreate([
            'user_id'=>$user,'organization_id'=>$organization,'ai_bot_id'=>$bot,
            'session_id'=>$session,'whatsapp_number'=>'905550000001','customer_name'=>'Test Müşteri',
            'tags'=>[],'lead_status'=>'new','lead_score'=>0,'lead_temperature'=>'cold',
            'next_follow_up_at'=>null,'human_takeover'=>false,
        ]);
    }

    private function seedAccount(): User
    {
        $user = User::query()->forceCreate(['id'=>40,'name'=>'Emlak AI','email'=>'detail-access@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'detail-access','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);\n        $user->organizations()->syncWithoutDetaching([37 => ['role'=>'owner','status'=>'active','joined_at'=>now()]]);
        AiBot::query()->forceCreate(['id'=>35,'user_id'=>40,'name'=>'Emlak AI','company_name'=>'Asilkan Gayrimenkul','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'emlak-ai-35','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        User::query()->forceCreate(['id'=>41,'name'=>'Foreign','email'=>'foreign-detail@example.test','password'=>Hash::make('test')]);
        Organization::query()->forceCreate(['id'=>38,'owner_user_id'=>41,'name'=>'Foreign','slug'=>'foreign-detail','plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active']);
        AiBot::query()->forceCreate(['id'=>36,'user_id'=>41,'name'=>'Foreign','company_name'=>'Foreign','openai_model'=>'gpt-5-mini','status'=>'active','business_sector'=>'real_estate','lead_scoring_profile'=>'real_estate','whatsapp_instance'=>'foreign-detail','follow_up_enabled'=>false,'second_follow_up_enabled'=>false,'ai_enabled'=>true]);
        return $user;
    }
}
