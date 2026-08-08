<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {

            $table->string('whatsapp_status')
                ->default('disconnected')
                ->after('status');

            $table->string('whatsapp_instance')
                ->nullable()
                ->after('whatsapp_status');

            $table->text('whatsapp_qr')
                ->nullable()
                ->after('whatsapp_instance');

            $table->text('whatsapp_token')
                ->nullable()
                ->after('whatsapp_qr');

        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {

            $table->dropColumn([
                'whatsapp_status',
                'whatsapp_instance',
                'whatsapp_qr',
                'whatsapp_token',
            ]);

        });
    }
};