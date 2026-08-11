<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ai_bot_id')
                ->constrained('ai_bots')
                ->cascadeOnDelete();

            $table->string('session_id')->index();
            $table->string('whatsapp_number')->index();

            /*
            |--------------------------------------------------------------------------
            | BAŞVURU TÜRÜ
            |--------------------------------------------------------------------------
            |
            | vodafone
            | turk_telekom
            | turkcell
            | findeks
            | elden_taksit
            |
            */

            $table->string('type')->index();

            /*
            |--------------------------------------------------------------------------
            | ORTAK BİLGİLER
            |--------------------------------------------------------------------------
            */

            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();

            /*
            |--------------------------------------------------------------------------
            | OPERATÖR BİLGİLERİ
            |--------------------------------------------------------------------------
            */

            $table->string('line_owner')->nullable();

            $table->string('mother_maiden_surname')
                ->nullable();

            $table->string('limit_score')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | FİNDEKS BİLGİLERİ
            |--------------------------------------------------------------------------
            */

            $table->date('birth_date')->nullable();

            $table->string('tc_identity_number')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | ELDEN TAKSİT
            |--------------------------------------------------------------------------
            */

            $table->string('limit')->nullable();

            /*
            |--------------------------------------------------------------------------
            | WHATSAPP GRUP GÖNDERİMİ
            |--------------------------------------------------------------------------
            */

            $table->string('group_jid')
                ->nullable();

            $table->string('group_message_id')
                ->nullable();

            $table->timestamp('sent_to_group_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | DURUM
            |--------------------------------------------------------------------------
            */

            $table->string('status')
                ->default('collecting')
                ->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | AYNI OTURUMDA AYNI TÜR İÇİN TEK AKTİF KAYIT
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'ai_bot_id',
                'session_id',
                'type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_leads');
    }
};