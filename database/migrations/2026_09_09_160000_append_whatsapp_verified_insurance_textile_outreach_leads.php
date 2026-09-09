<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outreach_leads')) {
            return;
        }

        $userId = 44;
        $now = now();

        $candidates = [
            ['By Baskı', 'Tişört Baskı / Tekstil', '905467240724', 'WhatsApp işletme profili', 'https://wa.me/by.baski'],
            ['Velvet Baskı', 'Tişört Baskı / Tekstil', '905069946045', 'resmi işletme sitesi', 'https://velvetbaski.com/'],
            ['Velvet Baskı', 'Tişört Baskı / Tekstil', '905552769706', 'resmi işletme sitesi', 'https://velvetbaski.com/'],
            ['Tekstil Baskı Atölyesi', 'Tişört Baskı / Tekstil', '905414175774', 'resmi işletme sitesi', 'https://tekstilbaskiatolyesi.com/'],
            ['Adresinize Teslim Baskı', 'Tişört Baskı / Tekstil', '905559720389', 'resmi işletme sitesi', 'https://www.adresinizeteslim.com/m/Kurumsal-Tisort-Baski-WhatsApp-0555-972-03-89_b_41.aspx'],
            ['Gelişim Dijital', 'Tişört Baskı / Tekstil', '905059196278', 'resmi işletme sitesi', 'https://gelisimdijital.com/tekstil-baski.html'],
            ['Gelişim Dijital', 'Tişört Baskı / Tekstil', '905412207860', 'resmi işletme sitesi', 'https://gelisimdijital.com/tekstil-baski.html'],
            ['Ervo Reklam', 'Tişört Baskı / Tekstil', '905365750507', 'kamuya açık sosyal medya', 'https://www.instagram.com/reel/DUdoICagnlR/'],
            ['UnaSempre Tekstil Baskı Atölyesi', 'Tişört Baskı / Tekstil', '905320612898', 'kamuya açık işletme haritası', 'https://yandex.com.tr/maps/org/unasempre_tekstil_baski_atolyesi/141501916325/'],
            ['DTF Baskı Tasarım', 'Tişört Baskı / Tekstil', '905342447008', 'resmi işletme sitesi', 'https://dtfbaskitasarim.com/hakkimizda'],
            ['Karadeniz Promosyon', 'Tişört Baskı / Tekstil', '905419455079', 'kamuya açık sosyal medya', 'https://www.instagram.com/p/DTll7hyDaq3/'],
            ['Printiva DTF Baskı', 'Tişört Baskı / Tekstil', '905330542329', 'kamuya açık sosyal medya', 'https://www.instagram.com/printivadtf/'],
            ['Bana da Reklam', 'Tişört Baskı / Tekstil', '905332470118', 'kamuya açık sosyal medya', 'https://www.instagram.com/reel/Dcd6L0rM1Uw/'],
            ['İmpara DTF Tekstil Baskı', 'Tişört Baskı / Tekstil', '905348926053', 'kamuya açık sosyal medya', 'https://www.instagram.com/reel/DNXungRqh36/'],
            ['Bak Bi Sigorta', 'Sigorta Acentesi', '905445123737', 'resmi işletme sitesi', 'https://www.bakbisigorta.com/tr/iletisim'],
            ['Kapadokya Era Sigorta', 'Sigorta Acentesi', '905304683656', 'resmi işletme sitesi', 'https://lion.sigorta.online/TSS'],
            ['Konuk Sigorta', 'Sigorta Acentesi', '905320548253', 'resmi işletme sitesi', 'https://www.enuyguntrafik.com/'],
            ['Süper Sigortam', 'Sigorta Acentesi', '905529573845', 'resmi işletme sitesi', 'https://www.supersigortam.com/'],
            ['Dörtay Sigorta', 'Sigorta Acentesi', '905510693312', 'WhatsApp işletme profili', 'https://wa.me/dortaysigorta'],
            ['Uygun Sigorta', 'Sigorta Acentesi', '905522002258', 'kamuya açık sosyal medya', 'https://www.instagram.com/p/DKlkmulJdjb/'],
            ['Sigorta Mobil Edirne', 'Sigorta Acentesi', '905526423022', 'kamuya açık sosyal medya', 'https://www.facebook.com/100063778711731/'],
            ['Sigorta Mobil Edirne', 'Sigorta Acentesi', '905438519266', 'kamuya açık sosyal medya', 'https://www.facebook.com/100063778711731/'],
            ['Seç Sigorta Düzce', 'Sigorta Acentesi', '905523609902', 'kamuya açık sosyal medya', 'https://www.facebook.com/secsigortaduzce/'],
            ['Ömer İnci Sigorta Acentesi', 'Sigorta Acentesi', '905413516404', 'kamuya açık sosyal medya', 'https://www.instagram.com/p/DYqaiWjoXRV/'],
            ['Asata Sigorta', 'Sigorta Acentesi', '905522566133', 'kamuya açık sosyal medya', 'https://www.instagram.com/p/DRY2mugiEUt/'],
            ['Şahinler Sigorta', 'Sigorta Acentesi', '905513604646', 'kamuya açık sosyal medya', 'https://www.instagram.com/reel/DYfODmMop9Q/'],
            ['Şahinler Sigorta', 'Sigorta Acentesi', '905384098368', 'kamuya açık sosyal medya', 'https://www.instagram.com/reel/DYfODmMop9Q/'],
        ];

        $candidates = collect($candidates)
            ->filter(fn (array $row): bool => (bool) preg_match('/^905\d{9}$/', $row[2]))
            ->unique(fn (array $row): string => $row[2])
            ->reject(fn (array $row): bool => DB::table('outreach_leads')
                ->where('user_id', $userId)
                ->where('phone_e164', '+'.$row[2])
                ->exists())
            ->values();

        if ($candidates->isEmpty()) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => (string) config('evolution.api_key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(45)->post(
                rtrim((string) config('evolution.url'), '/').'/chat/whatsappNumbers/wai-sales-48-clean',
                ['numbers' => $candidates->pluck(2)->all()],
            );
        } catch (\Throwable) {
            return;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return;
        }

        $results = array_values($response->json());
        $rows = [];

        foreach ($candidates as $index => $candidate) {
            $result = $results[$index] ?? null;

            if (! is_array($result) || ! (bool) data_get($result, 'exists', false)) {
                continue;
            }

            [$company, $sector, $digits, $source, $sourceUrl] = $candidate;

            $rows[] = [
                'user_id' => $userId,
                'company_name' => $company,
                'sector' => $sector,
                'phone_e164' => '+'.$digits,
                'source' => $source,
                'source_url' => $sourceUrl,
                'source_published_at' => null,
                'source_checked_at' => $now,
                'status' => 'ready',
                'whatsapp_status' => 'verified',
                'priority_score' => 50,
                'first_message_text' => 'Merhaba kolay gelsin, '.$company.' doğru mudur?',
                'whatsapp_verified_at' => $now,
                'contact_opened_at' => null,
                'replied_at' => null,
                'ai_activated_at' => null,
                'notes' => 'Türkiye mobil hattı; canlı Evolution API WhatsApp kontrolünden geçti. Kamuya açık işletme iletişim bilgisidir.',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('outreach_leads')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Append-only lead batch: rollback mevcut satış kayıtlarını silmez.
    }
};
