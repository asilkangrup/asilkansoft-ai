<?php

namespace Tests\Feature;

use App\Filament\Pages\Musteriler;
use App\Models\AiBot;
use App\Models\ConversationControl;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RealEstateLegacyCrmCustomersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_customers_page_renders_with_live_kpis(): void
    {
        $user = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak Owner',
            'email' => 'emlak-customers@example.test',
            'password' => Hash::make('test'),
            'is_admin' => false,
        ]);

        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'emlak-customers-page',
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

        ConversationControl::query()->forceCreate([
            'user_id' => 40,
            'organization_id' => 37,
            'ai_bot_id' => 35,
            'session_id' => 'crm-render-customer',
            'whatsapp_number' => '905550000040',
            'customer_name' => 'CRM Test Müşteri',
            'tags' => ['Riskli Lead'],
            'lead_status' => 'new',
            'lead_score' => 90,
            'lead_temperature' => 'hot',
            'next_follow_up_at' => null,
            'human_takeover' => false,
        ]);

        $this->actingAs($user);

        $component = Livewire::test(Musteriler::class)->assertOk();

        $live = $component->instance()->getLiveKpiTrendsProperty();
        $this->assertSame(['new', 'hot', 'proposal', 'won'], array_keys($live));
        $this->assertSame('+100%', $live['new']['trend_label']);
        $this->assertSame('up', $live['hot']['trend_direction']);
        $this->assertSame('up', $live['proposal']['trend_direction']);
        $this->assertSame('up', $live['won']['trend_direction']);
    }
}
