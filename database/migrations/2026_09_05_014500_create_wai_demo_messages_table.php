<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wai_demo_messages')) {
            return;
        }

        Schema::create('wai_demo_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wai_demo_lead_id')->constrained('wai_demo_leads')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('message');
            $table->timestamps();

            $table->index(['wai_demo_lead_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wai_demo_messages');
    }
};
