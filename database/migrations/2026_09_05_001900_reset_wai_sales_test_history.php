<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')
                ->where('ai_bot_id', 39)
                ->delete();
        }

        if (Schema::hasTable('conversation_follow_ups')) {
            DB::table('conversation_follow_ups')
                ->where('ai_bot_id', 39)
                ->delete();
        }

        if (Schema::hasTable('conversation_controls')) {
            DB::table('conversation_controls')
                ->where('ai_bot_id', 39)
                ->delete();
        }
    }

    public function down(): void
    {
        // Historical test conversations are intentionally not restored.
    }
};
