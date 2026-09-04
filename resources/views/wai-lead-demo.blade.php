<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $lead['company_name'] ?? 'WAI Demo' }} - WAI Canlı Test</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0b0d10;color:#f7f7f8}.wrap{max-width:780px;margin:0 auto;padding:24px 16px 40px}.brand{font-weight:800;font-size:20px;letter-spacing:.2px}.hero{margin:18px 0;padding:22px;border:1px solid #252a31;border-radius:22px;background:#12151a}.hero h1{margin:0 0 8px;font-size:25px}.hero p{margin:0;color:#aeb5bf;line-height:1.55}.badge{display:inline-block;margin-bottom:12px;padding:6px 10px;border-radius:999px;background:#1d241d;color:#bbf7d0;font-size:12px;font-weight:700}.chat{height:430px;overflow:auto;padding:16px;border:1px solid #252a31;border-radius:22px;background:#0f1216}.row{display:flex;margin:10px 0}.row.user{justify-content:flex-end}.bubble{max-width:82%;padding:11px 13px;border-radius:16px;line-height:1.45;white-space:pre-wrap}.ai .bubble{background:#1a1f25}.user .bubble{background:#243a2a}.composer{display:flex;gap:8px;margin-top:12px}.composer input{flex:1;border:1px solid #2a3038;background:#11151a;color:#fff;border-radius:14px;padding:13px 14px;font-size:15px}.composer button,.cta{border:0;border-radius:14px;padding:13px 16px;font-weight:800;cursor:pointer}.composer button{background:#f3f4f6;color:#111827}.cta{display:block;width:100%;margin-top:16px;background:#22c55e;color:#07140b;text-align:center;text-decoration:none}.muted{font-size:12px;color:#858d98;margin-top:10px;text-align:center}.hidden{display:none}.error{margin-top:10px;color:#fca5a5;font-size:13px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">WAI</div>
    <section class="hero">
        <div class="badge">SİZE ÖZEL CANLI DEMO</div>
        <h1>{{ $lead['company_name'] ?? 'İşletmeniz' }} için yapay zekânız hazır</h1>
        <p>Aşağıdan müşteri gibi mesaj yazın. Yapay zekâ, satış görüşmesinde verdiğiniz işletme bilgilerine göre cevap verecek. Önce burada canlı test edin; ardından WhatsApp'ınıza bağlayabilirsiniz.</p>
    </section>

    <div id="chat" class="chat">
        <div class="row ai"><div class="bubble">Merhaba 👋 Ben {{ $lead['company_name'] ?? 'işletmeniz' }} için hazırlanmış WAI demo asistanıyım. Müşteriniz size nasıl yazıyorsa bana da öyle bir mesaj gönderin; canlı olarak deneyelim.</div></div>
    </div>

    <div class="composer">
        <input id="message" maxlength="500" placeholder="Örn. Fiyat bilgisi alabilir miyim?">
        <button id="send" type="button">Gönder</button>
    </div>
    <div id="error" class="error hidden"></div>

    <a class="cta" href="/admin/register">Canlı testi beğendim → WhatsApp'ımda 1 gün ücretsiz dene</a>
    <div class="muted">Demo bağlantısı süreli ve yalnız bu hazırlanan senaryo içindir.</div>
</div>
<script>
const chat=document.getElementById('chat');
const input=document.getElementById('message');
const send=document.getElementById('send');
const error=document.getElementById('error');
const history=[];
const companyName=@json($lead['company_name'] ?? 'İşletmem');
const companyDescription=@json($lead['company_description'] ?? 'İşletmeye özel WAI demo yapay zekası.');
const role=@json($lead['role'] ?? 'sales');
function add(role,text){const row=document.createElement('div');row.className='row '+role;const b=document.createElement('div');b.className='bubble';b.textContent=text;row.appendChild(b);chat.appendChild(row);chat.scrollTop=chat.scrollHeight;}
async function submit(){const text=input.value.trim();if(!text)return;input.value='';error.classList.add('hidden');add('user',text);send.disabled=true;try{const r=await fetch('/demo/chat',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({company_name:companyName,company_description:companyDescription,role:role,message:text,messages:history.slice(-8)})});const data=await r.json();if(!r.ok||!data.success)throw new Error(data.message||'Demo cevabı alınamadı.');history.push({role:'user',content:text});history.push({role:'assistant',content:data.message});add('ai',data.message);}catch(e){error.textContent=e.message;error.classList.remove('hidden');}finally{send.disabled=false;input.focus();}}
send.addEventListener('click',submit);input.addEventListener('keydown',e=>{if(e.key==='Enter')submit();});
</script>
</body>
</html>
