<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | SAHİPLİK / BAĞLANTI
            |--------------------------------------------------------------------------
            */

            $table
                ->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('ai_bot_id')
                ->nullable()
                ->constrained('ai_bots')
                ->nullOnDelete();

            $table
                ->foreignId('conversation_control_id')
                ->nullable()
                ->constrained('conversation_controls')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | OPENAI KULLANIM BİLGİLERİ
            |--------------------------------------------------------------------------
            */

            $table
                ->string('provider', 50)
                ->default('openai');

            $table
                ->string('model', 100)
                ->nullable();

            $table
                ->string('operation', 100)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | TOKEN KULLANIMI
            |--------------------------------------------------------------------------
            */

            $table
                ->unsignedBigInteger('input_tokens')
                ->default(0);

            $table
                ->unsignedBigInteger('cached_input_tokens')
                ->default(0);

            $table
                ->unsignedBigInteger('output_tokens')
                ->default(0);

            $table
                ->unsignedBigInteger('reasoning_tokens')
                ->default(0);

            $table
                ->unsignedBigInteger('total_tokens')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | MALİYET
            |--------------------------------------------------------------------------
            */

            $table
                ->decimal(
                    'estimated_cost_usd',
                    12,
                    6
                )
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | EK BİLGİLER
            |--------------------------------------------------------------------------
            */

            $table
                ->string('request_id', 150)
                ->nullable()
                ->index();

            $table
                ->json('meta')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | RAPORLAMA INDEXLERİ
            |--------------------------------------------------------------------------
            */

            $table->index([
                'user_id',
                'created_at',
            ]);

            $table->index([
                'ai_bot_id',
                'created_at',
            ]);

            $table->index([
                'conversation_control_id',
                'created_at',
            ]);

            $table->index([
                'operation',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};