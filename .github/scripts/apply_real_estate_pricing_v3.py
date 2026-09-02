from pathlib import Path

def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit(f"{label}: expected block not found")
    return text.replace(old, new, 1)

# 1) Valuation service: customer-facing two-level pricing and deterministic
#    lower-band anchoring. Hidden active-listing market data stays available
#    for research quality and internal diagnostics.
p = Path("app/Services/RealEstateValuationService.php")
s = p.read_text()

old = """        $json = json_encode(
            $profile->valuation,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL FRESH REAL ESTATE VALUATION MEMORY]
Aşağıdaki değerleme bu taşınmazın güncel yapılandırılmış verisiyle eşleşen, süre kontrollü araştırma kaydıdır. Müşteriye dahili JSON'u veya freshness alanlarını gösterme. İlan/emsal fiyatlarının gerçekleşmiş satış fiyatı olmadığını açıkça ayır. Kaynak ve emsal kalitesi düşükse kesinlik dilini azalt. Yeni temel taşınmaz bilgisi gelirse bu değerlemeyi otomatik olarak eski kabul et.
Değerleme: {$json}
PROMPT;
"""
new = """        $customerValuation = $profile->valuation;

        // Only expose the commercial two-level pricing view to the chat model.
        // Active-listing market and raw quick-sale research bands remain internal
        // so they cannot accidentally become a third customer-facing price band.
        foreach ([
            'market_min', 'market_max',
            'quick_sale_min', 'quick_sale_max',
            'research_realistic_sale_min', 'research_realistic_sale_max',
            'market_gap_percent',
        ] as $internalField) {
            unset($customerValuation[$internalField]);
        }

        $json = json_encode(
            $customerValuation,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return <<<PROMPT
[INTERNAL FRESH REAL ESTATE VALUATION MEMORY]
Aşağıdaki değerleme bu taşınmazın güncel yapılandırılmış verisiyle eşleşen, süre kontrollü araştırma kaydıdır. Müşteriye dahili JSON'u veya freshness alanlarını gösterme. Fiyat sorularında yalnız iki müşteri seviyesi kullan: realistic_sale_min/max = gerçekçi satış bandı; investor_buy_min/max = yatırımcı / hızlı nakit alım seviyesi. Üçüncü bir \"normal piyasa satış bandı\" üretme. İlan/emsal fiyatlarının gerçekleşmiş satış fiyatı olmadığını açıkça ayır. Kaynak ve emsal kalitesi düşükse kesinlik dilini azalt. Yeni temel taşınmaz bilgisi gelirse bu değerlemeyi otomatik olarak eski kabul et.
Değerleme: {$json}
PROMPT;
"""
s = replace_once(s, old, new, "valuation prompt")

