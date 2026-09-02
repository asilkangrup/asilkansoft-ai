<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_valuation_research_events', function (Blueprint $table): void {
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
            $table->string('research_key', 64)->unique();
            $table->string('profile_fingerprint', 64)->index();
            $table->string('model', 80)->nullable();
            $table->boolean('forced_refresh')->default(false);
            $table->unsignedSmallInteger('confidence_score')->default(0);
            $table->unsignedSmallInteger('source_count')->default(0);
            $table->unsignedSmallInteger('comparable_count')->default(0);
            $table->unsignedSmallInteger('usable_comparable_count')->default(0);
            $table->unsignedSmallInteger('distinct_source_host_count')->default(0);
            $table->string('integrity_status', 32)->nullable()->index();
            $table->string('integrity_quality', 32)->nullable();
            $table->decimal('market_min', 16, 2)->nullable();
            $table->decimal('market_max', 16, 2)->nullable();
            $table->decimal('quick_sale_min', 16, 2)->nullable();
            $table->decimal('quick_sale_max', 16, 2)->nullable();
            $table->decimal('investor_buy_min', 16, 2)->nullable();
            $table->decimal('investor_buy_max', 16, 2)->nullable();
            $table->json('source_host_hashes')->nullable();
            $table->json('comparable_fingerprints')->nullable();
            $table->timestamp('researched_at')->index();
            $table->timestamps();

            $table->index(
                ['user_id', 'organization_id', 'ai_bot_id', 'researched_at'],
                're_val_research_scope_time_idx'
            );
            $table->index(
                ['real_estate_profile_id', 'researched_at'],
                're_val_research_profile_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_valuation_research_events');
    }
};
