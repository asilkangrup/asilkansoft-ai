<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_bots', function (Blueprint $table) {
    $table->id();

    $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('name');
    $table->string('company_name');
    $table->string('website')->nullable();
    $table->string('instagram')->nullable();
    $table->string('whatsapp_number')->nullable();
    $table->string('logo_path')->nullable();

    $table->string('openai_model')->default('gpt-5-mini');
    $table->longText('system_prompt')->nullable();

    $table->string('status')->default('draft');

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_bots');
    }
};
