<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $lead['company_name'] ?? 'WAI Demo' }} · Canlı Test</title>
    <style>
        :root{--bg:#07110d;--panel:#0d1713;--line:#1d2a24;--text:#f5f7f6;--muted:#8fa097;--green:#25d366;--mine:#005c4b;--theirs:#202c33}
        *{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif;background:radial-gradient(circle at top,#153227 0,#07110d 42%,#050806 100%);color:var(--text)}
        body{min-height:100dvh}.shell{width:min(100%,520px);min-height:100dvh;margin:auto;background:#0b141a;box-shadow:0 0 80px #0008;display:flex;flex-direction:column;position:relative;overflow:hidden}
        .top{height:68px;padding:10px 14px;display:flex;align-items:center;gap:11px;background:#202c33;border-bottom:1px solid #2a3942;position:sticky;top:0;z-index:5}.avatar{width:43px;height:43px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(145deg,#33e77a,#128c7e);font-weight:900;color:#04130a}.title{min-width:0;flex:1}.title strong{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:15px}.title span{font-size:12px;color:#a9b7bf}.wai{font-size:11px;font-weight:900;letter-spacing:.8px;padding:6px 8px;border:1px solid #3d4b52;border-radius:999px;color:#c7d3d8}
        .intro{padding:10px 14px;background:#0d1713;border-bottom:1px solid #18242a;text-align:center}.intro b{font-size:12px;color:#b9f6cd}.intro span{display:block;margin-top:2px;font-size:11px;color:#82918a}
        .chat{flex:1;min-height:420px;padding:18px 12px 108px;overflow:auto;background-color:#0b141a;background-image:linear-gradient(45deg,#ffffff06 25%,transparent 25%),linear-gradient(-45deg,#ffffff04 25%,transparent 25%);background-size:28px 28px}.day{width:max-content;margin:0 auto 14px;padding:5px 9px;border-radius:7px;background:#182229;color:#aebbc2;font-size:10px;font-weight:700}.row{display:flex;margin:6px 0}.row.user{justify-content:flex-end}.bubble{max-width:83%;padding:8px 9px 7px;border-radius:9px;font-size:14.5px;line-height:1.42;white-space:pre-wrap;box-shadow:0 1px 1px #0005;position:relative}.ai .bubble{background:var(--theirs);border-top-left-radius:2px}.user .bubble{background:var(--mine);border-top-right-radius:2px}.time{font-size:9px;color:#9cacb3;margin-left:7px;vertical-align:-2px;white-space:nowrap}
        .typing{display:inline-flex;gap:3px;align-items:center;height:13px}.typing i{width:5px;height:5px;background:#9aabb2;border-radius:50%;animation:pulse 1s infinite}.typing i:nth-child(2){animation-delay:.15s}.typing i:nth-child(3){animation-delay:.3s}@keyframes pulse{50%{opacity:.25;transform:translateY(-2px)}}
        .bottom{position:absolute;left:0;right:0;bottom:0;padding:8px 10px calc(9px + env(safe-area-inset-bottom));background:linear-gradient(180deg,transparent,#0b141a 18%);z-index:4}.composer{display:flex;align-items:flex-end;gap:7px}.inputwrap{flex:1;background:#202c33;border-radius:23px;padding:0 14px}.composer textarea{display:block;width:100%;height:45px;max-height:110px;resize:none;border:0;outline:0;background:transparent;color:#fff;font:inherit;padding:12px 0}.composer textarea::placeholder{color:#84949c}.send{width:45px;height:45px;border:0;border-radius:50%;background:var(--green);color:#04210f;font-size:20px;font-weight:900;cursor:pointer}.connect{width:100%;margin-top:7px;border:0;border-radius:12px;padding:10px 12px;background:#17372a;color:#bff7d1;font-weight:800;cursor:pointer}.error{padding:5px 3px 0;font-size:11px;color:#ffaaa5}.hidden{display:none!important}
        .sheetback{position:fixed;inset:0;background:#000b;z-index:20;display:flex;align-items:flex-end;justify-content:center}.sheet{width:min(100%,520px);background:#111b17;border-radius:24px 24px 0 0;padding:20px 18px calc(24px + env(safe-area-inset-bottom));border:1px solid #26362e;box-shadow:0 -20px 60px #000a}.grab{width:42px;height:4px;border-radius:99px;background:#42544b;margin:-8px auto 18px}.sheet h2{font-size:20px;margin:0 0 7px}.sheet p{font-size:13px;color:#9eaea5;line-height:1.5;margin:0 0 15px}.qrbox{width:238px;height:238px;margin:12px auto;background:#fff;border-radius:18px;display:grid;place-items:center;padding:12px}.qrbox img{width:100%;height:100%;object-fit:contain}.status{padding:10px;border-radius:12px;background:#17221d;text-align:center;font-size:12px;color:#b7c6be}.close{width:100%;margin-top:12px;padding:11px;border-radius:12px;border:1px solid #314138;background:transparent;color:#dce5e0;font-weight:700}.connected{color:#77ee9f;font-weight:800}
        @media(min-width:521px){body{padding:18px 0}.shell{min-height:calc(100dvh - 36px);height:calc(100dvh - 36px);border-radius:24px;border:1px solid #28352f}.sheetback{align-items:center}.sheet{border-radius:24px;max-height:90vh}}
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <div class="avatar">{{ mb_strtoupper(mb_substr($lead['company_name'] ?? 'W',0,1)) }}</div>
        <div class="title"><strong>{{ $lead['company_name'] ?? 'İşletmeniz' }} Yapay Zekası</strong><span>çevrimiçi · canlı demo</span></div>
        <div class="wai">WAI</div>
    </header>
    <div class="intro"><b>Size özel deneme yapay zekası hazır</b><span>Müşteriniz gibi yazın; gerçek kullanım hissiyle test edin.</span></div>

    <main id="chat" class="chat">
        <div class="day">BUGÜN</div>
        <div class="row ai"><div class="bubble">Merhaba 👋 Size nasıl yardımcı olabilirim?<span class="time">şimdi</span></div></div>
    </main>

    <div class="bottom">
        <div class="composer">
            <div class="inputwrap"><textarea id="message" maxlength="500" rows="1" placeholder="Mesaj"></textarea></div>
            <button id="send" class="send" type="button">➤</button>
        </div>
        <button id="connect" class="connect" type="button">WhatsApp'ımda 1 gün ücretsiz dene</button>
        <div id="error" class="error hidden"></div>
    </div>
</div>

<div id="sheet" class="sheetback hidden">
    <section class="sheet">
        <div class="grab"></div>
        <h2>WhatsApp'ınıza bağlayın</h2>
        <p>Üyelik açmanız gerekmiyor. WhatsApp → Ayarlar → Bağlı Cihazlar → Cihaz Bağla adımlarından QR kodu okutun. Bağlantı bu demo için 1 gün aktif kalır.</p>
        <div id="qrbox" class="qrbox"><div class="typing"><i></i><i></i><i></i></div></div>
        <div id="waStatus" class="status">QR hazırlanıyor…</div>
        <button id="closeSheet" class="close" type="button">Kapat</button>
    </section>
</div>

<script>
const token=@json($token);const chat=document.getElementById('chat');const input=document.getElementById('message');const send=document.getElementById('send');const error=document.getElementById('error');const connect=document.getElementById('connect');const sheet=document.getElementById('sheet');const qrbox=document.getElementById('qrbox');const waStatus=document.getElementById('waStatus');const history=[];let poll=null;
const companyName=@json($lead['company_name'] ?? 'İşletmem');const companyDescription=@json($lead['company_description'] ?? 'İşletmeye özel WAI demo yapay zekası.');const role=@json($lead['role'] ?? 'sales');
function now(){return new Date().toLocaleTimeString('tr-TR',{hour:'2-digit',minute:'2-digit'})}
function add(role,text){const row=document.createElement('div');row.className='row '+role;const b=document.createElement('div');b.className='bubble';b.textContent=text;const t=document.createElement('span');t.className='time';t.textContent=now();b.appendChild(t);row.appendChild(b);chat.appendChild(row);chat.scrollTop=chat.scrollHeight}
function typing(on){let e=document.getElementById('typing');if(on&&!e){e=document.createElement('div');e.id='typing';e.className='row ai';e.innerHTML='<div class="bubble"><span class="typing"><i></i><i></i><i></i></span></div>';chat.appendChild(e);chat.scrollTop=chat.scrollHeight}else if(!on&&e)e.remove()}
async function submit(){const text=input.value.trim();if(!text)return;input.value='';error.classList.add('hidden');add('user',text);send.disabled=true;typing(true);try{const r=await fetch('/demo/chat',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({company_name:companyName,company_description:companyDescription,role,message:text,messages:history.slice(-8)})});const data=await r.json();if(!r.ok||!data.success)throw new Error(data.message||'Demo cevabı alınamadı.');history.push({role:'user',content:text},{role:'assistant',content:data.message});typing(false);add('ai',data.message)}catch(e){typing(false);error.textContent=e.message;error.classList.remove('hidden')}finally{send.disabled=false;input.focus()}}
async function connectWA(){sheet.classList.remove('hidden');qrbox.innerHTML='<div class="typing"><i></i><i></i><i></i></div>';waStatus.textContent='Üyeliksiz demo bağlantısı hazırlanıyor…';try{const r=await fetch(`/demo/lead/${token}/whatsapp/connect`,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});const d=await r.json();if(!r.ok||!d.success)throw new Error(d.message||'Bağlantı hazırlanamadı');if(d.state==='open'){showConnected();return}if(d.qr){const img=document.createElement('img');img.alt='WhatsApp QR';img.src=d.qr.startsWith('data:')?d.qr:'data:image/png;base64,'+d.qr;qrbox.innerHTML='';qrbox.appendChild(img);waStatus.textContent='QR kodu WhatsApp ile okutun';startPoll()}else{waStatus.textContent='QR hazırlanıyor, birkaç saniye sonra tekrar deneyin.'}}catch(e){qrbox.innerHTML='';waStatus.textContent=e.message}}
function startPoll(){clearInterval(poll);poll=setInterval(async()=>{try{const r=await fetch(`/demo/lead/${token}/whatsapp/status`,{headers:{'Accept':'application/json'}});const d=await r.json();if(d.success&&d.state==='open')showConnected()}catch(e){}},2500)}
function showConnected(){clearInterval(poll);qrbox.innerHTML='<div style="font-size:58px">✓</div>';waStatus.innerHTML='<span class="connected">WhatsApp bağlandı. 1 günlük denemeniz başladı.</span>';connect.textContent='WhatsApp bağlı ✓'}
send.addEventListener('click',submit);input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();submit()}});input.addEventListener('input',()=>{input.style.height='45px';input.style.height=Math.min(input.scrollHeight,110)+'px'});connect.addEventListener('click',connectWA);document.getElementById('closeSheet').addEventListener('click',()=>sheet.classList.add('hidden'));
</script>
<!-- WAI demo deployment sync -->
</body>
</html>
