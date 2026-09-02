<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_next_best_action_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('ai_bot_id');
            $table->unsignedBigInteger('conversation_control_id');
            $table->unsignedBigInteger('real_estate_profile_id');
            $table->char('state_key', 64);
            $table->string('action_code', 96);
            $table->string('priority', 16);
            $table->string('stage', 32)->nullable();
            $table->json('reason_codes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'organization_id', 'ai_bot_id', 'state_key'],
                're_nba_scope_state_unique'
            );
            $table->index(
                ['user_id', 'organization_id', 'ai_bot_id', 'created_at'],
                're_nba_scope_created_index'
            );
            $table->index(
                ['real_estate_profile_id', 'created_at'],
                're_nba_profile_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_next_best_action_events');
    }
};
