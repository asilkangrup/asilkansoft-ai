<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('conversation_control_id')
                ->constrained('conversation_controls')
                ->cascadeOnDelete();
            $table->foreignId('real_estate_profile_id')
                ->constrained('real_estate_profiles')
                ->cascadeOnDelete();

            $table->string('event_key', 64)->unique();
            $table->string('event_type', 40);
            $table->string('profile_type', 32)->default('general');
            $table->string('stage', 32)->nullable();
            $table->unsignedTinyInteger('lead_score')->nullable();
            $table->string('lead_temperature', 16)->nullable();
            $table->boolean('ready_for_valuation')->default(false);
            $table->boolean('ready_for_match')->default(false);
            $table->boolean('valuation_present')->default(false);
            $table->unsignedSmallInteger('match_count')->default(0);
            $table->string('strongest_match_grade', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index([
                'user_id',
                'organization_id',
                'ai_bot_id',
                'occurred_at',
            ], 'real_estate_case_events_scope_time_idx');

            $table->index([
                'conversation_control_id',
                'event_type',
            ], 'real_estate_case_events_conversation_type_idx');

            $table->index([
                'real_estate_profile_id',
                'occurred_at',
            ], 'real_estate_case_events_profile_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_case_events');
    }
};
