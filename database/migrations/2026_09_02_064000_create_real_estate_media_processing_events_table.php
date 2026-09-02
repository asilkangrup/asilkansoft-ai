<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_media_processing_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('conversation_control_id')->constrained('conversation_controls')->cascadeOnDelete();
            $table->string('event_key', 64)->unique();
            $table->string('message_id_hash', 64);
            $table->string('source_type', 24);
            $table->string('mime_type', 120)->nullable();
            $table->string('outcome', 48);
            $table->string('reason', 80)->nullable();
            $table->unsignedBigInteger('declared_bytes')->nullable();
            $table->unsignedBigInteger('actual_bytes')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('analysis_version', 32);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(
                ['user_id', 'organization_id', 'ai_bot_id', 'recorded_at'],
                're_media_scope_recorded_idx'
            );
            $table->index(
                ['conversation_control_id', 'recorded_at'],
                're_media_conversation_recorded_idx'
            );
            $table->index(['outcome', 'recorded_at'], 're_media_outcome_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_media_processing_events');
    }
};
