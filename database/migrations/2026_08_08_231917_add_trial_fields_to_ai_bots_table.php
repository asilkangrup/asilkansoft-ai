<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {

            // Müşterinin paket durumu:
            // trial   = ücretsiz deneme
            // active  = ücretli paket aktif
            // expired = paket / deneme sona ermiş
            $table->string('subscription_status')
                ->default('trial')
                ->after('status');

            // Ücretsiz denemede verilecek toplam AI cevabı
            $table->unsignedInteger('trial_message_limit')
                ->default(30)
                ->after('subscription_status');

            // WhatsApp üzerinden kullanılan AI cevap sayısı
            $table->unsignedInteger('trial_messages_used')
                ->default(0)
                ->after('trial_message_limit');

            // Denemenin tamamlandığı tarih
            $table->timestamp('trial_completed_at')
                ->nullable()
                ->after('trial_messages_used');

            // Ücretli paket başlangıç tarihi
            $table->timestamp('subscription_started_at')
                ->nullable()
                ->after('trial_completed_at');

            // Ücretli paket bitiş tarihi
            $table->timestamp('subscription_ends_at')
                ->nullable()
                ->after('subscription_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_bots', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status',
                'trial_message_limit',
                'trial_messages_used',
                'trial_completed_at',
                'subscription_started_at',
                'subscription_ends_at',
            ]);
        });
    }
};