<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bot 35 is permanently reserved for the fresh isolated Emlak AI tenant.
        // Repair any legacy rows created before organization scoping was enforced.
        DB::table('conversation_controls')
            ->where('ai_bot_id', 35)
            ->update([
                'user_id' => 40,
                'organization_id' => 37,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: reverting would reintroduce cross-tenant
        // ambiguity without knowing each historical row's correct organization.
    }
};
