<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | MESAJI KİM GÖNDERDİ?
            |--------------------------------------------------------------------------
            |
            | customer = müşteri
            | ai       = yapay zekâ
            | human    = paneldeki personel
            |
            */

            $table->string('sender_type', 20)
                ->nullable()
                ->after('role')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | PERSONEL KULLANICI
            |--------------------------------------------------------------------------
            |
            | Mesaj panelden bir insan tarafından gönderildiyse
            | hangi kullanıcı gönderdiğini burada tutacağız.
            |
            */

            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->after('sender_type')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign([
                'sent_by_user_id',
            ]);

            $table->dropColumn([
                'sender_type',
                'sent_by_user_id',
            ]);
        });
    }
};