old = """        // Commercial guardrail: investor opportunity is anchored to the
        // researched realistic sale range, never directly to seller ask/listing price.
        $realisticMax = $result['realistic_sale_max'] ?? null;
        if (is_numeric($realisticMax) && (float) $realisticMax > 0) {
            $maxInvestorBuy = round((float) $realisticMax * 0.80, 2);
            if (! is_numeric($result['investor_buy_max'] ?? null) || (float) $result['investor_buy_max'] > $maxInvestorBuy) {
                $result['investor_buy_max'] = $maxInvestorBuy;
            }
            if (is_numeric($result['investor_buy_min'] ?? null) && (float) $result['investor_buy_min'] > (float) $result['investor_buy_max']) {
                $result['investor_buy_min'] = round((float) $result['investor_buy_max'] * 0.90, 2);
            }
        }

        return $result;
"""
new = """        // Preserve the research model's broader realistic-sale estimate for
        // diagnostics, but use the lower/faster executable band as the commercial
        // \"realistic sale\" shown to sellers.
        $result['research_realistic_sale_min'] = $result['realistic_sale_min'] ?? null;
        $result['research_realistic_sale_max'] = $result['realistic_sale_max'] ?? null;

        $quickMin = $result['quick_sale_min'] ?? null;
        $quickMax = $result['quick_sale_max'] ?? null;

        if (is_numeric($quickMin) && (float) $quickMin > 0) {
            $result['realistic_sale_min'] = (float) $quickMin;
        }
        if (is_numeric($quickMax) && (float) $quickMax > 0) {
            $result['realistic_sale_max'] = (float) $quickMax;
        }

        // Commercial guardrail: investor / fast-cash opportunity is anchored
        // to the customer-facing realistic sale range, never directly to seller
        // ask or active-listing market prices.
        $realisticMax = $result['realistic_sale_max'] ?? null;
        if (is_numeric($realisticMax) && (float) $realisticMax > 0) {
            $maxInvestorBuy = round((float) $realisticMax * 0.80, 2);

            if (
                ! is_numeric($result['investor_buy_max'] ?? null)
                || (float) $result['investor_buy_max'] > $maxInvestorBuy
            ) {
                $result['investor_buy_max'] = $maxInvestorBuy;
            }

            if (
                is_numeric($result['investor_buy_min'] ?? null)
                && (float) $result['investor_buy_min'] > (float) $result['investor_buy_max']
            ) {
                $result['investor_buy_min'] = round(
                    (float) $result['investor_buy_max'] * 0.90,
                    2
                );
            }
        }

        return $result;
"""
s = replace_once(s, old, new, "valuation normalize policy")
s = replace_once(
    s,
    "- quick_sale bandı, gerçekçi normal satıştan daha düşük/hızlı nakde dönüş bandıdır ve gerçekçi satış bandından mantıksız biçimde yüksek olamaz.\n",
    "- quick_sale bandı, araştırma sırasında alt/hızlı gerçekleşebilir satış bandıdır. Uygulama müşteriye gösterilecek gerçekçi satış bandını bu daha temkinli alt/hızlı band üzerinden deterministik olarak kurabilir.\n",
    "valuation research quick policy",
)
p.write_text(s)

# 2) Output guard.
p = Path("app/Services/RealEstateValuationResearchOutputGuardService.php")
s = p.read_text()
s = replace_once(
    s,
    """            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
""",
    """            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'realistic_sale_min' => $this->positiveNumber($valuation['realistic_sale_min'] ?? null),
            'realistic_sale_max' => $this->positiveNumber($valuation['realistic_sale_max'] ?? null),
            'research_realistic_sale_min' => $this->positiveNumber($valuation['research_realistic_sale_min'] ?? null),
            'research_realistic_sale_max' => $this->positiveNumber($valuation['research_realistic_sale_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
""",
    "output guard price fields",
)
s = replace_once(
    s,
    """        return $this->normalizeText((string) $value) === $this->normalizeText($expected)
            ? $expected
            : 'mismatch';
""",
    """        $actual = $this->normalizeText((string) $value);
        $expectedNormalized = $this->normalizeText($expected);

        if ($actual === '' || $expectedNormalized === '') {
            return null;
        }

        $matches = $actual === $expectedNormalized;

        // Research sources frequently return descriptive labels such as
        // \"konut imarlı arsa\" or \"satılık daire\". Accept the same core type
        // without weakening a genuine cross-category mismatch.
        if (! $matches && mb_strlen($expectedNormalized) >= 4) {
            $matches = str_contains($actual, $expectedNormalized);
        }

        return $matches ? $expected : 'mismatch';
""",
    "output guard property type",
)
p.write_text(s)

# 3) Comparable integrity.
p = Path("app/Services/RealEstateComparableIntegrityService.php")
s = p.read_text()
s = replace_once(
    s,
    """        return $comparableType !== ''
            && $profileType !== ''
            && $comparableType === $profileType;
""",
    """        if ($comparableType === '' || $profileType === '') {
            return false;
        }

        if ($comparableType === $profileType) {
            return true;
        }

        return mb_strlen($profileType) >= 4
            && str_contains($comparableType, $profileType);
""",
    "integrity property type",
)
s = replace_once(
    s,
    """            ['market_min', 'market_max'],
            ['quick_sale_min', 'quick_sale_max'],
            ['investor_buy_min', 'investor_buy_max'],
""",
    """            ['market_min', 'market_max'],
            ['realistic_sale_min', 'realistic_sale_max'],
            ['quick_sale_min', 'quick_sale_max'],
            ['investor_buy_min', 'investor_buy_max'],
""",
    "integrity price pairs",
)
s = replace_once(
    s,
    """        $marketMax = $this->positiveNumber($valuation['market_max'] ?? null);
        $quickMax = $this->positiveNumber($valuation['quick_sale_max'] ?? null);
        $investorMax = $this->positiveNumber($valuation['investor_buy_max'] ?? null);

        if ($marketMax !== null) {
""",
    """        $marketMax = $this->positiveNumber($valuation['market_max'] ?? null);
        $realisticMax = $this->positiveNumber($valuation['realistic_sale_max'] ?? null);
        $quickMax = $this->positiveNumber($valuation['quick_sale_max'] ?? null);
        $investorMax = $this->positiveNumber($valuation['investor_buy_max'] ?? null);

        if (
            $realisticMax !== null
            && $investorMax !== null
            && $investorMax > ($realisticMax * 0.8001)
        ) {
            return false;
        }

        if ($marketMax !== null) {
""",
    "integrity investor cap",
)
p.write_text(s)

