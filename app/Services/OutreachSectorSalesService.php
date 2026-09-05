<?php

namespace App\Services;

use App\Models\OutreachLead;
use Illuminate\Support\Str;

class OutreachSectorSalesService
{
    public function inspect(int $userId, string $phoneNumber, string $message): array
    {
        $phone = '+'.preg_replace('/\D+/', '', $phoneNumber);

        $lead = OutreachLead::query()
            ->where('user_id', $userId)
            ->where('phone_e164', $phone)
            ->first();

        if (! $lead) {
            return ['matched' => false];
        }

        $sector = $this->sectorFor($lead->company_name);
        $normalized = Str::lower(trim($message));

        $rejectionPatterns = [
            'ilgilenmiyorum', 'istemiyorum', 'gerek yok', 'düşünmüyorum', 'dusunmuyorum',
            'teşekkürler', 'tesekkurler', 'teşekkür ederim', 'tesekkur ederim',
            'sağ olun', 'sag olun', 'sağol', 'sagol', 'istemiyoruz', 'ilgilenmiyoruz',
            'rahatsız etmeyin', 'rahatsiz etmeyin', 'mesaj atmayın', 'mesaj atmayin',
        ];

        $isRejection = collect($rejectionPatterns)
            ->contains(fn (string $pattern): bool => str_contains($normalized, $pattern));

        if ($isRejection) {
            if ($lead->status === 'declined') {
                return [
                    'matched' => true,
                    'action' => 'ignore',
                    'lead' => $lead,
                    'sector' => $sector,
                ];
            }

            $lead->forceFill([
                'status' => 'declined',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'matched' => true,
                'action' => 'reply',
                'answer' => 'Tabii, teşekkür ederim. İyi günler dilerim.',
                'lead' => $lead,
                'sector' => $sector,
            ];
        }

        if ($lead->status === 'opened') {
            $lead->forceFill([
                'status' => 'replied',
                'replied_at' => now(),
            ])->save();

            return [
                'matched' => true,
                'action' => 'reply',
                'answer' => $this->firstSalesMessage($sector),
                'lead' => $lead,
                'sector' => $sector,
            ];
        }

        if ($lead->status === 'ready') {
            $lead->forceFill([
                'status' => 'replied',
                'replied_at' => now(),
            ])->save();
        }

        return [
            'matched' => true,
            'action' => 'continue',
            'lead' => $lead,
            'sector' => $sector,
            'context' => $this->context($lead->company_name, $sector),
        ];
    }

    public function context(string $company, string $sector): string
    {
        return implode("\n", [
            'BU KONUŞMA WAI SATIŞ LEADİDİR.',
            "İşletme: {$company}",
            "Sektör: {$sector}",
            'Kısa, doğal, WhatsApp dilinde konuş. Her mesajda tek ana fikir kullan.',
            'İlgiyi anlamak için hafif ve kısa sorular sor; sorguya çekme.',
            'WAI\'yi bu sektörün gerçek günlük WhatsApp işlerine göre anlat.',
            'Fiyat/randevu/sipariş/teklif/müşteri bilgisi toplama gibi bu sektöre uygun örnekler ver.',
            'Kullanıcı istemediğini söylerse ikna etmeye çalışma. Kısa teşekkür et ve konuşmayı bitir.',
            'Sessizlikte asla takip mesajı üretme.',
            'Aynı faydayı veya kapanış cümlesini tekrar tekrar yazma.',
            'Kesin satış veya gelir garantisi verme.',
        ]);
    }

    private function firstSalesMessage(string $sector): string
    {
        $capability = $this->capabilityFor($sector);

        return "Ben WAI ekibinden size ulaşıyorum. Numaranızı işletmenizin internette herkese açık iletişim bilgilerinden buldum.\n\n{$sector} işletmeleri için geliştirdiğimiz bir yapay zeka sistemimiz var. {$capability}\n\nNasıl çalıştığı hakkında bilgi almak ister misiniz?";
    }

    private function capabilityFor(string $sector): string
    {
        return match ($sector) {
            'Güzellik / Estetik' => 'WhatsApp’tan gelen işlem, fiyat ve randevu sorularını cevaplayabilir; müşteriden gerekli bilgileri alıp randevu aşamasına kadar ilerletebilir.',
            'Mobilya / Ev Dekorasyon' => 'Ürün, ölçü, model, renk ve fiyat sorularını cevaplayabilir; müşterinin talebini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Oto Servis / Otomotiv' => 'Araç marka-model, arıza, servis ve randevu taleplerini toplayabilir; müşteriyi uygun servis sürecine yönlendirebilir.',
            'Emlak' => 'Satılık-kiralık taleplerinde bölge, bütçe ve kriterleri toplayabilir; müşteriyi uygun portföy sürecine hazırlayabilir.',
            'Kuaför / Berber' => 'Hizmet, fiyat, uygun saat ve randevu sorularını otomatik karşılayıp randevuya kadar ilerletebilir.',
            'Sağlık / Klinik' => 'Hizmet ve randevu taleplerini karşılayabilir, temel bilgileri toplayıp uygun danışman veya randevu akışına aktarabilir.',
            'Teknik Servis / Tamir' => 'Arıza ve hizmet taleplerini toplayabilir, gerekli ön bilgileri alıp servis veya teklif aşamasına taşıyabilir.',
            'Yeme-İçme / Kafe' => 'Menü, rezervasyon, sipariş ve sık sorulan soruları otomatik karşılayabilir.',
            'Fotoğraf / Organizasyon' => 'Tarih, etkinlik türü, paket ve fiyat taleplerini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Temizlik / Ev Hizmetleri' => 'Konum, hizmet türü, tarih ve iş detaylarını alıp teklif veya randevu aşamasına taşıyabilir.',
            default => 'WhatsApp’tan gelen müşterileri karşılayabilir, sık sorulan soruları cevaplayabilir, müşteri bilgilerini toplayıp satış veya randevu aşamasına kadar ilerletebilir.',
        };
    }

    private function sectorFor(string $companyName): string
    {
        $name = Str::lower($companyName);

        $groups = [
            'Güzellik / Estetik' => ['beauty', 'estetic', 'estetik', 'nail', 'spa', 'makeup', 'hair studio', 'güzellik'],
            'Mobilya / Ev Dekorasyon' => ['mobilya', 'curtain', 'home accessories', 'seramik', 'furniture'],
            'Oto Servis / Otomotiv' => ['oto', 'auto', 'motor', 'car', 'motors', 'servis', 'tuning', 'detailing', 'ppf'],
            'Emlak' => ['property', 'emlak', 'real estate'],
            'Kuaför / Berber' => ['barber', 'hairdresser', 'kuaför'],
            'Sağlık / Klinik' => ['clinic', 'dental', 'klinik'],
            'Teknik Servis / Tamir' => ['bilgisayar', 'elektrik', 'çilingir', 'tamir', 'alarm', 'güvenlik', 'plumbing'],
            'Yeme-İçme / Kafe' => ['cafe', 'coffee', 'restaurant'],
            'Fotoğraf / Organizasyon' => ['wedding', 'fotoğraf', 'party', 'balloon'],
            'Temizlik / Ev Hizmetleri' => ['temizlik', 'clean', 'handyman', 'ilaçlama'],
        ];

        foreach ($groups as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $sector;
                }
            }
        }

        return 'Yerel İşletme';
    }
}
