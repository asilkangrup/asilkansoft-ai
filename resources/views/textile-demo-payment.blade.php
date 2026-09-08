<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#080b12">
    <title>Güvenli Demo Ödeme • WAI Textile</title>
    <style>
        :root{--bg:#080b12;--card:#111723;--line:#273246;--text:#f8fafc;--muted:#9aa8bb;--purple:#7c3aed;--cyan:#06b6d4;--green:#34d399}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;color:var(--text);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 8% 0,rgba(124,58,237,.25),transparent 35%),radial-gradient(circle at 100% 0,rgba(6,182,212,.18),transparent 30%),var(--bg);padding:24px}
        .wrap{width:min(560px,100%);margin:30px auto}.brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}.logo{width:50px;height:50px;border-radius:16px;display:grid;place-items:center;font-weight:950;background:linear-gradient(135deg,var(--purple),var(--cyan));box-shadow:0 16px 42px rgba(124,58,237,.28)}.brand b{display:block}.brand span{display:block;color:var(--muted);font-size:12px;margin-top:3px}
        .card{border:1px solid rgba(255,255,255,.08);border-radius:28px;background:linear-gradient(160deg,rgba(20,27,40,.98),rgba(12,17,27,.98));padding:28px;box-shadow:0 35px 100px rgba(0,0,0,.45)}.secure{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid rgba(52,211,153,.28);border-radius:999px;background:rgba(52,211,153,.08);color:#a7f3d0;font-size:11px;font-weight:900}.card h1{font-size:31px;line-height:1.08;letter-spacing:-.04em;margin:18px 0 9px}.lead{color:#b7c2d0;font-size:14px;line-height:1.65;margin:0 0 22px}
        .order{border:1px solid var(--line);border-radius:18px;background:#0b111b;padding:18px}.row{display:flex;justify-content:space-between;gap:16px;padding:9px 0;color:#bdc7d5;font-size:13px}.row b{color:#f8fafc;text-align:right}.row.total{border-top:1px solid var(--line);margin-top:7px;padding-top:17px;font-size:18px}.row.total b{color:#a7f3d0;font-size:24px}.fake{margin:18px 0;border:1px dashed #394860;border-radius:16px;padding:15px;color:#91a0b4;font-size:12px;line-height:1.55}.pay{width:100%;border:0;border-radius:15px;padding:15px 18px;color:white;font-weight:950;font-size:15px;cursor:pointer;background:linear-gradient(135deg,var(--purple),#0891b2);box-shadow:0 14px 38px rgba(124,58,237,.25)}.pay:hover{filter:brightness(1.08)}.success{border:1px solid rgba(52,211,153,.3);border-radius:18px;background:rgba(52,211,153,.09);padding:18px;color:#bbf7d0;line-height:1.55}.success b{display:block;font-size:18px;margin-bottom:5px}.foot{text-align:center;color:#66758a;font-size:11px;line-height:1.6;margin-top:16px}@media(max-width:600px){body{padding:14px}.wrap{margin:12px auto}.card{padding:22px 18px;border-radius:23px}.card h1{font-size:27px}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="brand"><div class="logo">W</div><div><b>WAI Textile Pay</b><span>İstanbul Tişört Baskı • Özel Demo</span></div></div>
    <section class="card">
        @if($paid || session('success'))
            <div class="success"><b>✓ Demo ödeme tamamlandı</b>Siparişiniz üretim planına aktarıldı. WhatsApp konuşmasına otomatik onay mesajı gönderildi.</div>
            <h1>Her şey hazır.</h1>
            <p class="lead">Gerçek kullanımda ödeme sağlayıcısının başarılı bildirimiyle sipariş, üretim görevi ve yönetici bildirimi aynı anda oluşturulur.</p>
        @else
            <span class="secure">🔒 GÜVENLİ DEMO ÖDEME</span>
            <h1>Siparişinizi tamamlayın</h1>
            <p class="lead">Tasarımınız onaylandı. Aşağıdaki işlem yalnızca çalışma akışını göstermek içindir; kart bilgisi istemez ve gerçek para çekmez.</p>
            <div class="order">
                <div class="row"><span>Sipariş</span><b>#{{ $orderId }}</b></div>
                <div class="row"><span>Ürün</span><b>{{ $order['product'] ?? 'Premium Oversize Tişört' }}</b></div>
                <div class="row"><span>Renk / Baskı</span><b>{{ $order['color_label'] ?? 'Siyah' }} • {{ $order['print_type'] ?? 'DTF Baskı' }}</b></div>
                <div class="row"><span>Adet</span><b>{{ number_format($quote['quantity'], 0, ',', '.') }}</b></div>
                <div class="row"><span>Birim fiyat</span><b>{{ number_format($quote['unit'], 0, ',', '.') }} TL</b></div>
                <div class="row total"><span>Demo toplam</span><b>{{ number_format($quote['total'], 0, ',', '.') }} TL</b></div>
            </div>
            <div class="fake">Demo modu aktif. Gerçek sistemde bu alan PayTR veya iyzico güvenli ödeme ekranı olarak açılır.</div>
            <form method="post" action="{{ request()->fullUrl() }}">
                @csrf
                <button class="pay" type="submit">Demo Ödemeyi Tamamla →</button>
            </form>
        @endif
    </section>
    <p class="foot">WAI tarafından uçtan uca otomatik oluşturulan sipariş deneyimi.</p>
</main>
</body>
</html>
