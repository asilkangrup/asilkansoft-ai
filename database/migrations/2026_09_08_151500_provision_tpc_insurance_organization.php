<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $user = DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['dogustopcu@gmail.com'])
            ->first();

        if (! $user) {
            return;
        }

        $now = now();

        $organization = DB::table('organizations')
            ->where('slug', 'tpc-sigorta')
            ->first();

        if (! $organization) {
            $organizationId = DB::table('organizations')->insertGetId([
                'owner_user_id' => $user->id,
                'name' => 'Doğuş Topçu Sigorta',
                'slug' => 'tpc-sigorta',
                'plan' => 'enterprise',
                'seat_limit' => 50,
                'monthly_message_limit' => 100000,
                'status' => 'active',
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
                'settings' => json_encode([
                    'product' => 'tpc_insurance_os',
                    'branding' => 'TPC Insurance OS',
                    'insurance_only' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $organizationId = $organization->id;

            DB::table('organizations')
                ->where('id', $organizationId)
                ->update([
                    'owner_user_id' => $user->id,
                    'name' => 'Doğuş Topçu Sigorta',
                    'plan' => 'enterprise',
                    'status' => 'active',
                    'seat_limit' => max((int) $organization->seat_limit, 50),
                    'monthly_message_limit' => max((int) $organization->monthly_message_limit, 100000),
                    'settings' => json_encode([
                        'product' => 'tpc_insurance_os',
                        'branding' => 'TPC Insurance OS',
                        'insurance_only' => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);
        }

        DB::table('organization_user')->updateOrInsert(
            [
                'organization_id' => $organizationId,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
                'permissions' => json_encode([
                    'insurance' => true,
                    'insurance_operations' => true,
                    'insurance_renewals' => true,
                    'insurance_team' => true,
                    'insurance_management' => true,
                    'insurance_quote_payment' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'joined_at' => $now,
                'last_active_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'is_admin' => false,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Production customer provisioning is intentionally not removed on rollback.
    }
};
