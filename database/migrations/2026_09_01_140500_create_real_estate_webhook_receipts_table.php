<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_estate_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->constrained('ai_bots')->cascadeOnDelete();
            $table->string('instance', 100);
            $table->string('event', 50);
            $table->string('receipt_key', 64)->unique();
            $table->string('whatsapp_message_id', 191)->nullable();
            $table->string('phone_number', 40)->nullable();
            $table->string('status', 30)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['ai_bot_id', 'status', 'created_at'], 're_receipts_bot_status_created_idx');
            $table->index(['instance', 'whatsapp_message_id'], 're_receipts_instance_message_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_estate_webhook_receipts');
    }
};