# 4) Decision logic.
p = Path("app/Services/RealEstateDecisionService.php")
s = p.read_text()
for old, new, label in [
    ("$marketMax = $this->number($valuation['market_max'] ?? null);", "$realisticMax = $this->number($valuation['realistic_sale_max'] ?? null);", "decision realistic anchor"),
    ("'Aciliyeti fırsat bilerek baskı kurma. Önce değerleme verisini güçlendir, sonra hızlı satış seçeneğini piyasa aralığından ayrı ve açık biçimde anlat.'", "'Aciliyeti fırsat bilerek baskı kurma. Önce değerleme verisini güçlendir, sonra gerçekçi satış ile yatırımcı/hızlı nakit seviyesini açık biçimde ayır.'", "decision urgent wording"),
    ("if ($asking !== null && $marketMax !== null && $asking > ($marketMax * 1.12))", "if ($asking !== null && $realisticMax !== null && $asking > ($realisticMax * 1.12))", "decision high ask condition"),
    ("'Fiyat beklentisini tek bir ilana değil güncel emsal aralığına dayandırarak yeniden çerçevele; satıcının esnekliğini doğal biçimde ölç.'", "'Fiyat beklentisini aktif ilanların üst beklentisine değil araştırılmış gerçekçi satış bandına göre yeniden çerçevele; satıcının esnekliğini doğal biçimde ölç.'", "decision high ask wording"),
    ("'Satıcıya piyasa, hızlı satış ve yatırımcı alım aralığını şeffaf biçimde ayır. Yatırımcıya sunulabilir gerçek bir fırsat oluşturmak için fiyatın yatırımcı alım bandına yaklaşması gerektiğini emsal ve hız/fiyat dengesiyle anlat; makul bir karşı teklif aralığı öner ve pazarlık esnekliğini ölç. Aciliyet üzerinden baskı kurma, sahte alıcı/teklif kullanma.'", "'Satıcıya yalnız gerçekçi satış bandı ile yatırımcı/hızlı nakit alım seviyesini şeffaf biçimde ayır. Üst aktif ilan bandını müşteri-facing piyasa satış fiyatı gibi sunma. Yatırımcıya sunulabilir gerçek bir fırsat oluşturmak için fiyatın yatırımcı alım seviyesine yaklaşması gerektiğini emsal ve hız/fiyat dengesiyle anlat; makul bir karşı teklif öner ve pazarlık esnekliğini ölç. Aciliyet üzerinden baskı kurma, sahte alıcı/teklif kullanma.'", "decision investor wording"),
    ("'Piyasa, hızlı satış ve yatırımcı alım aralıklarını birbirinden ayır; satıcıya hangi hız/fiyat dengesini tercih ettiğini netleştir.'", "'Müşteriye gerçekçi satış bandı ile yatırımcı/hızlı nakit alım seviyesini ayır; üçüncü bir piyasa bandı üretme ve hız/fiyat dengesini net biçimde anlat.'", "decision protect wording"),
]:
    s = replace_once(s, old, new, label)
s = replace_once(
    s,
    """        return $this->number($valuation['market_min'] ?? null) !== null
            || $this->number($valuation['market_max'] ?? null) !== null;
""",
    """        return $this->number($valuation['realistic_sale_min'] ?? null) !== null
            || $this->number($valuation['realistic_sale_max'] ?? null) !== null
            || $this->number($valuation['investor_buy_min'] ?? null) !== null
            || $this->number($valuation['investor_buy_max'] ?? null) !== null
            || $this->number($valuation['quick_sale_min'] ?? null) !== null
            || $this->number($valuation['quick_sale_max'] ?? null) !== null
            || $this->number($valuation['market_min'] ?? null) !== null
            || $this->number($valuation['market_max'] ?? null) !== null;
""",
    "decision has valuation",
)
p.write_text(s)

