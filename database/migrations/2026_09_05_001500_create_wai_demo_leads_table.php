<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wai_demo_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 48)->unique();
            $table->string('company_name', 120);
            $table->text('company_description')->nullable();
            $table->string('source', 50)->default('whatsapp_sales');
            $table->unsignedInteger('test_message_count')->default(0);
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('whatsapp_connect_started_at')->nullable();
            $table->timestamp('whatsapp_connected_at')->nullable();
            $table->foreignId('temporary_bot_id')->nullable()->constrained('ai_bots')->nullOnDelete();
            $table->string('whatsapp_instance')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_connected_at', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wai_demo_leads');
    }
};
