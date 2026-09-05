<?php

namespace App\Services;

use App\Models\OutreachLead;
use Illuminate\Support\Str;

class WaiSalesOutreachService
{
    public function decide(OutreachLead $lead, string $message): array
    {
        $sector = $this->sectorFor($lead);
        $normalized = Str::lower(trim($message));

        // İşletmenin otomatik karşılama mesajı ile gerçek insan cevabı aynı
        // 10 saniyelik pakette birleşebilir. Paket içinde "evet / doğru /
        // buyurun" gibi gerçek insan sinyali varsa otomatik mesaj filtresi
        // bütün paketi susturmamalıdır.
        if (
            in_array($lead->status, ['opened', 'ready'], true)
            && $this->isLikelyBusinessAutoReply($normalized)
            && ! $this->hasHumanAffirmation($normalized)
        ) {
            return ['action' => 'ignore', 'sector' => $sector, 'reason' => 'business_auto_reply'];
        }

        if ($this->isExplicitRejection($normalized)) {
            if ($lead->status === 'declined') {
                return ['action' => 'ignore', 'sector' => $sector];
            }

            $lead->forceFill([
                'status' => 'declined',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => 'Tabii, teşekkür ederim. İyi günler dilerim.',
                'sector' => $sector,
            ];
        }

        if ($this->containsAny($normalized, ['fiyat','ücret','ucret','kaç para','ne kadar'])) {
            $becameHot = $lead->status !== 'hot';
            $lead->forceFill([
                'status' => 'hot',
                'replied_at' => $lead->replied_at ?: now(),
                'ai_activated_at' => $lead->ai_activated_at ?: now(),
            ])->save();

            return [
                'action' => 'reply_hot',
                'answer' => '2.990 TL’den başlayan fiyatlarımız var. Daha detaylı görmek için ekip arkadaşlarımızla görüşmek ister misiniz?',
                'sector' => $sector,
                'became_hot' => $becameHot,
            ];
        }

        if ($lead->status === 'demo_offered' && $this->containsAny($normalized, [
            'olur','evet','isterim','istiyorum','deneyelim','hazırla','hazirla','gönder','gonder',
            'arayın','arayin','görüşelim','goruselim','tamam','olabilir',
        ])) {
            $becameHot = $lead->status !== 'hot';
            $lead->forceFill([
                'status' => 'hot',
                'ai_activated_at' => $lead->ai_activated_at ?: now(),
            ])->save();

            return [
                'action' => 'reply_hot',
                'answer' => 'Süper. Size özel demoyu hazırlayıp iletelim. Ekip arkadaşlarımız kısa süre içinde sizinle iletişime geçsin.',
                'sector' => $sector,
                'became_hot' => $becameHot,
            ];
        }

        if (in_array($lead->status, ['replied', 'ready'], true) && $this->containsAny($normalized, [
            'olabilir','olur','evet','isterim','istiyorum','deneyelim','demo','bilgi alabilirim','anlatın','anlatin',
        ])) {
            $lead->forceFill([
                'status' => 'demo_offered',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => 'Tamam. Şu an size özel hazırlanmış bir yapay zekayı sistemden oluşturup test linki olarak gönderebilirim. WhatsApp’ınıza bağlamadan önce test edebilirsiniz; beğenirseniz 3 gün ücretsiz kullanabilirsiniz. Hazırlayayım mı?',
                'sector' => $sector,
            ];
        }

        if ($lead->status === 'opened' || $lead->status === 'ready') {
            $lead->forceFill([
                'status' => 'replied',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => $this->firstSalesMessage($sector),
                'sector' => $sector,
            ];
        }

        return [
            'action' => 'continue',
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
            'Kısa ve doğal WhatsApp dili kullan. Maksimum 2-3 kısa cümle yaz.',
            'Sektörü zaten biliyorsun; tekrar sorma.',
            'Müşteri peş peşe birkaç bilgi verdiyse hepsini birlikte değerlendir ve tek cevap ver.',
            'WAI’yi bu sektörün günlük WhatsApp işlerine göre anlat; teknik kurulum detaylarına kendiliğinden girme.',
            'Müşteri fiyat derse WAI fiyatını soruyor kabul et; araç veya ürün fiyatı sormuş gibi davranma.',
            'İlgilenmeyen kişiyi ikna etmeye çalışma. Ret sonrası tekrar mesaj gönderme.',
            'Sessizlikte takip mesajı üretme.',
            'Uzun liste, tekrar eden fayda cümlesi ve satış/gelir garantisi verme.',
        ]);
    }

    public function sectorFor(OutreachLead $lead): string
    {
        $stored = trim((string) ($lead->sector ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $name = Str::lower($lead->company_name);
        $groups = [
            'Güzellik / Estetik' => ['beauty','estetic','estetik','nail','spa','makeup','hair studio','güzellik'],
            'Mobilya / Ev Dekorasyon' => ['mobilya','curtain','home accessories','seramik','furniture'],
            'Oto Servis / Otomotiv' => ['oto','auto','motor','car','motors','servis','tuning','detailing','ppf'],
            'Emlak' => ['property','emlak','real estate'],
            'Kuaför / Berber' => ['barber','hairdresser','kuaför'],
            'Sağlık / Klinik' => ['clinic','dental','klinik'],
            'Teknik Servis / Tamir' => ['bilgisayar','elektrik','çilingir','tamir','alarm','güvenlik','plumbing'],
            'Yeme-İçme / Kafe' => ['cafe','coffee','restaurant'],
            'Fotoğraf / Organizasyon' => ['wedding','fotoğraf','party','balloon'],
            'Temizlik / Ev Hizmetleri' => ['temizlik','clean','handyman','ilaçlama'],
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

    private function firstSalesMessage(string $sector): string
    {
        $capability = match ($sector) {
            'Güzellik / Estetik' => 'WhatsApp’tan gelen işlem, fiyat ve randevu sorularını cevaplayabilir; bilgileri alıp randevuya kadar ilerletebilir.',
            'Mobilya / Ev Dekorasyon' => 'Ürün, ölçü, model, renk ve fiyat taleplerini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Oto Servis / Otomotiv' => 'Araç marka-model, arıza, servis ve randevu taleplerini toplayıp müşteriyi uygun servis sürecine yönlendirebilir.',
            'Emlak' => 'Satılık-kiralık taleplerinde bölge, bütçe ve kriterleri toplayıp uygun portföy sürecine hazırlayabilir.',
            'Kuaför / Berber' => 'Hizmet, fiyat, uygun saat ve randevu sorularını karşılayıp randevuya kadar ilerletebilir.',
            'Sağlık / Klinik' => 'Hizmet ve randevu taleplerini karşılayıp temel bilgileri toplayabilir.',
            'Teknik Servis / Tamir' => 'Arıza ve hizmet taleplerini toplayıp servis veya teklif aşamasına taşıyabilir.',
            'Yeme-İçme / Kafe' => 'Menü, rezervasyon, sipariş ve sık sorulan soruları otomatik karşılayabilir.',
            'Fotoğraf / Organizasyon' => 'Tarih, etkinlik türü, paket ve fiyat taleplerini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Temizlik / Ev Hizmetleri' => 'Konum, hizmet türü, tarih ve iş detaylarını alıp teklif veya randevu aşamasına taşıyabilir.',
            default => 'WhatsApp’tan gelen müşterileri karşılayabilir, sık sorulan soruları cevaplayabilir ve satış veya randevu aşamasına kadar ilerletebilir.',
        };

        return "Ben WAI ekibinden size ulaşıyorum. Numaranızı işletmenizin internette herkese açık iletişim bilgilerinden buldum.\n\n{$sector} işletmeleri için geliştirdiğimiz bir yapay zeka sistemimiz var. {$capability}\n\nNasıl çalıştığı hakkında bilgi almak ister misiniz?";
    }

    private function hasHumanAffirmation(string $message): bool
    {
        return $this->containsAny($message, [
            'evet','doğru','dogru','buyurun','buyrun','olur','olabilir','tamam',
            'dinliyorum','anlatın','anlatin','bilgi alabilirim','nedir','nasıl çalışıyor','nasil calisiyor',
        ]);
    }

    private function isLikelyBusinessAutoReply(string $message): bool
    {
        return $this->containsAny($message, [
            'ile iletişime geçtiğiniz için teşekkür',
            'ile iletisime gectiginiz icin tesekkur',
            'mesajınız için teşekkür ederiz',
            'mesajiniz icin tesekkur ederiz',
            'size nasıl yardımcı olabiliriz',
            'size nasil yardimci olabiliriz',
            'şu anda size cevap veremiyoruz',
            'su anda size cevap veremiyoruz',
            'çalışma saatlerimiz',
            'calisma saatlerimiz',
        ]);
    }

    private function isExplicitRejection(string $message): bool
    {
        if ($this->containsAny($message, [
            'ilgilenmiyorum','istemiyorum','gerek yok','düşünmüyorum','dusunmuyorum',
            'istemiyoruz','ilgilenmiyoruz','rahatsız etmeyin','rahatsiz etmeyin',
            'mesaj atmayın','mesaj atmayin','aramayın','aramayin',
        ])) {
            return true;
        }

        return in_array(trim($message), [
            'teşekkürler','tesekkurler','teşekkür ederim','tesekkur ederim',
            'sağ olun','sag olun','sağol','sagol','yok teşekkürler','yok tesekkurler',
        ], true);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
