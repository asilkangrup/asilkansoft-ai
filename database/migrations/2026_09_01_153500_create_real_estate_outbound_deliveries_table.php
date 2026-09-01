<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_outbound_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->string('instance', 100);
            $table->string('delivery_key', 64)->unique();
            $table->string('inbound_whatsapp_message_id', 191);
            $table->string('session_id', 191);
            $table->string('phone_number', 40);
            $table->string('answer_hash', 64);
            $table->longText('answer');
            $table->string('status', 30)->default('reserved');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('whatsapp_message_id', 191)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('trial_consumed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['ai_bot_id', 'status', 'created_at'],
                're_outbound_bot_status_created_idx'
            );
            $table->index(
                ['instance', 'inbound_whatsapp_message_id'],
                're_outbound_instance_inbound_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_outbound_deliveries');
    }
};