# 5) Main chat contract.
p = Path("app/Services/RealEstateOpenAIService.php")
s = p.read_text()
s = replace_once(
    s,
    """Güncel ve güvenli değerleme varsa mümkün olduğunda şu mantığı kullan:
1. Tahmini piyasa satış aralığı.
2. Makul hızlı satış aralığı.
3. Yatırımcının ilgisini çekebilecek hedef alım aralığı.
4. İstenen fiyat ile piyasa arasındaki fark.
5. Güven skoru: 0-100.
6. Güven skorunu yükseltecek eksik veriler.
7. En mantıklı sonraki pazarlık/teklif aksiyonu.

Ancak bu başlıkların hepsini her mesajda müşteriye dökme. Kullanıcı sadece 'kaça alınır?' diyorsa sonucu kısa ve net ver; detay isterse gerekçeyi aç.
""",
    """Güncel ve güvenli değerleme varsa fiyatı müşteriye sade biçimde yalnız iki seviyede sun:
1. Gerçekçi satış bandı: dahili realistic_sale_min / realistic_sale_max. Bu, ilanların üst beklenti bandı değil, daha gerçekçi ve daha çabuk gerçekleşebilir satış seviyesidir.
2. Yatırımcı / hızlı nakit alım seviyesi: dahili investor_buy_min / investor_buy_max. Bu seviye gerçekçi satıştan ayrıca iskonto içerir ve yatırımcıya marj/risk payı bırakır.

Dahili market_min / market_max alanlarını müşteriye \"normal piyasa satış bandı\" adıyla ASLA gösterme. Bunlar yalnız emsal araştırması ve veri kalite kontrolünde kullanılan aktif ilan/istenen fiyat referanslarıdır.
Kullanıcı fiyat soruyorsa ana cevap gerçekçi satış + yatırımcı/hızlı alım seviyesidir. Yatırımcı bandını gerektiğinde \"yaklaşık 3,0 M civarı\" gibi doğal ve yuvarlatılmış biçimde özetle; sahte kesinlik verme.
Güven skorunu ve eksik verileri yalnız gerçekten yararlıysa kısa belirt.

Kullanıcı sadece 'kaça alınır?' diyorsa sonucu kısa ve net ver; detay isterse gerekçeyi aç.
""",
    "chat valuation contract",
)
s = replace_once(
    s,
    "- Satıcı fiyatı yatırımcı alım bandının üzerindeyse pasif kalma: güncel emsal, hızlı satış ve yatırımcı alım aralığını ayrı ayrı kullanarak fiyat beklentisini profesyonelce aşağı yönlü yeniden çerçevele. Hedef, yatırımcıya gerçekten cazip ve işlem yapılabilir bir fiyat seviyesine yaklaşmaktır.",
    "- Satıcı fiyatı yatırımcı alım bandının üzerindeyse pasif kalma: müşteriye yalnız gerçekçi satış bandı ile yatırımcı/hızlı nakit alım seviyesini kullanarak fiyat beklentisini profesyonelce aşağı yönlü yeniden çerçevele. \"Normal piyasa satış bandı\" diye üçüncü bir üst bant gösterme. Hedef, yatırımcıya gerçekten cazip ve işlem yapılabilir bir fiyat seviyesine yaklaşmaktır.",
    "chat negotiation contract",
)
s = replace_once(
    s,
    "- Fiyat indirimi için satıcının aciliyetini sömürme. 'Yatırımcı bu seviyede marj/risk görmüyor', 'hızlı satış için şu banda yaklaşmak gerekir' gibi veriye dayalı hız-fiyat dengesi anlat; makul karşı teklif aralığı öner ve esnekliği sor.",
    "- Fiyat indirimi için satıcının aciliyetini sömürme. 'Gerçekçi satış seviyesi bu banda yakın', 'yatırımcı hızlı nakit alımda marj/risk nedeniyle şu seviyeye yaklaşır' gibi veriye dayalı hız-fiyat dengesi anlat; makul karşı teklif aralığı öner ve esnekliği sor.",
    "chat discount wording",
)
p.write_text(s)

