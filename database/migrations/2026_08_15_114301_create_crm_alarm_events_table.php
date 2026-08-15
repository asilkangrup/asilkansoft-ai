<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_alarm_events', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('crm_alarm_id')
                ->constrained('crm_alarms')
                ->cascadeOnDelete();

            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event_type', 60);

            $table->text('description')
                ->nullable();

            $table->json('meta')
                ->nullable();

            $table->timestamps();

            $table->index([
                'crm_alarm_id',
                'created_at',
            ]);

            $table->index([
                'event_type',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'crm_alarm_events'
        );
    }
};