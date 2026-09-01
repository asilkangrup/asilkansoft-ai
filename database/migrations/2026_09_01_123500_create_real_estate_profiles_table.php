<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_control_id')
                ->unique()
                ->constrained('conversation_controls')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('ai_bot_id')
                ->nullable()
                ->constrained('ai_bots')
                ->nullOnDelete();
            $table->string('profile_type', 32)->default('general');
            $table->json('data')->nullable();
            $table->json('valuation')->nullable();
            $table->unsignedTinyInteger('completeness_score')->default(0);
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->timestamp('last_extracted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'profile_type']);
            $table->index('last_extracted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_profiles');
    }
};