# 6) Freshness/lifecycle.
for path, label in [
    ("app/Services/RealEstateValuationFreshnessService.php", "freshness pricing"),
    ("app/Services/RealEstateCaseLifecycleService.php", "case lifecycle pricing"),
]:
    p = Path(path)
    s = p.read_text()
    s = replace_once(
        s,
        """            'market_min',
            'market_max',
            'quick_sale_min',
""",
        """            'market_min',
            'market_max',
            'realistic_sale_min',
            'realistic_sale_max',
            'quick_sale_min',
""",
        label,
    )
    p.write_text(s)

# 7) Decision guard wording.
p = Path("app/Services/RealEstateValuationDecisionGuardService.php")
s = p.read_text()
s = replace_once(
    s,
    "Eski veya kaynaksız fiyat verisini pazarlık ankrajı yapma. Güncel emsal araştırmasını yenile, ardından piyasa / hızlı satış / yatırımcı alım aralıklarını yeniden karşılaştır.",
    "Eski veya kaynaksız fiyat verisini pazarlık ankrajı yapma. Güncel emsal araştırmasını yenile, ardından gerçekçi satış ile yatırımcı/hızlı nakit alım seviyesini yeniden karşılaştır.",
    "valuation decision guard wording",
)
p.write_text(s)

# 8) Research ledger.
p = Path("app/Services/RealEstateValuationResearchLedgerService.php")
s = p.read_text()
s = replace_once(
    s,
    """            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
""",
    """            'market_min' => $this->positiveNumber($valuation['market_min'] ?? null),
            'market_max' => $this->positiveNumber($valuation['market_max'] ?? null),
            'realistic_sale_min' => $this->positiveNumber($valuation['realistic_sale_min'] ?? null),
            'realistic_sale_max' => $this->positiveNumber($valuation['realistic_sale_max'] ?? null),
            'quick_sale_min' => $this->positiveNumber($valuation['quick_sale_min'] ?? null),
""",
    "ledger fingerprint",
)
s = replace_once(
    s,
    """                'market_min' => $fingerprintPayload['market_min'],
                'market_max' => $fingerprintPayload['market_max'],
                'quick_sale_min' => $fingerprintPayload['quick_sale_min'],
""",
    """                'market_min' => $fingerprintPayload['market_min'],
                'market_max' => $fingerprintPayload['market_max'],
                'realistic_sale_min' => $fingerprintPayload['realistic_sale_min'],
                'realistic_sale_max' => $fingerprintPayload['realistic_sale_max'],
                'quick_sale_min' => $fingerprintPayload['quick_sale_min'],
""",
    "ledger insert",
)
p.write_text(s)

# 9) Ledger model + schema.
p = Path("app/Models/RealEstateValuationResearchEvent.php")
s = p.read_text()
s = replace_once(
    s,
    """        'market_min',
        'market_max',
        'quick_sale_min',
""",
    """        'market_min',
        'market_max',
        'realistic_sale_min',
        'realistic_sale_max',
        'quick_sale_min',
""",
    "ledger model fillable",
)
s = replace_once(
    s,
    """        'market_min' => 'decimal:2',
        'market_max' => 'decimal:2',
        'quick_sale_min' => 'decimal:2',
""",
    """        'market_min' => 'decimal:2',
        'market_max' => 'decimal:2',
        'realistic_sale_min' => 'decimal:2',
        'realistic_sale_max' => 'decimal:2',
        'quick_sale_min' => 'decimal:2',
""",
    "ledger model casts",
)
p.write_text(s)

Path("database/migrations/2026_09_02_145900_add_realistic_sale_to_real_estate_valuation_research_events_table.php").write_text(r"""<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('real_estate_valuation_research_events', function (Blueprint $table): void {
            $table->decimal('realistic_sale_min', 16, 2)->nullable()->after('market_max');
            $table->decimal('realistic_sale_max', 16, 2)->nullable()->after('realistic_sale_min');
        });
    }

    public function down(): void
    {
        Schema::table('real_estate_valuation_research_events', function (Blueprint $table): void {
            $table->dropColumn(['realistic_sale_min', 'realistic_sale_max']);
        });
    }
};
""")

