<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_alarms', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table
                ->foreignId('conversation_control_id')
                ->constrained('conversation_controls')
                ->cascadeOnDelete();

            $table->string('type', 80);
            $table->string('severity', 30)->default('warning');

            $table->string('title', 255);
            $table->text('message');

            $table
                ->string('fingerprint', 191)
                ->unique();

            $table
                ->boolean('is_resolved')
                ->default(false);

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'is_resolved',
            ]);

            $table->index([
                'user_id',
                'severity',
            ]);

            $table->index([
                'conversation_control_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_alarms');
    }
};