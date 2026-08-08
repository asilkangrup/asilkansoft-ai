<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_follow_ups', function (Blueprint $table) {
            $table->timestamp('first_follow_up_sent_at')
                ->nullable()
                ->after('follow_up_sent_at');

            $table->timestamp('second_follow_up_sent_at')
                ->nullable()
                ->after('first_follow_up_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_follow_ups', function (Blueprint $table) {
            $table->dropColumn([
                'first_follow_up_sent_at',
                'second_follow_up_sent_at',
            ]);
        });
    }
};