<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_follow_ups', function (Blueprint $table) {
            $table->string('last_ai_message_id')
                ->nullable()
                ->after('human_takeover');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_follow_ups', function (Blueprint $table) {
            $table->dropColumn('last_ai_message_id');
        });
    }
};