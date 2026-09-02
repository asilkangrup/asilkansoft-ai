from pathlib import Path
import runpy

p = Path('app/Services/RealEstateOpenAIService.php')
s = p.read_text()

current = '''Güncel ve güvenli değerleme varsa müşteriye fiyatı sade biçimde iki seviyede sun:
1. Gerçekçi satış bandı: dahili realistic_sale_min / realistic_sale_max. Bu, ilanların üst beklenti bandı değil, daha gerçekçi ve daha çabuk gerçekleşebilir satış seviyesidir.
2. Yatırımcı / hızlı nakit alım seviyesi: dahili investor_buy_min / investor_buy_max. Bu seviye gerçekçi satıştan ayrıca iskonto içerir ve yatırımcı marj/risk payı bırakır.

Dahili market_min / market_max alanlarını müşteriye "normal piyasa satış bandı" adıyla ASLA gösterme. Bunlar yalnız emsal araştırması ve veri kalite kontrolünde kullanılan aktif ilan/istenen fiyat referanslarıdır. Kullanıcı fiyat soruyorsa ana cevap quick_sale_min / quick_sale_max değerlerini "Gerçekçi satış bandı" adıyla sunmak ve yatırımcı/hızlı nakit alım seviyesini ayrıca belirtmektir.
- "Gerçekçi satış bandı" için quick_sale_min / quick_sale_max kullan.
- "Yatırımcı / hızlı nakit alım" için investor_buy bandının alt tarafını esas al; satıcıya investor_buy_max değerini hedef satış fiyatı gibi öne çıkarma. Uygunsa tek yuvarlak hedef olarak investor_buy_min veya alt-orta seviyeyi "yaklaşık 3,0 M civarı" gibi doğal biçimde söyle.
- Üç ayrı fiyat bandı çıkarma; müşteriye yalnız bu iki seviye yeterlidir.
Güven skorunu ve eksik verileri yalnız gerçekten yararlıysa kısa belirt.

Ancak bu başlıkların hepsini her mesajda müşteriye dökme. Kullanıcı sadece 'kaça alınır?' diyorsa sonucu kısa ve net ver; detay isterse gerekçeyi aç.
'''
original = '''Güncel ve güvenli değerleme varsa mümkün olduğunda şu mantığı kullan:
1. Tahmini piyasa satış aralığı.
2. Makul hızlı satış aralığı.
3. Yatırımcının ilgisini çekebilecek hedef alım aralığı.
4. İstenen fiyat ile piyasa arasındaki fark.
5. Güven skoru: 0-100.
6. Güven skorunu yükseltecek eksik veriler.
7. En mantıklı sonraki pazarlık/teklif aksiyonu.

Ancak bu başlıkların hepsini her mesajda müşteriye dökme. Kullanıcı sadece 'kaça alınır?' diyorsa sonucu kısa ve net ver; detay isterse gerekçeyi aç.
'''
if current not in s:
    raise SystemExit('compat valuation block not found')
s = s.replace(current, original, 1)
s = s.replace(
    '- Satıcı fiyatı yatırımcı alım bandının üzerindeyse pasif kalma: müşteriye yalnız gerçekçi satış bandı ile yatırımcı/hızlı nakit alım seviyesini kullanarak fiyat beklentisini profesyonelce aşağı yönlü yeniden çerçevele. "Normal piyasa satış bandı" diye üçüncü bir üst bant gösterme.',
    '- Satıcı fiyatı yatırımcı alım bandının üzerindeyse pasif kalma: güncel emsal, hızlı satış ve yatırımcı alım aralığını ayrı ayrı kullanarak fiyat beklentisini profesyonelce aşağı yönlü yeniden çerçevele. Hedef, yatırımcıya gerçekten cazip ve işlem yapılabilir bir fiyat seviyesine yaklaşmaktır.',
    1,
)
s = s.replace(
    "- Fiyat indirimi için satıcının aciliyetini sömürme. 'Gerçekçi satış seviyesi bu banda yakın', 'yatırımcı hızlı nakit alımda marj/risk payı nedeniyle şu seviyeye yaklaşır' gibi veriye dayalı hız-fiyat dengesi anlat; makul karşı teklif aralığı öner ve esnekliği sor.",
    "- Fiyat indirimi için satıcının aciliyetini sömürme. 'Yatırımcı bu seviyede marj/risk görmüyor', 'hızlı satış için şu banda yaklaşmak gerekir' gibi veriye dayalı hız-fiyat dengesi anlat; makul karşı teklif aralığı öner ve esnekliği sor.",
    1,
)
p.write_text(s)

runpy.run_path('.github/scripts/apply_real_estate_pricing_v3.py', run_name='__main__')
Path('.github/scripts/run_pricing_v3_compat.py').unlink(missing_ok=True)
