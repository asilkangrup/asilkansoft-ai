<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->constrained('real_estate_profiles')->cascadeOnDelete();
            $table->foreignId('investor_profile_id')->constrained('real_estate_profiles')->cascadeOnDelete();
            $table->foreignId('seller_conversation_id')->constrained('conversation_controls')->cascadeOnDelete();
            $table->foreignId('investor_conversation_id')->constrained('conversation_controls')->cascadeOnDelete();
            $table->char('pair_key', 64);
            $table->char('event_key', 64)->unique();
            $table->string('status', 20);
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->string('grade', 20)->nullable();
            $table->decimal('estimated_transaction_price', 15, 2)->nullable();
            $table->json('reasons')->nullable();
            $table->json('risks')->nullable();
            $table->json('safety')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['pair_key', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
            $table->index(['seller_profile_id', 'status']);
            $table->index(['investor_profile_id', 'status']);
            $table->index(['user_id', 'organization_id', 'ai_bot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_match_events');
    }
};
