<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#07111f">
    <title>WAI Insurance Automation • Operasyon Prototipi</title>
    <style>
        :root{--bg:#07111f;--panel:#0b1728;--panel2:#0f2037;--line:#20344f;--text:#f7fbff;--muted:#91a4bb;--blue:#3ea6ff;--green:#31d08b;--amber:#ffbd45;--red:#ff6b6b;--shadow:0 26px 80px rgba(0,0,0,.34)}
        *{box-sizing:border-box} html{scroll-behavior:smooth} body{margin:0;font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at top left,#13375e 0,transparent 32%),radial-gradient(circle at top right,#0f4a41 0,transparent 27%),var(--bg);color:var(--text);min-height:100vh}
        button,input{font:inherit} button{cursor:pointer}.wrap{max-width:1180px;margin:0 auto;padding:28px 18px 56px}.top{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px}.brand{display:flex;align-items:center;gap:12px}.logo{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#3ea6ff,#20d49d);font-weight:900;box-shadow:0 12px 32px rgba(62,166,255,.25)}.brand b{display:block;font-size:15px}.brand span{display:block;color:var(--muted);font-size:12px;margin-top:2px}.badge{padding:8px 12px;border:1px solid rgba(49,208,139,.3);background:rgba(49,208,139,.08);border-radius:999px;color:#baf4da;font-size:12px;font-weight:800;white-space:nowrap}.hero{border:1px solid rgba(255,255,255,.08);background:linear-gradient(160deg,rgba(15,32,55,.95),rgba(7,17,31,.96));box-shadow:var(--shadow);border-radius:28px;padding:34px;overflow:hidden;position:relative}.hero:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(62,166,255,.08);right:-100px;top:-110px}.eyebrow{font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#8fcfff;font-weight:900}.hero h1{font-size:clamp(30px,5vw,58px);line-height:1.02;margin:12px 0 12px;max-width:850px;letter-spacing:-.04em}.hero p{color:#b9c7d7;max-width:780px;line-height:1.65;font-size:16px;margin:0}.hero-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}.chip{border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.045);padding:9px 12px;border-radius:12px;color:#d8e3ef;font-size:12px;font-weight:700}.tabs{display:flex;gap:8px;margin:22px 0}.tab{flex:1;border:1px solid var(--line);background:#0a1626;color:#9eb1c7;border-radius:14px;padding:14px 12px;font-weight:800;transition:.2s}.tab.active{background:linear-gradient(135deg,#1567b7,#0c8c77);color:white;border-color:transparent;box-shadow:0 10px 30px rgba(36,145,224,.18)}.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.card{border:1px solid var(--line);background:rgba(11,23,40,.92);border-radius:22px;padding:22px;box-shadow:0 14px 50px rgba(0,0,0,.2)}.card h3{margin:0 0 6px;font-size:18px}.sub{color:var(--muted);font-size:13px;line-height:1.5}.phone{max-width:430px;margin:0 auto;background:#071018;border:1px solid #273d58;border-radius:34px;padding:10px;box-shadow:0 24px 70px rgba(0,0,0,.42)}.phone-top{height:34px;display:flex;justify-content:center;align-items:center}.notch{width:112px;height:20px;border-radius:999px;background:#02060a}.screen{background:linear-gradient(180deg,#0e1b2b,#09131f);border-radius:26px;min-height:590px;overflow:hidden}.wa-head{display:flex;align-items:center;gap:10px;padding:14px 15px;border-bottom:1px solid rgba(255,255,255,.06);background:#0c1c2c}.avatar{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#3ea6ff,#31d08b);font-weight:900}.wa-head b{font-size:14px}.wa-head small{display:block;color:#74d9a8;margin-top:3px}.chat{padding:18px 13px;display:flex;flex-direction:column;gap:10px;min-height:485px}.msg{max-width:86%;padding:11px 12px;border-radius:12px;font-size:13px;line-height:1.45;box-shadow:0 4px 12px rgba(0,0,0,.16)}.incoming{background:#172637;border-top-left-radius:3px}.outgoing{background:#0e5b4b;align-self:flex-end;border-top-right-radius:3px}.time{display:block;color:#91a4bb;font-size:10px;text-align:right;margin-top:5px}.composer{padding:10px;border-top:1px solid rgba(255,255,255,.06)}.upload{width:100%;border:1px dashed #31577d;background:#0e1e31;border-radius:14px;padding:14px;color:#d8e8f8;font-weight:800}.steps{display:grid;gap:10px;margin-top:18px}.step{display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--line);background:#0a1626;border-radius:13px}.dot{width:29px;height:29px;border-radius:50%;display:grid;place-items:center;background:#13243a;color:#7993ae;font-size:12px;font-weight:900;flex:0 0 auto}.step.done .dot{background:rgba(49,208,139,.16);color:#62e7ab}.step.active{border-color:#2e83bf;background:rgba(62,166,255,.07)}.step.active .dot{background:rgba(62,166,255,.18);color:#80c8ff}.step strong{display:block;font-size:13px}.step span{display:block;color:var(--muted);font-size:11px;margin-top:2px}.result{display:none;margin-top:16px;border:1px solid rgba(49,208,139,.35);background:linear-gradient(145deg,rgba(49,208,139,.1),rgba(62,166,255,.06));border-radius:18px;padding:18px}.result.show{display:block}.price{font-size:38px;font-weight:950;letter-spacing:-.03em;margin:8px 0}.pay{width:100%;border:0;border-radius:13px;padding:14px;background:linear-gradient(135deg,#21bf7b,#1586d1);color:white;font-weight:900;margin-top:10px}.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px}.kpi{padding:17px;border:1px solid var(--line);background:#0a1626;border-radius:16px}.kpi b{font-size:28px;display:block}.kpi span{color:var(--muted);font-size:11px}.table{overflow:auto;border:1px solid var(--line);border-radius:16px}.row{min-width:760px;display:grid;grid-template-columns:1.05fr 1.15fr 1.15fr .9fr .8fr;gap:8px;align-items:center;padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.06);font-size:12px}.row:last-child{border-bottom:0}.row.head{color:#8fa7c0;background:#0a1626;font-weight:800}.status{display:inline-block;padding:7px 9px;border-radius:9px;font-size:11px;font-weight:900}.danger{color:#ffb3b3;background:rgba(255,107,107,.12)}.ok{color:#a8f0cf;background:rgba(49,208,139,.1)}.action{border:1px solid #2a537a;background:#102640;color:#b8dcff;border-radius:9px;padding:8px 9px;font-weight:800}.notice{margin-top:18px;padding:14px 16px;border-radius:14px;border:1px solid rgba(255,189,69,.23);background:rgba(255,189,69,.07);color:#e9cc8d;font-size:11px;line-height:1.6}.hidden{display:none}.toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(20px);background:#f7fbff;color:#07111f;border-radius:12px;padding:12px 16px;font-weight:800;font-size:12px;opacity:0;pointer-events:none;transition:.25s;box-shadow:0 14px 40px rgba(0,0,0,.3);z-index:30}.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
        @media(max-width:850px){.wrap{padding:18px 12px 36px}.top{align-items:flex-start}.hero{padding:24px 20px;border-radius:22px}.grid{grid-template-columns:1fr}.tabs{position:sticky;top:8px;z-index:5;background:rgba(7,17,31,.82);backdrop-filter:blur(12px);padding:7px;border-radius:16px}.card{padding:16px}.kpis{grid-template-columns:1fr}.screen{min-height:565px}.badge{font-size:10px}.brand span{max-width:190px}.hero-meta{gap:7px}.chip{font-size:11px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="brand"><div class="logo">W</div><div><b>WAI Insurance Automation</b><span>Doğuş Topçu Sigorta • Operasyon Prototipi</span></div></div>
        <div class="badge">● DEMO ORTAMI</div>
    </div>

    <section class="hero">
        <div class="eyebrow">Kurumsal Sigorta Operasyon Otomasyonu</div>
        <h1>Ruhsattan ödemeye, tek akışta.</h1>
        <p>Yoğun WhatsApp operasyonunda personelin ekranlar arasında kaybolmasını azaltan; ruhsat verisini okuyup teklif akışını başlatan, tek prim sonucunu sunan ve satış kaybı takibini merkezileştiren özel operasyon prototipi.</p>
        <div class="hero-meta"><div class="chip">WhatsApp tabanlı işlem</div><div class="chip">Ruhsat veri çıkarımı</div><div class="chip">Tek prim akışı</div><div class="chip">Poliçe kaybı takibi</div><div class="chip">7/24 yoğun operasyon tasarımı</div></div>
    </section>

    <div class="tabs">
        <button class="tab active" data-tab="quote">Ruhsattan Teklif</button>
        <button class="tab" data-tab="monitor">Poliçe Takip Merkezi</button>
    </div>

    <section id="quoteTab">
        <div class="grid">
            <div class="card">
                <h3>WhatsApp Personel Akışı</h3>
                <div class="sub">Demo senaryosunu başlatmak için aşağıdaki “Ruhsat Gönder” butonuna dokunun.</div>
                <div class="phone" style="margin-top:18px">
                    <div class="phone-top"><div class="notch"></div></div>
                    <div class="screen">
                        <div class="wa-head"><div class="avatar">AI</div><div><b>WAI Sigorta Operasyon</b><small>● çevrimiçi</small></div></div>
                        <div class="chat" id="chat">
                            <div class="msg incoming">Merhaba. Ruhsatı gönderdiğiniz anda araç bilgilerini okuyup teklif sürecini başlatabilirim.<span class="time">19:42</span></div>
                        </div>
                        <div class="composer"><button class="upload" id="startBtn">＋ Ruhsat Gönder</button></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>İşlem Motoru</h3>
                <div class="sub">Personel yalnızca ruhsatı gönderir. Geri kalan operasyon adımları tek akışta ilerler.</div>
                <div class="steps">
                    <div class="step" id="s1"><div class="dot">1</div><div><strong>Ruhsat alındı</strong><span>Belge işleme kuyruğuna eklendi</span></div></div>
                    <div class="step" id="s2"><div class="dot">2</div><div><strong>Araç bilgileri okunuyor</strong><span>Plaka • motor no • şasi no • marka/model</span></div></div>
                    <div class="step" id="s3"><div class="dot">3</div><div><strong>Teklif sistemi sorgusu</strong><span>Yetkili entegrasyon üzerinden</span></div></div>
                    <div class="step" id="s4"><div class="dot">4</div><div><strong>Tek prim sonucu</strong><span>Personele tek sonuç olarak iletilir</span></div></div>
                    <div class="step" id="s5"><div class="dot">5</div><div><strong>Ödeme / onay</strong><span>İşlem ödeme aşamasına taşınır</span></div></div>
                </div>
                <div class="result" id="result">
                    <div class="sub">34 DGS 784 • Renault Clio • 2023</div>
                    <div style="font-size:12px;color:#8eb0cb;margin-top:10px">Trafik Sigortası • Tek Prim</div>
                    <div class="price">9.486 TL</div>
                    <div style="font-size:12px;color:#84e2b8;font-weight:800">✓ Teklif başarıyla hazırlandı</div>
                    <button class="pay" id="payBtn">Ödeme Aşamasına Geç</button>
                </div>
            </div>
        </div>
    </section>

    <section id="monitorTab" class="hidden">
        <div class="card">
            <h3>Poliçe Takip & Satış Geri Kazanım Merkezi</h3>
            <div class="sub">Mevcut aktif portföy içerisinden yeniden poliçe düzenlenen veya satış ekibinin yeniden ilgilenmesi gereken kayıtların merkezî takibi.</div>
            <div class="kpis" style="margin-top:18px">
                <div class="kpi"><b>3.247</b><span>Aktif poliçe</span></div>
                <div class="kpi"><b>142</b><span>Kontrol bekleyen kayıt</span></div>
                <div class="kpi"><b>37</b><span>Yeni poliçe tespit edilen</span></div>
            </div>
            <div class="table">
                <div class="row head"><div>Araç</div><div>Mevcut kayıt</div><div>Son tespit</div><div>Durum</div><div>İşlem</div></div>
                <div class="row"><div><b>34 XYZ 789</b><br><span class="sub">Toyota Corolla</span></div><div>14.10.2025 • Aktif</div><div>06.09.2026 • Yeni poliçe</div><div><span class="status danger">Satış görüşmesi</span></div><div><button class="action" onclick="toast('Satış ekibine görev oluşturuldu')">Ekibe aktar</button></div></div>
                <div class="row"><div><b>06 DR 4421</b><br><span class="sub">Fiat Egea</span></div><div>21.11.2025 • Aktif</div><div>07.09.2026 • Kontrol</div><div><span class="status danger">Yeni poliçe</span></div><div><button class="action" onclick="toast('Satış ekibine görev oluşturuldu')">Ekibe aktar</button></div></div>
                <div class="row"><div><b>35 TK 907</b><br><span class="sub">Renault Megane</span></div><div>03.12.2025 • Aktif</div><div>07.09.2026 • Eşleşme yok</div><div><span class="status ok">Korunuyor</span></div><div><button class="action" onclick="toast('Kayıt detayları açıldı')">Detay</button></div></div>
                <div class="row"><div><b>16 AK 118</b><br><span class="sub">Peugeot 3008</span></div><div>19.09.2025 • Aktif</div><div>05.09.2026 • Yeni poliçe</div><div><span class="status danger">Satış görüşmesi</span></div><div><button class="action" onclick="toast('Satış ekibine görev oluşturuldu')">Ekibe aktar</button></div></div>
            </div>
        </div>
    </section>

    <div class="notice"><b>Prototip notu:</b> Bu sayfa operasyon akışını göstermek amacıyla hazırlanmış demo ortamıdır. Ekrandaki araç, poliçe ve prim verileri temsili örneklerdir. Canlı kullanımda sigorta/teklif/ödeme ve poliçe kontrol adımları, müşterinin kullandığı yetkili sistemler ve izin verilen entegrasyonlar üzerinden bağlanacaktır.</div>
</div>
<div class="toast" id="toast"></div>
<script>
    const tabs=document.querySelectorAll('.tab');
    tabs.forEach(t=>t.addEventListener('click',()=>{tabs.forEach(x=>x.classList.remove('active'));t.classList.add('active');const quote=t.dataset.tab==='quote';document.getElementById('quoteTab').classList.toggle('hidden',!quote);document.getElementById('monitorTab').classList.toggle('hidden',quote)}));
    const chat=document.getElementById('chat'), btn=document.getElementById('startBtn'), result=document.getElementById('result');
    const wait=ms=>new Promise(r=>setTimeout(r,ms));
    function add(text,type='incoming'){const d=document.createElement('div');d.className='msg '+type;d.innerHTML=text+'<span class="time">şimdi</span>';chat.appendChild(d);chat.scrollTop=chat.scrollHeight}
    function setStep(n,state){const el=document.getElementById('s'+n);el.classList.remove('active','done');if(state)el.classList.add(state)}
    btn.addEventListener('click',async()=>{btn.disabled=true;btn.textContent='Ruhsat işleniyor…';result.classList.remove('show');for(let i=1;i<=5;i++)setStep(i,'');add('📄 <b>ruhsat_34DGS784.jpg</b>','outgoing');setStep(1,'active');await wait(650);setStep(1,'done');setStep(2,'active');add('Ruhsat alındı. Araç bilgileri okunuyor…');await wait(900);setStep(2,'done');add('✓ Plaka: <b>34 DGS 784</b><br>✓ Marka/Model: <b>Renault Clio 2023</b><br>✓ Motor ve şasi bilgileri okundu.');setStep(3,'active');await wait(1100);setStep(3,'done');setStep(4,'active');add('Yetkili teklif sistemi sorgusu tamamlanıyor…');await wait(900);setStep(4,'done');setStep(5,'active');result.classList.add('show');add('Tek prim sonucu hazırlandı: <b>9.486 TL</b><br>Ödeme/onay aşamasına geçilebilir.');await wait(450);setStep(5,'done');btn.textContent='↻ Demoyu Tekrar Başlat';btn.disabled=false});
    document.getElementById('payBtn').addEventListener('click',()=>toast('Ödeme/onay ekranına aktarım simüle edildi • Toplam demo süresi: 01:42'));
    function toast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),2600)}
</script>
</body>
</html>
