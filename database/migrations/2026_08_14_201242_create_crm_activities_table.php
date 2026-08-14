<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'crm_activities',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'user_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'ai_bot_id'
                )
                    ->nullable()
                    ->constrained('ai_bots')
                    ->nullOnDelete();

                $table->foreignId(
                    'conversation_control_id'
                )
                    ->constrained(
                        'conversation_controls'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'performed_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string(
                    'type',
                    60
                );

                $table->string(
                    'title',
                    255
                );

                $table->text(
                    'description'
                )
                    ->nullable();

                $table->text(
                    'old_value'
                )
                    ->nullable();

                $table->text(
                    'new_value'
                )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | POSTGRESQL UYUMLU META
                |--------------------------------------------------------------------------
                */

                $table->json(
                    'meta'
                )
                    ->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | INDEXLER
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'conversation_control_id',
                    'created_at',
                ]);

                $table->index([
                    'user_id',
                    'created_at',
                ]);

                $table->index(
                    'type'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'crm_activities'
        );
    }
};