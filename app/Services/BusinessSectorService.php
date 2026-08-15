<?php

namespace App\Services;

class BusinessSectorService
{
    /*
    |--------------------------------------------------------------------------
    | WAI SEKTÖR KATALOĞU
    |--------------------------------------------------------------------------
    |
    | Kullanıcı teknik lead scoring profilini seçmez.
    | Kendi sektörünü seçer, sistem doğru profile otomatik bağlar.
    |
    */

    public static function options(): array
    {
        return [
            'E-Ticaret ve Perakende' => [
                'ecommerce' => 'E-Ticaret / Online Mağaza',
                'marketplace_seller' => 'Pazaryeri Satıcısı',
                'retail' => 'Mağazacılık / Perakende',
                'wholesale' => 'Toptan Satış',
                'market' => 'Market / Süpermarket',
                'grocery' => 'Bakkal',
                'delicatessen' => 'Şarküteri',
                'gift_shop' => 'Hediyelik Eşya',
                'stationery' => 'Kırtasiye',
                'toy_store' => 'Oyuncak Mağazası',
                'baby_store' => 'Bebek / Çocuk Ürünleri',
                'home_goods' => 'Ev / Yaşam Ürünleri',
                'pet_shop' => 'Pet Shop',
                'bookstore' => 'Kitap / Kitabevi',
                'hobby_store' => 'Hobi / El Sanatları',
                'office_supplies' => 'Ofis Malzemeleri',
                'consumer_goods' => 'Tüketici Ürünleri',
            ],

            'Gıda ve Tarım' => [
                'olive_oil' => 'Zeytin / Zeytinyağı',
                'food_production' => 'Gıda Üretimi',
                'food_wholesale' => 'Gıda Toptancılığı',
                'organic_food' => 'Organik / Doğal Ürünler',
                'bakery' => 'Fırın / Unlu Mamuller',
                'patisserie' => 'Pastane',
                'butcher' => 'Kasap',
                'dairy' => 'Süt / Süt Ürünleri',
                'nuts' => 'Kuruyemiş',
                'coffee' => 'Kahve / Kahve Ürünleri',
                'tea' => 'Çay / Bitki Çayı',
                'spices' => 'Baharat',
                'agriculture' => 'Tarım',
                'farm' => 'Çiftlik',
                'greenhouse' => 'Seracılık',
                'livestock' => 'Hayvancılık',
                'beekeeping' => 'Arıcılık / Bal',
                'seed_fertilizer' => 'Tohum / Gübre / Tarım Ürünleri',
                'fruit_vegetable' => 'Meyve / Sebze',
                'seafood' => 'Balık / Deniz Ürünleri',
                'frozen_food' => 'Dondurulmuş Gıda',
                'beverage' => 'İçecek Üretimi / Satışı',
            ],

            'Restoran ve Yeme İçme' => [
                'restaurant' => 'Restoran',
                'cafe' => 'Kafe',
                'fast_food' => 'Fast Food',
                'pizzeria' => 'Pizzacı',
                'dessert_shop' => 'Tatlıcı',
                'ice_cream' => 'Dondurma',
                'catering' => 'Catering',
                'meal_delivery' => 'Yemek Sipariş / Paket Servis',
                'breakfast' => 'Kahvaltı Salonu',
                'steakhouse' => 'Et / Steakhouse',
                'seafood_restaurant' => 'Balık Restoranı',
            ],

            'Finans ve Sigorta' => [
                'credit_consulting' => 'Kredi / Finansman Danışmanlığı',
                'finance' => 'Finans',
                'financial_consulting' => 'Finansal Danışmanlık',
                'insurance' => 'Sigorta',
                'insurance_agency' => 'Sigorta Acentesi',
                'accounting' => 'Muhasebe / Mali Müşavirlik',
                'investment_consulting' => 'Yatırım Danışmanlığı',
                'payment_services' => 'Ödeme / Tahsilat Hizmetleri',
                'leasing' => 'Leasing / Finansal Kiralama',
                'factoring' => 'Faktoring',
            ],

            'Sağlık ve Klinik' => [
                'medical_clinic' => 'Özel Klinik',
                'hospital' => 'Hastane / Tıp Merkezi',
                'dentist' => 'Diş Kliniği / Diş Hekimi',
                'aesthetic_clinic' => 'Estetik Klinik',
                'dermatology' => 'Dermatoloji',
                'dietitian' => 'Diyetisyen / Beslenme',
                'physiotherapy' => 'Fizyoterapi',
                'psychology' => 'Psikolog',
                'psychiatry' => 'Psikiyatri',
                'veterinary' => 'Veteriner Kliniği',
                'pharmacy' => 'Eczane',
                'home_health' => 'Evde Sağlık',
                'medical_products' => 'Medikal Ürünler',
                'optical' => 'Optik / Gözlük',
                'laboratory' => 'Laboratuvar',
                'radiology' => 'Görüntüleme / Radyoloji',
                'hearing_center' => 'İşitme Merkezi',
                'orthopedics' => 'Ortopedi',
            ],

            'Güzellik ve Kişisel Bakım' => [
                'beauty_center' => 'Güzellik Merkezi',
                'hairdresser' => 'Kadın Kuaförü',
                'barber' => 'Erkek Kuaförü / Berber',
                'nail_studio' => 'Protez Tırnak / Nail Studio',
                'spa' => 'Spa / Masaj',
                'tattoo_piercing' => 'Dövme / Tattoo / Piercing',
                'laser_epilation' => 'Lazer Epilasyon',
                'permanent_makeup' => 'Kalıcı Makyaj',
                'cosmetics' => 'Kozmetik',
                'personal_care' => 'Kişisel Bakım Ürünleri',
                'makeup_artist' => 'Makyaj Sanatçısı',
                'hair_transplant' => 'Saç Ekimi',
            ],

            'Emlak ve İnşaat' => [
                'real_estate' => 'Emlak / Gayrimenkul',
                'real_estate_project' => 'Konut / Gayrimenkul Projesi',
                'construction' => 'İnşaat',
                'contractor' => 'Müteahhitlik',
                'architecture' => 'Mimarlık',
                'interior_design' => 'İç Mimarlık',
                'decoration' => 'Dekorasyon',
                'property_management' => 'Site / Gayrimenkul Yönetimi',
                'land_sale' => 'Arsa / Tarla Satışı',
                'prefabricated_house' => 'Prefabrik / Çelik Ev',
            ],

            'Ev, Yapı ve Teknik Hizmetler' => [
                'furniture' => 'Mobilya',
                'kitchen_bath' => 'Mutfak / Banyo',
                'glass_balcony' => 'Cam Balkon',
                'shutter_blind' => 'Perde / Panjur / Sineklik',
                'plumbing' => 'Su Tesisatı / Tesisatçı',
                'electrician' => 'Elektrikçi',
                'locksmith' => 'Çilingir',
                'hvac' => 'Klima / Isıtma / Soğutma',
                'boiler_service' => 'Kombi Servisi',
                'white_goods_service' => 'Beyaz Eşya Servisi',
                'appliance_service' => 'Teknik Servis',
                'cleaning' => 'Temizlik',
                'carpet_cleaning' => 'Halı / Koltuk Yıkama',
                'pest_control' => 'İlaçlama',
                'landscaping' => 'Peyzaj / Bahçe',
                'solar_energy' => 'Güneş Enerjisi',
                'pool_service' => 'Havuz Yapım / Bakım',
                'roofing' => 'Çatı / İzolasyon',
                'painting' => 'Boya / Badana',
                'elevator' => 'Asansör',
                'door_window' => 'Kapı / Pencere',
                'aluminium_joinery' => 'Alüminyum Doğrama',
                'marble_granite' => 'Mermer / Granit',
            ],

            'Otomotiv' => [
                'car_dealer' => 'Oto Galeri / Araç Satışı',
                'used_car' => 'İkinci El Araç',
                'car_service' => 'Oto Servis',
                'auto_repair' => 'Oto Tamir',
                'auto_electric' => 'Oto Elektrik',
                'tire_service' => 'Lastikçi',
                'car_wash' => 'Oto Yıkama',
                'detailing' => 'Oto Detailing',
                'car_rental' => 'Rent a Car',
                'towing' => 'Oto Çekici',
                'roadside_assistance' => 'Yol Yardım',
                'motorcycle' => 'Motosiklet Satış / Servis',
                'auto_parts' => 'Oto Yedek Parça',
                'auto_expertise' => 'Oto Ekspertiz',
                'car_insurance' => 'Trafik / Kasko Sigortası',
                'car_accessories' => 'Oto Aksesuar',
                'battery_service' => 'Akü Servisi',
            ],

            'Nakliyat ve Lojistik' => [
                'transportation' => 'Evden Eve Nakliyat',
                'logistics' => 'Lojistik',
                'courier' => 'Kurye',
                'cargo' => 'Kargo',
                'warehouse' => 'Depolama',
                'international_shipping' => 'Uluslararası Taşımacılık',
                'taxi_transfer' => 'Taksi / Transfer',
                'freight' => 'Yük / Parsiyel Taşımacılık',
                'moving_company' => 'Taşınma Hizmeti',
            ],

            'Turizm ve Konaklama' => [
                'hotel' => 'Otel',
                'boutique_hotel' => 'Butik Otel',
                'pension' => 'Pansiyon',
                'villa_rental' => 'Villa / Tatil Evi Kiralama',
                'travel_agency' => 'Seyahat Acentesi',
                'tour_operator' => 'Tur / Gezi',
                'airport_transfer' => 'Havalimanı Transfer',
                'yacht_tour' => 'Tekne / Yat Turu',
                'camping' => 'Kamp / Karavan',
                'thermal_hotel' => 'Termal Otel',
            ],

            'Yazılım ve Teknoloji' => [
                'software' => 'Yazılım',
                'saas' => 'SaaS / Yazılım Hizmeti',
                'web_design' => 'Web Tasarım',
                'mobile_app' => 'Mobil Uygulama',
                'ai_services' => 'Yapay Zekâ Hizmetleri',
                'automation' => 'Otomasyon Sistemleri',
                'it_services' => 'Bilişim / IT',
                'cyber_security' => 'Siber Güvenlik',
                'hosting_domain' => 'Hosting / Domain',
                'computer_service' => 'Bilgisayar Teknik Servisi',
                'phone_service' => 'Telefon Teknik Servisi',
                'electronics' => 'Elektronik / Teknoloji',
                'telecom' => 'Telekomünikasyon',
                'erp_crm' => 'ERP / CRM Yazılımı',
                'pos_systems' => 'POS / Ödeme Sistemleri',
                'cloud_services' => 'Bulut / Sunucu Hizmetleri',
            ],

            'Reklam ve Medya' => [
                'digital_agency' => 'Dijital Ajans',
                'advertising_agency' => 'Reklam Ajansı',
                'social_media' => 'Sosyal Medya Ajansı',
                'seo' => 'SEO',
                'performance_marketing' => 'Performans Pazarlama',
                'graphic_design' => 'Grafik Tasarım',
                'photography' => 'Fotoğrafçılık',
                'video_production' => 'Video Prodüksiyon',
                'printing' => 'Matbaa / Baskı',
                'promotional_products' => 'Promosyon Ürünleri',
                'influencer_agency' => 'Influencer / İçerik Ajansı',
                'outdoor_advertising' => 'Açık Hava Reklamcılığı',
            ],

            'Eğitim' => [
                'education' => 'Eğitim / Kurs',
                'private_school' => 'Özel Okul',
                'kindergarten' => 'Kreş / Anaokulu',
                'language_school' => 'Dil Kursu',
                'driving_school' => 'Sürücü Kursu',
                'private_tutoring' => 'Özel Ders',
                'exam_preparation' => 'Sınav Hazırlık Kursu',
                'online_education' => 'Online Eğitim',
                'coaching' => 'Koçluk / Mentorluk',
                'sports_school' => 'Spor Okulu',
                'music_school' => 'Müzik Kursu',
                'art_school' => 'Sanat Kursu',
            ],

            'Spor ve Fitness' => [
                'gym' => 'Spor Salonu / Fitness',
                'personal_trainer' => 'Personal Trainer',
                'pilates' => 'Pilates',
                'yoga' => 'Yoga',
                'swimming' => 'Yüzme Kursu',
                'football_school' => 'Futbol Okulu',
                'sports_products' => 'Spor Ürünleri',
                'martial_arts' => 'Dövüş Sporları',
                'tennis' => 'Tenis Kursu',
            ],

            'Hukuk ve Kurumsal Hizmetler' => [
                'law' => 'Avukatlık / Hukuk',
                'consulting' => 'Danışmanlık',
                'corporate_consulting' => 'Kurumsal Danışmanlık',
                'human_resources' => 'İnsan Kaynakları',
                'recruitment' => 'İşe Alım / Kariyer',
                'call_center' => 'Çağrı Merkezi',
                'virtual_office' => 'Sanal Ofis',
                'translation' => 'Tercüme / Çeviri',
                'patent_trademark' => 'Marka / Patent Danışmanlığı',
                'occupational_safety' => 'İş Güvenliği',
            ],

            'Moda ve Tekstil' => [
                'textile' => 'Tekstil',
                'clothing' => 'Giyim / Moda',
                'women_clothing' => 'Kadın Giyim',
                'men_clothing' => 'Erkek Giyim',
                'kids_clothing' => 'Çocuk Giyim',
                'shoes' => 'Ayakkabı',
                'bags' => 'Çanta',
                'accessories' => 'Aksesuar',
                'jewelry' => 'Kuyumculuk / Mücevher',
                'bijouterie' => 'Bijuteri',
                'wedding_dress' => 'Gelinlik / Abiye',
                'workwear' => 'İş Kıyafetleri',
            ],

            'Üretim ve Sanayi' => [
                'manufacturing' => 'Üretim / İmalat',
                'factory' => 'Fabrika',
                'machinery' => 'Makine / Endüstriyel Ekipman',
                'metal' => 'Metal / Çelik',
                'plastic' => 'Plastik',
                'packaging' => 'Ambalaj',
                'chemical' => 'Kimya',
                'industrial_supply' => 'Endüstriyel Tedarik',
                'building_materials' => 'Yapı Malzemeleri',
                'aluminium' => 'Alüminyum',
                'wood_products' => 'Ahşap Ürünleri',
                'glass_industry' => 'Cam Sanayi',
                'electrical_equipment' => 'Elektrik / Elektronik Üretimi',
                'automation_industry' => 'Endüstriyel Otomasyon',
            ],

            'Organizasyon ve Etkinlik' => [
                'event_planning' => 'Organizasyon / Etkinlik',
                'wedding_planning' => 'Düğün Organizasyonu',
                'event_venue' => 'Düğün / Davet Mekânı',
                'entertainment' => 'Eğlence',
                'music' => 'Müzik / DJ',
                'party_supplies' => 'Parti / Organizasyon Ürünleri',
                'conference' => 'Kongre / Konferans Organizasyonu',
                'fair_organization' => 'Fuar Organizasyonu',
            ],

            'Diğer Hizmetler' => [
                'security' => 'Güvenlik Hizmetleri',
                'florist' => 'Çiçekçi',
                'laundry' => 'Kuru Temizleme / Çamaşırhane',
                'repair' => 'Tamir / Onarım',
                'pet_services' => 'Evcil Hayvan Hizmetleri',
                'funeral_services' => 'Cenaze Hizmetleri',
                'tailor' => 'Terzi',
                'watch_repair' => 'Saat / Takı Tamiri',
                'other' => 'Diğer / Listede Yok',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEKTÖR -> LEAD SCORING PROFİLİ
    |--------------------------------------------------------------------------
    */

    public static function profileForSector(?string $sector): string
    {
        $sector = trim((string) $sector);

        if ($sector === '') {
            return 'general';
        }

        if (in_array($sector, self::financeSectors(), true)) {
            return 'finance';
        }

        if (in_array($sector, self::appointmentSectors(), true)) {
            return 'appointment';
        }

        if (in_array($sector, self::realEstateSectors(), true)) {
            return 'real_estate';
        }

        if (in_array($sector, self::automotiveSectors(), true)) {
            return 'automotive';
        }

        if (in_array($sector, self::emergencySectors(), true)) {
            return 'emergency';
        }

        if (in_array($sector, self::ecommerceSectors(), true)) {
            return 'ecommerce';
        }

        if (in_array($sector, self::serviceSectors(), true)) {
            return 'service';
        }

        return 'general';
    }

    public static function label(?string $sector): string
    {
        $sector = trim((string) $sector);

        foreach (self::options() as $group) {
            if (isset($group[$sector])) {
                return $group[$sector];
            }
        }

        return $sector !== ''
            ? $sector
            : 'Belirtilmedi';
    }

    private static function financeSectors(): array
    {
        return [
            'credit_consulting',
            'finance',
            'financial_consulting',
            'insurance',
            'insurance_agency',
            'investment_consulting',
            'car_insurance',
            'payment_services',
            'leasing',
            'factoring',
        ];
    }

    private static function appointmentSectors(): array
    {
        return [
            'medical_clinic',
            'hospital',
            'dentist',
            'aesthetic_clinic',
            'dermatology',
            'dietitian',
            'physiotherapy',
            'psychology',
            'psychiatry',
            'veterinary',
            'home_health',
            'laboratory',
            'radiology',
            'hearing_center',
            'orthopedics',

            'beauty_center',
            'hairdresser',
            'barber',
            'nail_studio',
            'spa',
            'tattoo_piercing',
            'laser_epilation',
            'permanent_makeup',
            'makeup_artist',
            'hair_transplant',

            'education',
            'private_school',
            'kindergarten',
            'language_school',
            'driving_school',
            'private_tutoring',
            'exam_preparation',
            'online_education',
            'coaching',
            'sports_school',
            'music_school',
            'art_school',

            'gym',
            'personal_trainer',
            'pilates',
            'yoga',
            'swimming',
            'football_school',
            'martial_arts',
            'tennis',

            'hotel',
            'boutique_hotel',
            'pension',
            'villa_rental',
            'travel_agency',
            'tour_operator',
            'yacht_tour',
            'camping',
            'thermal_hotel',

            'event_planning',
            'wedding_planning',
            'event_venue',
            'conference',
            'fair_organization',
        ];
    }

    private static function realEstateSectors(): array
    {
        return [
            'real_estate',
            'real_estate_project',
            'land_sale',
            'property_management',
            'prefabricated_house',
        ];
    }

    private static function automotiveSectors(): array
    {
        return [
            'car_dealer',
            'used_car',
            'car_service',
            'auto_repair',
            'auto_electric',
            'tire_service',
            'car_wash',
            'detailing',
            'car_rental',
            'motorcycle',
            'auto_parts',
            'auto_expertise',
            'car_accessories',
            'battery_service',
        ];
    }

    private static function emergencySectors(): array
    {
        return [
            'towing',
            'roadside_assistance',
            'locksmith',
            'plumbing',
            'electrician',
        ];
    }

    private static function ecommerceSectors(): array
    {
        return [
            'ecommerce',
            'marketplace_seller',
            'retail',
            'wholesale',
            'market',
            'grocery',
            'delicatessen',
            'gift_shop',
            'stationery',
            'toy_store',
            'baby_store',
            'home_goods',
            'pet_shop',
            'bookstore',
            'hobby_store',
            'office_supplies',
            'consumer_goods',

            'olive_oil',
            'food_production',
            'food_wholesale',
            'organic_food',
            'bakery',
            'patisserie',
            'butcher',
            'dairy',
            'nuts',
            'coffee',
            'tea',
            'spices',
            'fruit_vegetable',
            'seafood',
            'frozen_food',
            'beverage',

            'restaurant',
            'cafe',
            'fast_food',
            'pizzeria',
            'dessert_shop',
            'ice_cream',
            'catering',
            'meal_delivery',
            'breakfast',
            'steakhouse',
            'seafood_restaurant',

            'pharmacy',
            'medical_products',
            'optical',
            'cosmetics',
            'personal_care',

            'electronics',

            'textile',
            'clothing',
            'women_clothing',
            'men_clothing',
            'kids_clothing',
            'shoes',
            'bags',
            'accessories',
            'jewelry',
            'bijouterie',
            'wedding_dress',
            'workwear',

            'sports_products',
            'florist',
            'party_supplies',
        ];
    }

    private static function serviceSectors(): array
    {
        return [
            'accounting',
            'law',
            'consulting',
            'corporate_consulting',
            'human_resources',
            'recruitment',
            'call_center',
            'virtual_office',
            'translation',
            'patent_trademark',
            'occupational_safety',

            'construction',
            'contractor',
            'architecture',
            'interior_design',
            'decoration',
            'furniture',
            'kitchen_bath',
            'glass_balcony',
            'shutter_blind',
            'hvac',
            'boiler_service',
            'white_goods_service',
            'appliance_service',
            'cleaning',
            'carpet_cleaning',
            'pest_control',
            'landscaping',
            'solar_energy',
            'pool_service',
            'roofing',
            'painting',
            'elevator',
            'door_window',
            'aluminium_joinery',
            'marble_granite',

            'transportation',
            'logistics',
            'courier',
            'cargo',
            'warehouse',
            'international_shipping',
            'taxi_transfer',
            'freight',
            'moving_company',
            'airport_transfer',

            'software',
            'saas',
            'web_design',
            'mobile_app',
            'ai_services',
            'automation',
            'it_services',
            'cyber_security',
            'hosting_domain',
            'computer_service',
            'phone_service',
            'telecom',
            'erp_crm',
            'pos_systems',
            'cloud_services',

            'digital_agency',
            'advertising_agency',
            'social_media',
            'seo',
            'performance_marketing',
            'graphic_design',
            'photography',
            'video_production',
            'printing',
            'promotional_products',
            'influencer_agency',
            'outdoor_advertising',

            'agriculture',
            'farm',
            'greenhouse',
            'livestock',
            'beekeeping',
            'seed_fertilizer',

            'manufacturing',
            'factory',
            'machinery',
            'metal',
            'plastic',
            'packaging',
            'chemical',
            'industrial_supply',
            'building_materials',
            'aluminium',
            'wood_products',
            'glass_industry',
            'electrical_equipment',
            'automation_industry',

            'security',
            'laundry',
            'repair',
            'pet_services',
            'funeral_services',
            'tailor',
            'watch_repair',
            'music',
            'entertainment',
        ];
    }
}