<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_follow_ups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ai_bot_id')
                ->nullable()
                ->constrained('ai_bots')
                ->nullOnDelete();

            $table->string('session_id')
                ->index();

            $table->string('whatsapp_number')
                ->nullable()
                ->index();

            $table->timestamp('last_customer_message_at')
                ->nullable();

            $table->timestamp('last_bot_message_at')
                ->nullable();

            $table->timestamp('follow_up_sent_at')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'ai_bot_id',
                'session_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_follow_ups');
    }
};