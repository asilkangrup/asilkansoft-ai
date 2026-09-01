<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_operator_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('conversation_control_id')->nullable()->constrained('conversation_controls')->cascadeOnDelete();
            $table->foreignId('real_estate_profile_id')->nullable()->constrained('real_estate_profiles')->cascadeOnDelete();
            $table->string('alert_key', 180);
            $table->string('type', 60);
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ai_bot_id', 'alert_key'], 're_operator_alert_identity');
            $table->index(['organization_id', 'status', 'severity'], 're_operator_alert_queue');
            $table->index(['conversation_control_id', 'status'], 're_operator_alert_conversation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_operator_alerts');
    }
};
