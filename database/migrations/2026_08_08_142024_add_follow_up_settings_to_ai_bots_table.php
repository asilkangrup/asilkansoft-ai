<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->boolean('follow_up_enabled')
                ->default(false);

            $table->unsignedInteger('first_follow_up_minutes')
                ->default(1440);

            $table->text('first_follow_up_message')
                ->nullable();

            $table->boolean('second_follow_up_enabled')
                ->default(true);

            $table->unsignedInteger('second_follow_up_minutes')
                ->default(4320);

            $table->text('second_follow_up_message')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_enabled',
                'first_follow_up_minutes',
                'first_follow_up_message',
                'second_follow_up_enabled',
                'second_follow_up_minutes',
                'second_follow_up_message',
            ]);
        });
    }
};