<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('phone_e164', 32);
            $table->string('source')->default('manual');
            $table->string('status')->default('ready');
            $table->text('first_message_text')->nullable();
            $table->timestamp('whatsapp_verified_at')->nullable();
            $table->timestamp('contact_opened_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('ai_activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'phone_e164']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_leads');
    }
};
