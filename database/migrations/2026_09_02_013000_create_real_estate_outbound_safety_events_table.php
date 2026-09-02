<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('real_estate_outbound_safety_events')) {
            return;
        }

        Schema::create('real_estate_outbound_safety_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('ai_bot_id');
            $table->unsignedBigInteger('conversation_control_id')->nullable();
            $table->unsignedBigInteger('real_estate_profile_id')->nullable();
            $table->char('event_key', 64)->unique();
            $table->char('inbound_message_id_hash', 64);
            $table->char('response_hash', 64);
            $table->string('action', 32)->default('replaced');
            $table->string('recipient_role', 32)->default('general');
            $table->json('reasons');
            $table->string('safe_response_version', 64);
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(
                ['user_id', 'organization_id', 'ai_bot_id', 'detected_at'],
                'real_estate_outbound_safety_scope_time_idx'
            );
            $table->index(
                ['conversation_control_id', 'detected_at'],
                'real_estate_outbound_safety_conversation_idx'
            );
            $table->index(
                ['action', 'detected_at'],
                'real_estate_outbound_safety_action_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_outbound_safety_events');
    }
};
