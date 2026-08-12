<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_controls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ai_bot_id')
                ->constrained('ai_bots')
                ->cascadeOnDelete();

            $table->string('session_id')
                ->index();

            $table->string('whatsapp_number')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | İNSAN DEVİR ALMA DURUMU
            |--------------------------------------------------------------------------
            |
            | false = Yapay zekâ cevap verir.
            | true  = Bu konuşmayı insan devralmıştır ve AI cevap vermez.
            |
            */

            $table->boolean('human_takeover')
                ->default(false)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | DEVİR ALMA / AI'YE GERİ VERME ZAMANLARI
            |--------------------------------------------------------------------------
            */

            $table->timestamp('taken_over_at')
                ->nullable();

            $table->timestamp('released_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | AYNI BOT + AYNI OTURUM İÇİN TEK KONTROL KAYDI
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'ai_bot_id',
                'session_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_controls');
    }
};