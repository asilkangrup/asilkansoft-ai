<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_evidence_events', function (Blueprint $table): void {
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
            $table->string('message_id_hash', 64)->nullable()->index();
            $table->string('source_type', 32);
            $table->string('mime_type', 120)->nullable();
            $table->string('document_type', 120)->nullable();
            $table->string('provenance_class', 32)->index();
            $table->unsignedSmallInteger('confidence_score')->default(0);
            $table->json('field_keys')->nullable();
            $table->json('identity_signals')->nullable();
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->string('content_fingerprint', 64)->nullable()->index();
            $table->string('analysis_version', 32)->default('v1');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(
                ['user_id', 'organization_id', 'ai_bot_id', 'recorded_at'],
                're_evidence_scope_recorded_idx'
            );
            $table->index(
                ['real_estate_profile_id', 'provenance_class'],
                're_evidence_profile_class_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_evidence_events');
    }
};
