<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>İstanbul Tişört Baskı • Firma Bilgileri</title>
    <style>
        :root{--ink:#17211d;--muted:#68736e;--brand:#18a965;--brand2:#087747;--paper:#fff;--bg:#f2f7f4;--line:#dfe8e2;--soft:#e9f8f0}
        *{box-sizing:border-box} body{margin:0;color:var(--ink);font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 100% 0,#dff7e9,transparent 34%),var(--bg);min-height:100vh}
        .shell{width:min(680px,100%);margin:auto;padding:20px 16px calc(30px + env(safe-area-inset-bottom))}
        .brand{display:flex;align-items:center;gap:11px;margin:4px 2px 22px}.mark{width:42px;height:42px;border-radius:14px;background:linear-gradient(145deg,var(--brand),var(--brand2));display:grid;place-items:center;color:#fff;font-weight:900}.brand b{display:block}.brand small{color:var(--muted)}
        .top{margin-bottom:14px}.eyebrow{font-size:12px;color:var(--brand2);font-weight:800;letter-spacing:.08em;text-transform:uppercase}.top h1{font-size:clamp(27px,8vw,40px);line-height:1.08;margin:8px 0}.top p{color:var(--muted);line-height:1.5;margin:0}
        .progress-line{height:8px;background:#dce7e0;border-radius:99px;overflow:hidden;margin:20px 0 8px}.progress-line i{display:block;height:100%;width:12.5%;background:linear-gradient(90deg,var(--brand),#58d18d);transition:.3s}.progress-meta{display:flex;justify-content:space-between;color:var(--muted);font-size:12px;margin-bottom:14px}
        .card{display:none;background:rgba(255,255,255,.96);border:1px solid rgba(213,228,219,.9);border-radius:24px;padding:24px 20px;box-shadow:0 20px 55px rgba(29,68,48,.10)}.card.active{display:block;animation:in .25s ease}@keyframes in{from{opacity:0;transform:translateY(7px)}}
        .step-tag{color:var(--brand2);font-size:13px;font-weight:800}.card h2{font-size:24px;margin:8px 0 6px}.help{color:var(--muted);font-size:14px;line-height:1.45;margin:0 0 18px}
        label{display:block;font-size:13px;font-weight:750;margin:15px 0 7px}input,textarea{width:100%;border:1.5px solid var(--line);border-radius:14px;padding:13px 14px;font:inherit;color:var(--ink);background:#fbfdfc;outline:none;transition:.2s}textarea{min-height:95px;resize:vertical}input:focus,textarea:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(24,169,101,.10)}
        .choices{display:grid;grid-template-columns:1fr 1fr;gap:9px}.choice{position:relative}.choice input{position:absolute;opacity:0}.choice span{display:block;border:1.5px solid var(--line);border-radius:14px;padding:13px;background:#fff;font-weight:650}.choice input:checked+span{border-color:var(--brand);background:var(--soft);color:var(--brand2)}
        .optional{font-weight:500;color:var(--muted)}.nav{display:flex;gap:10px;margin-top:22px}.btn{border:0;border-radius:15px;padding:14px 18px;font:inherit;font-weight:800;cursor:pointer}.back{background:#edf2ef;color:#52605a}.next,.submit{margin-left:auto;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;min-width:135px}.skip{background:transparent;color:var(--muted);padding-inline:6px}.success{background:#e7f8ef;border:1px solid #a8e1c0;color:#086b3d;padding:16px;border-radius:16px;margin-bottom:16px;font-weight:700}
        .confirm{display:flex;gap:11px;padding:13px;border-radius:15px;background:var(--soft);margin-top:16px}.confirm input{width:20px;height:20px;flex:none;margin:1px 0}.confirm label{margin:0;font-weight:650;line-height:1.4}
        .done-icon{width:64px;height:64px;border-radius:22px;background:var(--soft);display:grid;place-items:center;font-size:30px}.review{padding:0;margin:15px 0;list-style:none}.review li{padding:11px 0;border-bottom:1px solid var(--line)}.review b{display:block;font-size:12px;color:var(--muted);margin-bottom:3px}
        .saved{font-size:12px;color:var(--muted);text-align:center;margin-top:12px}@media(max-width:480px){.shell{padding-top:12px}.card{padding:21px 16px;border-radius:21px}.choices{grid-template-columns:1fr}.nav{position:sticky;bottom:8px;background:rgba(255,255,255,.92);padding:7px;border-radius:18px;backdrop-filter:blur(10px)}}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand"><div class="mark">İT</div><div><b>İstanbul Tişört Baskı</b><small>Yapay zekâ bilgi ayarları</small></div></div>
    <section class="top"><span class="eyebrow">Yaklaşık 4 dakika</span><h1>Asistanınız sizi doğru anlatsın.</h1><p>Bildiklerimizi doldurduk. Yalnızca kontrol edin, eksik olanları ekleyin; emin olmadığınız soruyu geçebilirsiniz.</p></section>
    @if(session('success'))<div class="success">✓ {{ session('success') }} Artık WhatsApp yanıtlarında bu bilgiler kullanılacak.</div>@endif
    <div class="progress-line"><i id="bar"></i></div><div class="progress-meta"><span id="stepText">1 / 8</span><span id="percent">12% tamamlandı</span></div>

    <form method="post" action="{{ route('textile.company-profile.store', ['token' => $token]) }}" id="wizard">
        @csrf
        <section class="card active" data-title="Firma">
            <span class="step-tag">Önce sizi tanıyalım</span><h2>Firmanız nasıl anlatılsın?</h2><p class="help">Müşteri “siz kimsiniz?” dediğinde asistan bu bilgileri kullanacak.</p>
            <label>Firma adı</label><input name="company_name" required value="{{ old('company_name',$values['company_name']) }}">
            <label>Telefon</label><input name="company_phone" inputmode="tel" value="{{ old('company_phone',$values['company_phone']) }}">
            <label>Kısa tanıtım</label><textarea name="business_summary">{{ old('business_summary',$values['business_summary']) }}</textarea>
        </section>

        <section class="card" data-title="Görüşme">
            <span class="step-tag">Müşteri iletişimi</span><h2>Nasıl çalışıyorsunuz?</h2><p class="help">Dükkâna gelmek isteyenlere verilecek yanıtı netleştirir.</p>
            <label>Online ve yüz yüze görüşme düzeni</label><textarea name="visit_policy" required>{{ old('visit_policy',$values['visit_policy']) }}</textarea>
            <label>Çalışma saatleri <span class="optional">— isteğe bağlı</span></label><input name="working_hours" placeholder="Örn. Hafta içi 09.00–18.00" value="{{ old('working_hours',$values['working_hours']) }}">
        </section>

        <section class="card" data-title="Ürün">
            <span class="step-tag">Ürünler</span><h2>Tişört özellikleri doğru mu?</h2><p class="help">Renk, kalıp, kumaş ve minimum adet bilgilerini kontrol edin.</p>
            <label>Ürün, kalıp ve renkler</label><textarea name="products_and_colors" required>{{ old('products_and_colors',$values['products_and_colors']) }}</textarea>
            <label>Kumaş kalitesi</label><textarea name="fabric_quality" required>{{ old('fabric_quality',$values['fabric_quality']) }}</textarea>
            <label>Minimum sipariş kuralları</label><textarea name="minimum_order" required>{{ old('minimum_order',$values['minimum_order']) }}</textarea>
        </section>

        <section class="card" data-title="Baskı">
            <span class="step-tag">Baskı</span><h2>Hangi baskıları yapıyorsunuz?</h2><p class="help">Uygulanan yöntemleri seçip ölçü sınırını kontrol edin.</p>
            <div class="choices">
                @foreach(['Dijital baskı','Transfer baskı','DTF','Serigrafi','Nakış','Süblimasyon'] as $method)
                    <label class="choice"><input type="checkbox" name="print_methods[]" value="{{ $method }}" @checked(in_array($method,old('print_methods',$values['print_methods'])) )><span>{{ $method }}</span></label>
                @endforeach
            </div>
            <label>Baskı ölçüsü ve sınırları</label><textarea name="print_limits" required>{{ old('print_limits',$values['print_limits']) }}</textarea>
            <label>Tasarım desteği <span class="optional">— isteğe bağlı</span></label><textarea name="design_service" placeholder="Logosu olmayan müşteriye tasarım yapıyor musunuz?">{{ old('design_service',$values['design_service']) }}</textarea>
        </section>

        <section class="card" data-title="Fiyat">
            <span class="step-tag">Fiyatlandırma</span><h2>Bilinen fiyatlar doğru mu?</h2><p class="help">Asistan yalnızca burada onayladığınız fiyatları söyleyecek.</p>
            <label>Numune fiyatı</label><textarea name="sample_price" required>{{ old('sample_price',$values['sample_price']) }}</textarea>
            <label>Bilinen adetli fiyatlar</label><textarea name="bulk_pricing" required>{{ old('bulk_pricing',$values['bulk_pricing']) }}</textarea>
            <label>Teklif vermek için gerekenler</label><textarea name="quote_requirements" required>{{ old('quote_requirements',$values['quote_requirements']) }}</textarea>
        </section>

        <section class="card optional-step" data-title="Teslimat">
            <span class="step-tag">Operasyon</span><h2>Sipariş nasıl tamamlanıyor?</h2><p class="help">Bilmiyorsanız bu adımı geçebilirsiniz.</p>
            <label>Ortalama üretim süresi <span class="optional">— isteğe bağlı</span></label><input name="production_time" placeholder="Örn. Onaydan sonra 5–7 iş günü" value="{{ old('production_time',$values['production_time']) }}">
            <label>Kargo ve teslimat <span class="optional">— isteğe bağlı</span></label><textarea name="shipping_info" placeholder="Kargo firması, ücret, teslim şekli…">{{ old('shipping_info',$values['shipping_info']) }}</textarea>
            <label>Ödeme seçenekleri <span class="optional">— isteğe bağlı</span></label><textarea name="payment_info" placeholder="Havale, kart, kapora oranı…">{{ old('payment_info',$values['payment_info']) }}</textarea>
        </section>

        <section class="card optional-step" data-title="Destek">
            <span class="step-tag">Satış sonrası</span><h2>Ne zaman size aktarsın?</h2><p class="help">Bu bölüm isteğe bağlıdır; asistanın sınırını belirler.</p>
            <label>Değişim ve satış sonrası <span class="optional">— isteğe bağlı</span></label><textarea name="after_sales" placeholder="Hatalı ürün, değişim ve iade yaklaşımınız…">{{ old('after_sales',$values['after_sales']) }}</textarea>
            <label>Canlı yetkiliye aktarım <span class="optional">— isteğe bağlı</span></label><textarea name="human_contact" placeholder="Hangi durumda ve hangi numaraya yönlendirsin?">{{ old('human_contact',$values['human_contact']) }}</textarea>
        </section>

        <section class="card" data-title="Onay">
            <div class="done-icon">✓</div><span class="step-tag">Son kontrol</span><h2>Hazırız.</h2><p class="help">Kaydettiğiniz anda bilgiler WhatsApp asistanının yanıtlarına eklenir. Daha sonra aynı bağlantıdan değiştirebilirsiniz.</p>
            <ul class="review" id="review"></ul>
            <div class="confirm"><input type="checkbox" name="confirmed" id="confirmed" required value="1"><label for="confirmed">Bilgileri kontrol ettim; yapay zekâ bu bilgilerle müşterilere yanıt verebilir.</label></div>
        </section>

        <div class="nav"><button class="btn back" type="button" id="back" hidden>Geri</button><button class="btn skip" type="button" id="skip" hidden>Şimdilik geç</button><button class="btn next" type="button" id="next">Devam</button><button class="btn submit" type="submit" id="submit" hidden>Kaydet ve Öğret</button></div>
        <div class="saved" id="saved">Cevaplarınız bu telefonda taslak olarak korunur.</div>
    </form>
</main>
<script>
(() => {
 const form=document.querySelector('#wizard'), cards=[...document.querySelectorAll('.card')], key='textile-profile-{{ $token }}'; let index=0;
 const bar=document.querySelector('#bar'), stepText=document.querySelector('#stepText'), percent=document.querySelector('#percent'), back=document.querySelector('#back'), next=document.querySelector('#next'), skip=document.querySelector('#skip'), submit=document.querySelector('#submit');
 function show(i){index=Math.max(0,Math.min(cards.length-1,i));cards.forEach((c,n)=>c.classList.toggle('active',n===index));const p=Math.round((index+1)/cards.length*100);bar.style.width=p+'%';stepText.textContent=(index+1)+' / '+cards.length;percent.textContent=p+'% tamamlandı';back.hidden=index===0;next.hidden=index===cards.length-1;submit.hidden=index!==cards.length-1;skip.hidden=!cards[index].classList.contains('optional-step');if(index===cards.length-1)review();scrollTo({top:0,behavior:'smooth'})}
 function review(){const fields=[['Firma','company_name'],['Telefon','company_phone'],['Ürün ve renk','products_and_colors'],['Minimum adet','minimum_order'],['Numune','sample_price']];document.querySelector('#review').innerHTML=fields.map(([l,n])=>{const e=form.elements[n];return e&&e.value?'<li><b>'+l+'</b>'+esc(e.value)+'</li>':''}).join('')}
 function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML}
 function save(){const out={};new FormData(form).forEach((v,k)=>{if(k==='_token'||k==='confirmed')return;if(k.endsWith('[]'))(out[k]??=[]).push(v);else out[k]=v});localStorage.setItem(key,JSON.stringify(out))}
 function restore(){try{const d=JSON.parse(localStorage.getItem(key));if(!d)return;Object.entries(d).forEach(([n,v])=>{const els=form.querySelectorAll('[name="'+CSS.escape(n)+'"]');els.forEach(e=>e.type==='checkbox'?e.checked=[].concat(v).includes(e.value):e.value=v)})}catch(e){}}
 next.onclick=()=>{const required=[...cards[index].querySelectorAll('[required]')];if(required.some(e=>!e.value||e.type==='checkbox'&&!cards[index].querySelector('[name="'+e.name+'"]:checked'))){required.find(e=>!e.value)?.reportValidity();return}save();show(index+1)};
 back.onclick=()=>show(index-1);skip.onclick=()=>{save();show(index+1)};form.addEventListener('input',save);form.addEventListener('submit',()=>localStorage.removeItem(key));restore();show(0);
})();
</script>
</body>
</html>
