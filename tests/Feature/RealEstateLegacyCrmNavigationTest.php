<?php

namespace Tests\Feature;

use App\Filament\Pages\AlarmMerkezi;
use App\Filament\Pages\Gorevler;
use App\Filament\Pages\Musteriler;
use App\Filament\Pages\Raporlar;
use App\Filament\Pages\SatisPipeline;
use App\Models\Organization;
use App\Models\User;
use App\Services\RealEstateIsolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateLegacyCrmNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_isolated_emlak_owner_can_see_legacy_crm_navigation(): void
    {
        $user = User::query()->forceCreate([
            'id'=>40,'name'=>'Emlak Owner','email'=>'emlak-crm@example.test',
            'password'=>Hash::make('test'),'is_admin'=>false,
        ]);
        Organization::query()->forceCreate([
            'id'=>37,'owner_user_id'=>40,'name'=>'Emlak AI','slug'=>'emlak-legacy-crm',
            'plan'=>'start','seat_limit'=>1,'monthly_message_limit'=>1000,'status'=>'active',
        ]);
        $user->organizations()->syncWithoutDetaching([
            37 => ['role'=>'owner','status'=>'active','joined_at'=>now()],
        ]);

        $this->actingAs($user);

        $this->assertTrue(app(RealEstateIsolationService::class)->currentOperatorHasAccess());
        $this->assertTrue(Musteriler::canAccess());
        $this->assertTrue(SatisPipeline::canAccess());
        $this->assertTrue(Gorevler::canAccess());
        $this->assertTrue(AlarmMerkezi::canAccess());
        $this->assertTrue(Raporlar::canAccess());

        foreach ([
            Musteriler::class, SatisPipeline::class, Gorevler::class,
            AlarmMerkezi::class, Raporlar::class,
        ] as $page) {
            $this->assertSame('CRM', $page::getNavigationGroup());
            $this->assertTrue($page::shouldRegisterNavigation());
        }
    }

    public function test_customer_page_exposes_render_safe_live_kpi_trends(): void
    {
        $trends = (new Musteriler())->getLiveKpiTrendsProperty();

        $this->assertSame(['new', 'hot', 'proposal', 'won'], array_keys($trends));

        foreach ($trends as $trend) {
            $this->assertSame('flat', $trend['trend_direction']);
            $this->assertNotEmpty($trend['trend_label']);
            $this->assertNotEmpty($trend['points']);
        }
    }

    public function test_non_isolated_user_does_not_receive_emlak_crm_override(): void
    {
        $user = User::query()->forceCreate([
            'id'=>41,'name'=>'Foreign','email'=>'foreign-crm@example.test',
            'password'=>Hash::make('test'),'is_admin'=>false,
        ]);

        $this->actingAs($user);

        $this->assertFalse(app(RealEstateIsolationService::class)->currentOperatorHasAccess());
        $this->assertFalse(Musteriler::canAccess());
    }
}
