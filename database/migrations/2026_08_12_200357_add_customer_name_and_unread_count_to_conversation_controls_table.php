<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | MÜŞTERİ ADI
            |--------------------------------------------------------------------------
            |
            | WhatsApp webhook payload'ından gelen isim burada tutulacak.
            | Gelmezse null kalabilir.
            |
            */

            $table->string('customer_name')
                ->nullable()
                ->after('whatsapp_number');

            /*
            |--------------------------------------------------------------------------
            | OKUNMAMIŞ MESAJ SAYISI
            |--------------------------------------------------------------------------
            |
            | Müşteri her yeni mesaj attığında 1 artacak.
            | Personel konuşmayı açtığında 0'a düşecek.
            |
            */

            $table->unsignedInteger('unread_count')
                ->default(0)
                ->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_controls', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'unread_count',
            ]);
        });
    }
};