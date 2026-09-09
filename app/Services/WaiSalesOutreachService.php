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

        if ($lead->status === 'solution_asked' && $this->hasHumanAffirmation($normalized)) {
            $lead->forceFill([
                'status' => 'demo_offered',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => $this->isTextileSector($sector)
                    ? "Size anlatmak yerine canlı gösterebiliriz. Hazır tekstil demo hattımıza müşteri gibi bir sipariş yazıp logonuzu gönderiyorsunuz; yapay zeka siparişi topluyor ve logonuzu tişört üzerinde hazırlayıp WhatsApp’tan geri sunuyor. Demo numarasını göndereyim mi?"
                    : 'Tamam. Şu an size özel hazırlanmış bir yapay zekayı sistemden oluşturup test linki olarak gönderebilirim. WhatsApp’ınıza bağlamadan önce test edebilirsiniz; beğenirseniz 3 gün ücretsiz kullanabilirsiniz. Hazırlayayım mı?',
                'sector' => $sector,
            ];
        }

        if ($lead->status === 'pain_asked' && $this->hasHumanAffirmation($normalized)) {
            $lead->forceFill([
                'status' => 'solution_asked',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => $this->solutionQuestion($sector),
                'sector' => $sector,
            ];
        }

        if (in_array($lead->status, ['replied', 'ready'], true) && $this->containsAny($normalized, [
            'olabilir','olur','evet','isterim','istiyorum','deneyelim','demo','bilgi alabilirim','anlatın','anlatin',
            'buyurun','buyrun','dinliyorum','nasıl','nasil',
        ])) {
            $lead->forceFill([
                'status' => 'pain_asked',
                'replied_at' => $lead->replied_at ?: now(),
            ])->save();

            return [
                'action' => 'reply',
                'answer' => $this->painQuestion($sector),
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
            'Önce sektöre özel problemi konuştur, sonra WAI’yi çözüm olarak konumlandır, ardından demo teklif et.',
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

        return $this->sectorForName($lead->company_name);
    }

    public function sectorForName(string $companyName): string
    {
        $name = Str::lower($companyName);
        $groups = [
            'Kredi Danışmanlığı / Finansman' => ['kredi','finansman','finans danışman','kredi danışman'],
            'Güzellik / Estetik' => ['beauty','estetic','estetik','nail','spa','makeup','hair studio','güzellik','epilasyon'],
            'Mobilya / Ev Dekorasyon' => ['mobilya','curtain','home accessories','seramik','furniture','dekorasyon'],
            'Oto Servis / Otomotiv' => ['oto','auto','motor','car','motors','servis','tuning','detailing','ppf','otomotiv'],
            'Emlak' => ['property','emlak','real estate','gayrimenkul'],
            'Kuaför / Berber' => ['barber','hairdresser','kuaför','berber'],
            'Sağlık / Klinik' => ['clinic','dental','klinik','diş','dent'],
            'Teknik Servis / Tamir' => ['bilgisayar','elektrik','çilingir','tamir','alarm','güvenlik','plumbing','tesisat','tesisatçı','teknik servis','tech','iletişim'],
            'Yeme-İçme / Kafe' => ['cafe','coffee','restaurant','cookie','pastane','tatlı','bakery','kahve'],
            'Fotoğraf / Organizasyon' => ['wedding','fotoğraf','party','balloon','organizasyon'],
            'Temizlik / Ev Hizmetleri' => ['temizlik','clean','handyman','ilaçlama','pest'],
            'Spor / Fitness' => ['fitness','gym','pilates','spor'],
            'Dövme / Tattoo' => ['tattoo','dövme','piercing'],
            'Çiçekçi' => ['flower','flowers','çiçek','florist'],
            'Moda / Tekstil' => ['moda','tekstil','iç giyim','lingerie','nakış','tişört baskı','tshirt','giyim','pijama','bridal'],
            'Hediyelik / Dekorasyon' => ['gift','hediyelik','mosaic lamps','mozaik lamba'],
        ];

        foreach ($groups as $sector => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return $sector;
                }
            }
        }

        return 'Bilinmeyen';
    }

    private function firstSalesMessage(string $sector): string
    {
        $capability = match ($sector) {
            'Kredi Danışmanlığı / Finansman' => 'WhatsApp’tan gelen kredi türü, uygunluk, evrak ve başvuru sorularını karşılayıp ön bilgileri toplayabilir.',
            'Güzellik / Estetik' => 'WhatsApp’tan gelen işlem, fiyat ve randevu sorularını cevaplayabilir; bilgileri alıp randevuya kadar ilerletebilir.',
            'Mobilya / Ev Dekorasyon' => 'Ürün, ölçü, model, renk ve fiyat taleplerini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Oto Servis / Otomotiv' => 'Araç marka-model, arıza, servis ve randevu taleplerini toplayıp müşteriyi uygun servis sürecine yönlendirebilir.',
            'Emlak' => 'Satılık-kiralık taleplerinde bölge, bütçe ve kriterleri toplayıp uygun portföy sürecine hazırlayabilir.',
            'Kuaför / Berber' => 'Hizmet, fiyat, uygun saat ve randevu sorularını karşılayıp randevuya kadar ilerletebilir.',
            'Sağlık / Klinik' => 'Hizmet ve randevu taleplerini karşılayıp temel bilgileri toplayabilir.',
            'Teknik Servis / Tamir' => 'Arıza ve hizmet taleplerini toplayıp servis veya teklif aşamasına taşıyabilir.',
            'Yeme-İçme / Kafe' => 'Menü, sipariş, rezervasyon ve sık sorulan soruları karşılayabilir.',
            'Fotoğraf / Organizasyon' => 'Tarih, etkinlik türü, paket ve fiyat taleplerini toplayıp teklif aşamasına kadar ilerletebilir.',
            'Temizlik / Ev Hizmetleri' => 'Konum, hizmet türü, tarih ve iş detaylarını alıp teklif veya randevu aşamasına taşıyabilir.',
            'Spor / Fitness' => 'Üyelik, ders, paket ve randevu taleplerini karşılayıp kayıt sürecine ilerletebilir.',
            'Dövme / Tattoo' => 'Tasarım, bölge, ölçü ve randevu taleplerini toplayıp görüşme veya randevu aşamasına taşıyabilir.',
            'Çiçekçi' => 'Ürün, teslimat adresi, tarih ve sipariş taleplerini toplayıp sipariş aşamasına ilerletebilir.',
            'Tişört Baskı / Tekstil', 'Moda / Tekstil' => 'Müşteriden ürün, renk, adet, beden ve baskı bilgisini alabilir; gönderilen logoyu tişört üzerinde görselleştirip WhatsApp’tan müşteriye sunabilir.',
            'Hediyelik / Dekorasyon' => 'Ürün, model, teslimat ve sipariş taleplerini karşılayıp satış aşamasına ilerletebilir.',
            default => 'WhatsApp’tan gelen müşterileri karşılayabilir ve taleplerini doğru ekibe yönlendirebilir.',
        };

        return "Ben WAI ekibinden size ulaşıyorum. Numaranızı işletmenizin internette herkese açık iletişim bilgilerinden buldum.\n\n{$sector} işletmeleri için geliştirdiğimiz bir yapay zeka sistemimiz var. {$capability}\n\nNasıl çalıştığı hakkında bilgi almak ister misiniz?";
    }

    private function painQuestion(string $sector): string
    {
        return match ($sector) {
            'Kredi Danışmanlığı / Finansman' => 'Sizin sektörde müşteriler genelde aynı anda kredi türü, uygunluk, evrak ve ne kadar çıkabileceği gibi sorular soruyor. Yoğun olduğunuzda geç cevap verilen başvurular başka danışmana kayabiliyor. Size de tanıdık geliyor mu?',
            'Güzellik / Estetik' => 'Sizin sektörde müşteriler genelde aynı anda fiyat, işlem detayı, kampanya ve uygun randevu saati soruyor. Özellikle yoğun saatlerde WhatsApp’a geç dönüldüğünde bazı müşteriler başka işletmeye gidebiliyor. Size de tanıdık geliyor mu?',
            'Kuaför / Berber' => 'Sizin sektörde müşteriler en çok fiyat, uygun saat ve randevu soruyor. Yoğunken geç cevap verilen bir müşteri birkaç dakika içinde başka bir salonla anlaşabiliyor. Size de tanıdık geliyor mu?',
            'Oto Servis / Otomotiv' => 'Sizin sektörde müşteriler arıza, servis uygunluğu, fiyat ve randevu için peş peşe yazıyor. Yoğunlukta cevap gecikince müşteri başka servise gidebiliyor. Size de tanıdık geliyor mu?',
            'Mobilya / Ev Dekorasyon' => 'Sizin sektörde müşteriler model, ölçü, renk, fiyat ve teslim süresini aynı anda sorabiliyor. Yoğunlukta cevap gecikince müşteri başka firmadan teklif alabiliyor. Size de tanıdık geliyor mu?',
            'Teknik Servis / Tamir' => 'Sizin sektörde müşteriler arıza, konum, aciliyet, servis saati ve fiyatı aynı anda soruyor. Hızlı dönüş olmayınca özellikle acil müşteriler başka ustaya geçebiliyor. Size de tanıdık geliyor mu?',
            'Emlak' => 'Sizin sektörde müşteriler fiyat, konum, özellikler ve randevu için peş peşe yazıyor. Özellikle sıcak bir alıcıya geç dönülünce başka ilana veya danışmana kayabiliyor. Size de tanıdık geliyor mu?',
            'Tişört Baskı / Tekstil', 'Moda / Tekstil' => 'Müşteriler genelde logo gönderip “Bu tişörtte nasıl görünür?”, ardından adet, renk, baskı türü, fiyat ve teslim süresi soruyor. Her müşteriye tek tek taslak hazırlamak ve aynı soruları cevaplamak ciddi zaman alıyor. Size de tanıdık geliyor mu?',
            default => 'WhatsApp’ta aynı anda birkaç müşteri yazdığında hepsine hızlı ve eksiksiz dönmek zor olabiliyor. Geç cevap verilen müşteriler de başka işletmeye kayabiliyor. Size de tanıdık geliyor mu?',
        };
    }

    private function solutionQuestion(string $sector): string
    {
        $detail = match ($sector) {
            'Kredi Danışmanlığı / Finansman' => 'müşterinin kredi türünü, gelir ve temel uygunluk bilgilerini toplayabilir; gerekli evrakları anlatıp uygun başvuruları size hazır şekilde aktarabilir',
            'Güzellik / Estetik' => 'işlem ve fiyat sorularını cevaplayabilir, uygun saati konuşup randevuya kadar ilerletebilir ve önemli müşterileri size bildirebilir',
            'Kuaför / Berber' => 'fiyat ve hizmet sorularını cevaplayabilir, uygun saatleri konuşup randevuya kadar ilerletebilir',
            'Oto Servis / Otomotiv' => 'araç ve arıza bilgilerini toplayabilir, servis talebini düzenleyip randevu veya teklif aşamasına ilerletebilir',
            'Mobilya / Ev Dekorasyon' => 'ölçü, model, renk ve bütçe bilgisini toplayıp müşteriyi teklif aşamasına kadar hazırlayabilir',
            'Teknik Servis / Tamir' => 'arıza, konum ve aciliyet bilgisini alıp servis veya teklif sürecini başlatabilir',
            'Emlak' => 'müşterinin lokasyon, bütçe ve kriterlerini toplayıp uygun portföy veya görüşme aşamasına hazırlayabilir',
            'Tişört Baskı / Tekstil', 'Moda / Tekstil' => 'ürün modeli, renk, adet, beden ve baskı detaylarını tek konuşmada toplayabilir; DTF ile serigrafiyi ihtiyaca göre ayırabilir ve müşterinin gönderdiği logoyu seçilen tişört üzerinde görselleştirerek WhatsApp’tan geri sunabilir',
            default => 'müşteriyi 7/24 karşılayabilir, sorularını işletmenizin bilgilerine göre cevaplayıp satış, teklif veya randevu aşamasına ilerletebilir',
        };

        return "WAI tam olarak bu noktada devreye giriyor. {$detail}. Siz meşgulken veya uyurken bile WhatsApp boş kalmıyor. Böyle bir sistem olsa işinizi ciddi anlamda rahatlatır mı sizce?";
    }

    public function isTextileSector(string $sector): bool
    {
        return in_array($sector, ['Tişört Baskı / Tekstil', 'Moda / Tekstil'], true);
    }

    private function hasHumanAffirmation(string $message): bool
    {
        return $this->containsAny($message, [
            'evet','doğru','dogru','buyurun','buyrun','olur','olabilir','tamam',
            'dinliyorum','anlatın','anlatin','bilgi alabilirim','nedir','nasıl çalışıyor','nasil calisiyor',
            'aynen','tabii','tabi','elbette','kesinlikle','yarar','rahatlatır','rahatlatir',
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
