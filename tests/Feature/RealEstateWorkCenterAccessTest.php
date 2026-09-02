<?php

namespace Tests\Feature;

use App\Filament\Pages\EmlakIsMerkezi;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RealEstateWorkCenterAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_fresh_real_estate_owner_can_access_work_center(): void
    {
        $fresh = User::query()->forceCreate([
            'id' => 40,
            'name' => 'Emlak AI',
            'email' => 'real-estate-owner@example.test',
            'password' => Hash::make('test-password'),
            'is_admin' => false,
        ]);
        Organization::query()->forceCreate([
            'id' => 37,
            'owner_user_id' => 40,
            'name' => 'Emlak AI',
            'slug' => 'real-estate-work-center-access',
            'plan' => 'start',
            'seat_limit' => 1,
            'monthly_message_limit' => 1000,
            'status' => 'active',
        ]);
        $fresh->organizations()->syncWithoutDetaching([
            37 => [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        $this->actingAs($fresh);
        $this->assertTrue(EmlakIsMerkezi::canAccess());

        $old = User::query()->forceCreate([
            'id' => 1,
            'name' => 'Old WAI',
            'email' => 'old-wai@example.test',
            'password' => Hash::make('test-password'),
            'is_admin' => true,
        ]);

        $this->actingAs($old);
        $this->assertFalse(EmlakIsMerkezi::canAccess());
    }
}