# 10) Regression tests.
Path("tests/Feature/RealEstateCustomerPricingPolicyTest.php").write_text(r"""<?php

namespace Tests\Feature;

use App\Services\RealEstateValuationResearchOutputGuardService;
use App\Services\RealEstateValuationService;
use ReflectionClass;
use Tests\TestCase;

class RealEstateCustomerPricingPolicyTest extends TestCase
{
    public function test_lower_executable_band_becomes_realistic_sale_and_investor_is_capped(): void
    {
        $service = app(RealEstateValuationService::class);
        $method = (new ReflectionClass($service))->getMethod('normalize');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            'market_min' => 4_200_000,
            'market_max' => 5_250_000,
            'realistic_sale_min' => 3_950_000,
            'realistic_sale_max' => 4_650_000,
            'quick_sale_min' => 3_450_000,
            'quick_sale_max' => 3_950_000,
            'investor_buy_min' => 3_000_000,
            'investor_buy_max' => 3_720_000,
            'confidence_score' => 70,
            'sources' => ['https://example.test/listing'],
            'comparables' => [],
        ]);

        $this->assertSame(3_450_000.0, $result['realistic_sale_min']);
        $this->assertSame(3_950_000.0, $result['realistic_sale_max']);
        $this->assertSame(3_000_000.0, $result['investor_buy_min']);
        $this->assertSame(3_160_000.0, $result['investor_buy_max']);
        $this->assertLessThanOrEqual(
            $result['realistic_sale_max'] * 0.80,
            $result['investor_buy_max']
        );
    }

    public function test_output_guard_preserves_realistic_sale_fields(): void
    {
        $guard = app(RealEstateValuationResearchOutputGuardService::class);

        $result = $guard->buildSanitizedValuation([
            'market_min' => 4_200_000,
            'market_max' => 5_250_000,
            'realistic_sale_min' => 3_450_000,
            'realistic_sale_max' => 3_950_000,
            'quick_sale_min' => 3_450_000,
            'quick_sale_max' => 3_950_000,
            'investor_buy_min' => 3_000_000,
            'investor_buy_max' => 3_160_000,
            'confidence_score' => 70,
            'comparables' => [],
            'sources' => [],
        ], [
            'property_type' => 'arsa',
            'city' => 'İstanbul',
            'district' => 'Silivri',
        ]);

        $this->assertSame(3_450_000.0, $result['realistic_sale_min']);
        $this->assertSame(3_950_000.0, $result['realistic_sale_max']);
    }

    public function test_descriptive_comparable_property_type_keeps_same_core_type(): void
    {
        $guard = app(RealEstateValuationResearchOutputGuardService::class);

        $result = $guard->buildSanitizedValuation([
            'comparables' => [[
                'url' => 'https://example.test/listing',
                'listing_price' => 3_500_000,
                'area_sqm' => 400,
                'location' => 'İstanbul Silivri Büyükçavuşlu',
                'property_type' => 'Konut imarlı arsa',
                'observed_at' => now()->toDateString(),
            ]],
            'sources' => ['https://example.test/listing'],
        ], [
            'property_type' => 'arsa',
            'city' => 'İstanbul',
            'district' => 'Silivri',
            'neighborhood' => 'Büyükçavuşlu',
            'area_sqm' => 400,
        ]);

        $this->assertSame('arsa', $result['comparables'][0]['property_type']);
    }

    public function test_customer_chat_contract_has_only_two_price_levels(): void
    {
        $valuationSource = file_get_contents(app_path('Services/RealEstateValuationService.php'));
        $chatSource = file_get_contents(app_path('Services/RealEstateOpenAIService.php'));

        $this->assertStringContainsString(
            'unset($customerValuation[$internalField])',
            $valuationSource
        );
        $this->assertStringContainsString(
            'normal piyasa satış bandı',
            mb_strtolower($chatSource)
        );
        $this->assertStringContainsString('ASLA gösterme', $chatSource);
        $this->assertStringContainsString('realistic_sale_min', $chatSource);
        $this->assertStringContainsString('investor_buy_min', $chatSource);
    }
}
""")

# Temporary helper files should disappear from the product commit.
Path(".github/workflows/apply-real-estate-pricing-v3.yml").unlink(missing_ok=True)
Path(".github/scripts/apply_real_estate_pricing_v3.py").unlink(missing_ok=True)
