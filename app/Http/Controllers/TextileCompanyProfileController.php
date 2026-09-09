<?php

namespace App\Http\Controllers;

use App\Models\AiBot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TextileCompanyProfileController extends Controller
{
    private const BOT_ID = 51;
    private const ACCESS_TOKEN = 'txf_V7m4Qp9L2zN8cR5kH3sW6dF1aB0uYxEe';
    private const START = '--- FIRMA BILGI FORMU BASLANGIC ---';
    private const END = '--- FIRMA BILGI FORMU BITIS ---';

    public function show(string $token): View
    {
        $this->authorizeToken($token);
        $bot = AiBot::query()->findOrFail(self::BOT_ID);
        $values = array_merge($this->defaults(), $this->storedValues((string) $bot->company_rules));

        return view('textile-company-profile', compact('values', 'token'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->authorizeToken($token);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'business_summary' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:750'],
            'website_social' => ['nullable', 'string', 'max:500'],
            'visit_policy' => ['required', 'string', 'max:1000'],
            'working_hours' => ['nullable', 'string', 'max:500'],
            'products_and_colors' => ['required', 'string', 'max:1500'],
            'product_types' => ['nullable', 'string', 'max:1000'],
            'size_range' => ['nullable', 'string', 'max:750'],
            'fabric_options' => ['nullable', 'string', 'max:1000'],
            'stock_model' => ['nullable', 'string', 'max:750'],
            'cuts_and_necks' => ['nullable', 'string', 'max:1000'],
            'kids_and_women' => ['nullable', 'string', 'max:750'],
            'mixed_sizes_colors' => ['nullable', 'string', 'max:750'],
            'color_catalog' => ['nullable', 'string', 'max:750'],
            'fabric_quality' => ['required', 'string', 'max:1000'],
            'minimum_order' => ['required', 'string', 'max:1000'],
            'print_methods' => ['required', 'array', 'min:1'],
            'print_methods.*' => ['string', 'max:80'],
            'print_limits' => ['required', 'string', 'max:1000'],
            'print_recommendation' => ['nullable', 'string', 'max:1000'],
            'print_positions' => ['nullable', 'string', 'max:750'],
            'artwork_formats' => ['nullable', 'string', 'max:750'],
            'artwork_quality' => ['nullable', 'string', 'max:750'],
            'print_durability' => ['nullable', 'string', 'max:1000'],
            'washing_instructions' => ['nullable', 'string', 'max:1000'],
            'color_print_tolerance' => ['nullable', 'string', 'max:750'],
            'background_removal' => ['nullable', 'string', 'max:750'],
            'design_revisions' => ['nullable', 'string', 'max:750'],
            'copyright_policy' => ['nullable', 'string', 'max:750'],
            'design_service' => ['nullable', 'string', 'max:1000'],
            'sample_price' => ['required', 'string', 'max:1000'],
            'bulk_pricing' => ['required', 'string', 'max:1500'],
            'quote_requirements' => ['required', 'string', 'max:1000'],
            'price_factors' => ['nullable', 'string', 'max:1000'],
            'vat_invoice' => ['nullable', 'string', 'max:750'],
            'quantity_discounts' => ['nullable', 'string', 'max:1000'],
            'extra_fees' => ['nullable', 'string', 'max:1000'],
            'quote_validity' => ['nullable', 'string', 'max:500'],
            'production_time' => ['nullable', 'string', 'max:500'],
            'rush_order' => ['nullable', 'string', 'max:750'],
            'approval_process' => ['nullable', 'string', 'max:1000'],
            'personalization' => ['nullable', 'string', 'max:1000'],
            'packaging_labeling' => ['nullable', 'string', 'max:1000'],
            'repeat_order' => ['nullable', 'string', 'max:750'],
            'cancellation_changes' => ['nullable', 'string', 'max:1000'],
            'shipping_info' => ['nullable', 'string', 'max:750'],
            'pickup_tracking' => ['nullable', 'string', 'max:750'],
            'shipping_damage' => ['nullable', 'string', 'max:750'],
            'payment_info' => ['nullable', 'string', 'max:750'],
            'deposit_balance' => ['nullable', 'string', 'max:750'],
            'cash_on_delivery' => ['nullable', 'string', 'max:500'],
            'after_sales' => ['nullable', 'string', 'max:750'],
            'defect_policy' => ['nullable', 'string', 'max:750'],
            'custom_product_returns' => ['nullable', 'string', 'max:1000'],
            'complaint_period' => ['nullable', 'string', 'max:500'],
            'frequent_questions' => ['nullable', 'string', 'max:1500'],
            'forbidden_promises' => ['nullable', 'string', 'max:1000'],
            'human_contact' => ['nullable', 'string', 'max:500'],
            'assistant_tone' => ['nullable', 'string', 'max:750'],
            'supported_languages' => ['nullable', 'string', 'max:500'],
            'order_closing_flow' => ['nullable', 'string', 'max:1000'],
            'customer_info_to_collect' => ['nullable', 'string', 'max:1000'],
            'unsupported_requests' => ['nullable', 'string', 'max:1000'],
            'confirmed' => ['accepted'],
        ]);

        unset($validated['confirmed']);
        $bot = AiBot::query()->findOrFail(self::BOT_ID);
        $existing = $this->withoutStoredProfile((string) $bot->company_rules);
        $profile = $this->toKnowledge($validated);
        $rules = trim($existing."\n\n".self::START."\n".$profile."\nPROFILE_JSON:".json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n".self::END);

        $bot->forceFill([
            'company_name' => $validated['company_name'],
            'company_description' => $validated['business_summary'] ?: $bot->company_description,
            'working_hours' => $validated['working_hours'] ?: $bot->working_hours,
            'cargo_information' => $validated['shipping_info'] ?: $bot->cargo_information,
            'payment_information' => $validated['payment_info'] ?: $bot->payment_information,
            'return_policy' => $validated['after_sales'] ?: $bot->return_policy,
            'company_rules' => $rules,
        ])->save();

        return back()->with('success', 'Bilgiler kaydedildi ve yapay zekâya öğretildi.');
    }

    private function authorizeToken(string $token): void
    {
        abort_unless(hash_equals(self::ACCESS_TOKEN, $token), 404);
    }

    private function defaults(): array
    {
        return [
            'company_name' => 'İstanbul Tişört Baskı',
            'company_phone' => '0539 238 9098',
            'business_summary' => 'Tişört üretimi ve profesyonel tekstil baskı hizmetleri sunuyoruz.',
            'address' => '',
            'website_social' => '',
            'visit_policy' => 'Genellikle online çalışıyoruz. Adetli siparişlerde müşterilerle yüz yüze görüşme sağlıyoruz.',
            'working_hours' => '',
            'products_and_colors' => 'Siyah ve beyaz renkli tişörtlerde oversize kalıplarımız mevcuttur. Diğer renklerde minimum 60 adet olacak şekilde üretim yapabiliriz.',
            'product_types' => '',
            'size_range' => '',
            'fabric_options' => '',
            'stock_model' => '',
            'cuts_and_necks' => '',
            'kids_and_women' => '',
            'mixed_sizes_colors' => '',
            'color_catalog' => '',
            'fabric_quality' => 'Standart tişört kalitemiz 30/1 süprem penye, %100 pamuklu kumaştır; metrekarede ortalama 150–160 gram gelir.',
            'minimum_order' => 'Tişört firmamızdan alınırsa minimum adet yoktur; 1 adet sipariş verilebilir. Ürünleri müşteri temin edecekse minimum 30 adet olmalıdır.',
            'print_methods' => ['Dijital baskı', 'Transfer baskı'],
            'print_limits' => 'Dijital baskı maksimum 40 cm uzunluk ve 35 cm genişlik olabilir. Transfer baskıda standart maksimum genişlik 29 cm’dir; üzerindeki ölçülerde fiyat farkı çıkar.',
            'print_recommendation' => '',
            'print_positions' => '',
            'artwork_formats' => 'JPG, PNG ve WEBP görseller kabul edilir.',
            'artwork_quality' => '',
            'print_durability' => '',
            'washing_instructions' => '',
            'color_print_tolerance' => '',
            'background_removal' => '',
            'design_revisions' => '',
            'copyright_policy' => '',
            'design_service' => '',
            'sample_price' => '1 adet numune: %100 pamuklu tişört + transfer veya dijital baskı + kargo dahil 750 TL.',
            'bulk_pricing' => '5–30 adet beyaz tişörtte dijital baskı: yalnız ön logo baskılı 300 TL/adet; ön ve arka logo baskılı 385 TL/adet.',
            'quote_requirements' => 'Toptan fiyat için tişört rengi, baskı görseli, baskı konumu ve ölçüsü, tişört adedi ve varsa hazır tasarım istenir.',
            'price_factors' => '',
            'vat_invoice' => '',
            'quantity_discounts' => '',
            'extra_fees' => '',
            'quote_validity' => '',
            'production_time' => '',
            'rush_order' => '',
            'approval_process' => 'Üretimden önce baskı ön izlemesi müşteriye gönderilir ve müşteri onayı alınır.',
            'personalization' => '',
            'packaging_labeling' => '',
            'repeat_order' => '',
            'cancellation_changes' => '',
            'shipping_info' => '',
            'pickup_tracking' => '',
            'shipping_damage' => '',
            'payment_info' => '',
            'deposit_balance' => '',
            'cash_on_delivery' => '',
            'after_sales' => '',
            'defect_policy' => '',
            'custom_product_returns' => '',
            'complaint_period' => '',
            'frequent_questions' => '',
            'forbidden_promises' => 'Asistan, firma tarafından açıkça belirtilmeyen fiyat, stok, teslim tarihi veya indirim sözü vermemelidir.',
            'human_contact' => '',
            'assistant_tone' => 'Samimi, profesyonel, kısa ve doğal konuşsun; müşterinin sorusuna doğrudan cevap versin ve aynı metni tekrarlamasın.',
            'supported_languages' => 'Türkçe',
            'order_closing_flow' => 'Eksik sipariş bilgilerini sırayla tamamlasın; baskı ön izlemesi onaylandıktan sonra ödeme aşamasına geçirsin.',
            'customer_info_to_collect' => 'Ürün, renk, beden dağılımı, adet, baskı görseli, baskı konumu ve ölçüsünü toplasın.',
            'unsupported_requests' => '',
        ];
    }

    private function storedValues(string $rules): array
    {
        if (!preg_match('/'.preg_quote(self::START, '/').'.*?PROFILE_JSON:(\{.*\})\s*'.preg_quote(self::END, '/').'/su', $rules, $match)) {
            return [];
        }

        $decoded = json_decode($match[1], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function withoutStoredProfile(string $rules): string
    {
        return trim((string) preg_replace('/\s*'.preg_quote(self::START, '/').'.*?'.preg_quote(self::END, '/').'\s*/su', "\n", $rules));
    }

    private function toKnowledge(array $data): string
    {
        $labels = [
            'company_phone' => 'Firma telefonu',
            'business_summary' => 'Firma özeti',
            'address' => 'Adres ve ziyaret bilgisi',
            'website_social' => 'Web sitesi ve sosyal medya',
            'visit_policy' => 'Çalışma ve ziyaret düzeni',
            'working_hours' => 'Çalışma saatleri',
            'products_and_colors' => 'Ürün, kalıp ve renkler',
            'product_types' => 'Ürün çeşitleri',
            'size_range' => 'Beden aralığı',
            'fabric_options' => 'Alternatif kumaş seçenekleri',
            'stock_model' => 'Stoktan ve üretimden çalışma',
            'cuts_and_necks' => 'Kalıp, yaka ve kol seçenekleri',
            'kids_and_women' => 'Kadın ve çocuk ürünleri',
            'mixed_sizes_colors' => 'Karışık beden ve renk siparişi',
            'color_catalog' => 'Renk kartelası ve stok teyidi',
            'fabric_quality' => 'Kumaş kalitesi',
            'minimum_order' => 'Minimum sipariş kuralları',
            'print_methods' => 'Baskı yöntemleri',
            'print_limits' => 'Baskı ölçü sınırları',
            'print_recommendation' => 'Baskı tekniği önerme kuralları',
            'print_positions' => 'Uygulanabilen baskı konumları',
            'artwork_formats' => 'Kabul edilen dosya türleri',
            'artwork_quality' => 'Görsel kalite ve çözünürlük kuralları',
            'print_durability' => 'Baskı kalıcılığı',
            'washing_instructions' => 'Yıkama ve bakım talimatı',
            'color_print_tolerance' => 'Renk ve baskı toleransı',
            'background_removal' => 'Arka plan kaldırma ve dosya düzenleme',
            'design_revisions' => 'Tasarım revizyon hakkı',
            'copyright_policy' => 'Telifli görsel politikası',
            'design_service' => 'Tasarım desteği',
            'sample_price' => 'Numune fiyatı',
            'bulk_pricing' => 'Bilinen fiyatlar',
            'quote_requirements' => 'Teklif için gerekli bilgiler',
            'price_factors' => 'Fiyatı değiştiren unsurlar',
            'vat_invoice' => 'KDV ve fatura bilgisi',
            'quantity_discounts' => 'Adet indirimleri',
            'extra_fees' => 'Ek ücretler',
            'quote_validity' => 'Teklif geçerlilik süresi',
            'production_time' => 'Üretim süresi',
            'rush_order' => 'Acil sipariş kuralları',
            'approval_process' => 'Ön izleme ve müşteri onayı',
            'personalization' => 'İsim ve numara kişiselleştirme',
            'packaging_labeling' => 'Paketleme, etiket ve marka uygulamaları',
            'repeat_order' => 'Tekrar sipariş',
            'cancellation_changes' => 'Sipariş değişikliği ve iptal',
            'shipping_info' => 'Kargo ve teslimat',
            'pickup_tracking' => 'Elden teslim ve kargo takibi',
            'shipping_damage' => 'Kargoda hasar',
            'payment_info' => 'Ödeme',
            'deposit_balance' => 'Kapora ve kalan ödeme',
            'cash_on_delivery' => 'Kapıda ödeme',
            'after_sales' => 'Değişim ve satış sonrası',
            'defect_policy' => 'Hatalı baskı ve ürün politikası',
            'custom_product_returns' => 'Kişiye özel ürünlerde iade',
            'complaint_period' => 'Sorun bildirme süresi',
            'frequent_questions' => 'Sık sorulan ek bilgiler',
            'forbidden_promises' => 'Asistanın vermemesi gereken sözler',
            'human_contact' => 'Yetkiliye aktarım',
            'assistant_tone' => 'Asistanın konuşma tarzı',
            'supported_languages' => 'Desteklenen diller',
            'order_closing_flow' => 'Siparişi tamamlama akışı',
            'customer_info_to_collect' => 'Toplanacak sipariş bilgileri',
            'unsupported_requests' => 'Hizmet verilmeyen talepler',
        ];

        $lines = ['Bu bölüm firma yetkilisi tarafından onaylanmıştır. Yanıt verirken öncelikle bu bilgileri kullan; belirtilmeyen fiyat veya koşulları uydurma.'];

        foreach ($labels as $key => $label) {
            $value = $data[$key] ?? null;
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            if (filled($value)) {
                $lines[] = $label.': '.trim((string) $value);
            }
        }

        return implode("\n", $lines);
    }
}
