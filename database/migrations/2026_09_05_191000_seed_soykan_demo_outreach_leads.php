<?php

use App\Models\OutreachLead;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', ['soykan@gmail.com'])
            ->first();

        if (! $user) {
            return;
        }

        $leads = [
            ['LOCAL HAIR STUDIO', '+905523865315'],
            ['Eurorepar Car Servis İstanbul OBD Oto Servis', '+905323703118'],
            ['Istanbul Property Services', '+905321319696'],
            ['Glow By Hulu Beauty Salon', '+905016157787'],
            ['7/24 İstanbul Full Servis Fikirtepe', '+905455523492'],
            ['Istaproperty Turkey Real Estate Agency', '+905541644782'],
            ['Luxury Mobilya', '+905325241699'],
            ['Local Beauty', '+905461201042'],
            ['Dr.Auto Full Service', '+905346664234'],
            ['Euphoria Beauty Center', '+905461612147'],
            ['Ortaklar Motors', '+905559803814'],
            ['Galata No5 Beauty Hairdresser', '+905398401120'],
            ['Rempa Otomotiv', '+905544023544'],
            ['Omer Istanbul Dental Clinic Turkey', '+905528403434'],
            ['İnk Station Tattoo Gallery', '+905321753134'],
            ['Handyman Istanbul', '+905417743162'],
            ['Ufuk Sarisen Düğün Fotoğraf ve Video', '+905327152789'],
            ['EHLİ KEYF CAFE', '+905354240515'],
            ['Send Flowers to Turkey', '+905321369429'],
            ['Plus Fitness Club', '+905326261631'],
            ['Barbershop FreshCut', '+905427416285'],
            ['Queens Nail Studio', '+905525223012'],
            ['Crystal Wave Spa Massage & Hammam', '+905363013442'],
            ['İstanbul Plumbing', '+905326994058'],
            ['AUTO DETAILING STUDIO', '+905324978561'],
            ['MOTO MECHANIC Servis Yedek Parça', '+905318820000'],
            ['Micro Bilgisayar', '+905322404786'],
            ['Soft Cleans Temizlik Hizmetleri', '+905333781474'],
            ['Alphapest İlaçlama ve Çevre Sağlığı', '+905465890434'],
            ['Çilingirciniz', '+905380590173'],
            ['Kaudupul', '+905359633315'],
            ['Dai Wedding İstanbul', '+905335506314'],
            ['Garantili Saat Tamiri', '+905448887766'],
            ['Cookie Me', '+905421154902'],
            ['AnL Elektrik', '+905419306060'],
            ['Secure Bilgisayar ve Güvenlik Kamera Sistemleri', '+905422421824'],
            ['Alarm İstanbul', '+905373262111'],
            ['Yasemin Genel MakeUp Studio', '+905336411377'],
            ['EsteticDerm Clinic Istanbul', '+905384824585'],
            ['Maral Beauty', '+905072003132'],
            ['YASİN NAKIŞ', '+905547481718'],
            ['The Hit Design İstanbul Tişört Baskı', '+905317803722'],
            ['Tuncer Gift Shop Mosaic Lamps', '+905323263204'],
            ['BALLOON BOUTIQUE', '+905437287870'],
            ['My Party Store', '+905325958926'],
            ['Curtain Home', '+905465840033'],
            ['Tulip Home Accessories', '+905551888706'],
            ['Yıldız Seramik Atölyesi', '+905323061337'],
            ['CarArts Oto Aksesuar ve Tuning Merkezi', '+905074074127'],
            ['Tint House Cam Film PPF Kaplama Merkezi', '+905331462414'],
        ];

        foreach ($leads as [$company, $phone]) {
            OutreachLead::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'phone_e164' => $phone,
                ],
                [
                    'company_name' => $company,
                    'source' => 'public business listing',
                    'status' => 'ready',
                    'whatsapp_status' => 'unknown',
                    'source_checked_at' => now(),
                    'priority_score' => 0,
                    'first_message_text' => 'Merhaba kolay gelsin, '.$company.' doğru mudur?',
                ]
            );
        }
    }

    public function down(): void
    {
        // Demo lead seed is intentionally non-destructive on rollback.
    }
};
