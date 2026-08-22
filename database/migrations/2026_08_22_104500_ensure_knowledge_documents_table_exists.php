<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('knowledge_documents')) {
            return;
        }

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_bot_id')->nullable()->constrained('ai_bots')->cascadeOnDelete();
            $table->string('title');
            $table->string('source_type')->default('manual');
            $table->longText('content');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['user_id', 'ai_bot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
