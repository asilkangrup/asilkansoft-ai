<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outreach_leads')) {
            return;
        }

        $now = now();

        // Append-only automation batch. insertOrIgnore respects the existing
        // unique (user_id, phone_e164) constraint and never updates old rows.
        DB::table('outreach_leads')->insertOrIgnore([
            [
                'user_id' => 44,
                'company_name' => 'Emekli Finans',
                'sector' => 'Kredi / Finansman Danışmanlığı',
                'phone_e164' => '+905322099252',
                'source' => 'public business website',
                'source_url' => 'https://www.emeklifinans.com/hakkimizda/',
                'source_published_at' => null,
                'source_checked_at' => $now,
                'status' => 'ready',
                'whatsapp_status' => 'verified',
                'priority_score' => 50,
                'first_message_text' => 'Merhaba kolay gelsin, Emekli Finans doğru mudur?',
                'whatsapp_verified_at' => $now,
                'contact_opened_at' => null,
                'replied_at' => null,
                'ai_activated_at' => null,
                'notes' => 'Kamuya açık işletme sitesinde kredi danışmanlığı şirketi olarak tanımlanıyor; mobil hat ve doğrudan WhatsApp bağlantısı resmi sitede yayınlanıyor.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        // Intentionally non-destructive: automation lead batches are append-only.
    }
};
