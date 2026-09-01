<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_negotiation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->foreignId('conversation_control_id')->constrained('conversation_controls')->cascadeOnDelete();
            $table->foreignId('real_estate_profile_id')->constrained('real_estate_profiles')->cascadeOnDelete();
            $table->foreignId('source_chat_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('actor_role', 20);
            $table->decimal('numeric_value', 16, 2)->nullable();
            $table->string('text_value', 255)->nullable();
            $table->decimal('previous_numeric_value', 16, 2)->nullable();
            $table->string('previous_text_value', 255)->nullable();
            $table->string('direction', 16)->default('initial');
            $table->char('position_key', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'ai_bot_id', 'real_estate_profile_id', 'event_type', 'position_key'],
                're_negotiation_event_identity'
            );
            $table->index(
                ['organization_id', 'conversation_control_id', 'occurred_at'],
                're_negotiation_conversation_timeline'
            );
            $table->index(
                ['real_estate_profile_id', 'event_type', 'occurred_at'],
                're_negotiation_profile_event'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_negotiation_events');
    }
};
