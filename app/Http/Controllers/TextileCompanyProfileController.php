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
            'fabric_quality' => ['required', 'string', 'max:1000'],
            'minimum_order' => ['required', 'string', 'max:1000'],
            'print_methods' => ['required', 'array', 'min:1'],
            'print_methods.*' => ['string', 'max:80'],
            'print_limits' => ['required', 'string', 'max:1000'],
            'print_recommendation' => ['nullable', 'string', 'max:1000'],
            'print_positions' => ['nullable', 'string', 'max:750'],
            'artwork_formats' => ['nullable', 'string', 'max:750'],
            'artwork_quality' => ['nullable', 'string', 'max:750'],
            'design_service' => ['nullable', 'string', 'max:1000'],
            'sample_price' => ['required', 'string', 'max:1000'],
            'bulk_pricing' => ['required', 'string', 'max:1500'],
            'quote_requirements' => ['required', 'string', 'max:1000'],
            'price_factors' => ['nullable', 'string', 'max:1000'],
            'vat_invoice' => ['nullable', 'string', 'max:750'],
            'production_time' => ['nullable', 'string', 'max:500'],
            'rush_order' => ['nullable', 'string', 'max:750'],
            'approval_process' => ['nullable', 'string', 'max:1000'],
            'shipping_info' => ['nullable', 'string', 'max:750'],
            'payment_info' => ['nullable', 'string', 'max:750'],
            'after_sales' => ['nullable', 'string', 'max:750'],
            'defect_policy' => ['nullable', 'string', 'max:750'],
            'frequent_questions' => ['nullable', 'string', 'max:1500'],
            'forbidden_promises' => ['nullable', 'string', 'max:1000'],
            'human_contact' => ['nullable', 'string', 'max:500'],
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
            'fabric_quality' => 'Standart tişört kalitemiz 30/1 süprem penye, %100 pamuklu kumaştır; metrekarede ortalama 150–160 gram gelir.',
            'minimum_order' => 'Tişört firmamızdan alınırsa minimum adet yoktur; 1 adet sipariş verilebilir. Ürünleri müşteri temin edecekse minimum 30 adet olmalıdır.',
            'print_methods' => ['Dijital baskı', 'Transfer baskı'],
            'print_limits' => 'Dijital baskı maksimum 40 cm uzunluk ve 35 cm genişlik olabilir. Transfer baskıda standart maksimum genişlik 29 cm’dir; üzerindeki ölçülerde fiyat farkı çıkar.',
            'print_recommendation' => '',
            'print_positions' => '',
            'artwork_formats' => 'JPG, PNG ve WEBP görseller kabul edilir.',
            'artwork_quality' => '',
            'design_service' => '',
            'sample_price' => '1 adet numune: %100 pamuklu tişört + transfer veya dijital baskı + kargo dahil 750 TL.',
            'bulk_pricing' => '5–30 adet beyaz tişörtte dijital baskı: yalnız ön logo baskılı 300 TL/adet; ön ve arka logo baskılı 385 TL/adet.',
            'quote_requirements' => 'Toptan fiyat için tişört rengi, baskı görseli, baskı konumu ve ölçüsü, tişört adedi ve varsa hazır tasarım istenir.',
            'price_factors' => '',
            'vat_invoice' => '',
            'production_time' => '',
            'rush_order' => '',
            'approval_process' => 'Üretimden önce baskı ön izlemesi müşteriye gönderilir ve müşteri onayı alınır.',
            'shipping_info' => '',
            'payment_info' => '',
            'after_sales' => '',
            'defect_policy' => '',
            'frequent_questions' => '',
            'forbidden_promises' => 'Asistan, firma tarafından açıkça belirtilmeyen fiyat, stok, teslim tarihi veya indirim sözü vermemelidir.',
            'human_contact' => '',
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
            'fabric_quality' => 'Kumaş kalitesi',
            'minimum_order' => 'Minimum sipariş kuralları',
            'print_methods' => 'Baskı yöntemleri',
            'print_limits' => 'Baskı ölçü sınırları',
            'print_recommendation' => 'Baskı tekniği önerme kuralları',
            'print_positions' => 'Uygulanabilen baskı konumları',
            'artwork_formats' => 'Kabul edilen dosya türleri',
            'artwork_quality' => 'Görsel kalite ve çözünürlük kuralları',
            'design_service' => 'Tasarım desteği',
            'sample_price' => 'Numune fiyatı',
            'bulk_pricing' => 'Bilinen fiyatlar',
            'quote_requirements' => 'Teklif için gerekli bilgiler',
            'price_factors' => 'Fiyatı değiştiren unsurlar',
            'vat_invoice' => 'KDV ve fatura bilgisi',
            'production_time' => 'Üretim süresi',
            'rush_order' => 'Acil sipariş kuralları',
            'approval_process' => 'Ön izleme ve müşteri onayı',
            'shipping_info' => 'Kargo ve teslimat',
            'payment_info' => 'Ödeme',
            'after_sales' => 'Değişim ve satış sonrası',
            'defect_policy' => 'Hatalı baskı ve ürün politikası',
            'frequent_questions' => 'Sık sorulan ek bilgiler',
            'forbidden_promises' => 'Asistanın vermemesi gereken sözler',
            'human_contact' => 'Yetkiliye aktarım',
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
