<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | GRUP YÖNLENDİRME ÖZELLİĞİ
            |--------------------------------------------------------------------------
            */

            $table->boolean('group_routing_enabled')
                ->default(false)
                ->after('whatsapp_token');

            /*
            |--------------------------------------------------------------------------
            | WHATSAPP GRUPLARI
            |--------------------------------------------------------------------------
            */

            $table->string('vodafone_group_jid')
                ->nullable()
                ->after('group_routing_enabled');

            $table->string('turktelekom_group_jid')
                ->nullable()
                ->after('vodafone_group_jid');

            $table->string('turkcell_group_jid')
                ->nullable()
                ->after('turktelekom_group_jid');

            $table->string('findeks_group_jid')
                ->nullable()
                ->after('turkcell_group_jid');

            $table->string('elden_taksit_group_jid')
                ->nullable()
                ->after('findeks_group_jid');
        });
    }

    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->dropColumn([
                'group_routing_enabled',
                'vodafone_group_jid',
                'turktelekom_group_jid',
                'turkcell_group_jid',
                'findeks_group_jid',
                'elden_taksit_group_jid',
            ]);
        });
    }
};