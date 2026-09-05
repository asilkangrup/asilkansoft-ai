<?php

namespace App\Services;

use App\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WaiOutreachLeadPolicy
{
    public function __construct(
        private readonly WaiSalesOutreachService $salesService,
    ) {}

    public function prepareForCreate(OutreachLead $lead): void
    {
        if ((int) $lead->user_id !== 44) {
            return;
        }

        $digits = preg_replace('/\D+/', '', (string) $lead->phone_e164) ?: '';

        // WAI satış panelinde yalnızca Türkiye mobil hatları kabul edilir.
        if (! preg_match('/^905\d{9}$/', $digits)) {
            throw ValidationException::withMessages([
                'phone_e164' => 'Sabit hat veya geçersiz mobil numara WAI satış paneline eklenemez.',
            ]);
        }

        $phone = '+'.$digits;

        if (OutreachLead::query()
            ->where('user_id', 44)
            ->where('phone_e164', $phone)
            ->exists()) {
            throw ValidationException::withMessages([
                'phone_e164' => 'Bu telefon numarası panelde zaten mevcut.',
            ]);
        }

        $companyName = trim((string) $lead->company_name);
        if ($companyName === '' || $this->isGenericBusinessName($companyName)) {
            throw ValidationException::withMessages([
                'company_name' => 'Lokasyon + hizmet şeklindeki jenerik işletmeler panele eklenemez; özel işletme adı gerekir.',
            ]);
        }

        $sector = trim((string) ($lead->sector ?? ''));
        if ($sector === '' || $sector === 'Bilinmeyen' || $sector === 'Yerel İşletme') {
            $sector = $this->salesService->sectorForName($companyName);
        }

        if ($sector === '' || $sector === 'Bilinmeyen' || $sector === 'Yerel İşletme') {
            throw ValidationException::withMessages([
                'sector' => 'İşletmenin sektörü doğrulanmadan WAI satış paneline eklenemez.',
            ]);
        }

        $response = Http::withHeaders([
            'apikey' => (string) config('evolution.api_key'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30)->post(
            rtrim((string) config('evolution.url'), '/').'/chat/whatsappNumbers/wai-sales-48-clean',
            ['numbers' => [$digits]],
        );

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'phone_e164' => 'WhatsApp doğrulaması yapılamadı; doğrulanmayan lead panele eklenmedi.',
            ]);
        }

        $result = collect($response->json())->first();
        if (! (bool) data_get($result, 'exists', false)) {
            throw ValidationException::withMessages([
                'phone_e164' => 'Bu numarada WhatsApp bulunamadı; lead panele eklenmedi.',
            ]);
        }

        $lead->phone_e164 = $phone;
        $lead->sector = $sector;
        $lead->whatsapp_status = 'verified';
        $lead->whatsapp_verified_at = now();
        $lead->first_message_text = $lead->first_message_text
            ?: "Merhaba kolay gelsin, {$companyName} doğru mudur?";
    }

    private function isGenericBusinessName(string $name): bool
    {
        $value = Str::lower(Str::ascii($name));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        $locations = [
            'istanbul','adalar','arnavutkoy','atasehir','avcilar','bagcilar','bahcelievler','bakirkoy',
            'basaksehir','bayrampasa','besiktas','beykoz','beylikduzu','beyoglu','buyukcekmece','catalca',
            'cekmekoy','esenler','esenyurt','eyupsultan','fatih','gaziosmanpasa','gungoren','kadikoy',
            'kagithane','kartal','kucukcekmece','maltepe','pendik','sancaktepe','sariyer','silivri',
            'sultanbeyli','sultangazi','sile','sisli','tuzla','umraniye','uskudar','zeytinburnu',
            'fikirtepe','altinsehir','anadolu yakasi','avrupa yakasi',
        ];

        $genericPhrases = [
            'bilgisayar teknik servisi','bilgisayar teknik servis','bilgisayar servisi','bilgisayar servis',
            'temizlik sirketi','temizlik hizmetleri','temizlik','tesisatci','tesisat','oto servis iletisim',
            'oto servis','guzellik merkezi','epilasyon ve guzellik merkezi','mobilya magazasi','mobilya',
            'teknik servis','full servis','servis','iletisim','dekorasyon','merkezi','magazasi','sirketi',
            '7 24','24 7','premium','local','new','studio',
        ];

        foreach (array_merge($locations, $genericPhrases) as $term) {
            $term = Str::lower(Str::ascii($term));
            $value = preg_replace('/\b'.preg_quote($term, '/').'\b/', ' ', $value) ?: $value;
        }

        $tokens = collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter(fn (string $token) => strlen($token) >= 3 && ! ctype_digit($token))
            ->values();

        return $tokens->isEmpty();
    }
}
