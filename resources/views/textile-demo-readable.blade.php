@include('textile-demo')
<style>
/* Readability pass for customer-facing textile demo */
body{font-size:16px!important}
.brand span{font-size:13px!important;line-height:1.5!important}
.demo{font-size:12px!important}.eyebrow{font-size:13px!important}.chip{font-size:12px!important;line-height:1.45!important}
.sub{font-size:14px!important;line-height:1.7!important}
.kpi b{font-size:30px!important}.kpi span{font-size:13px!important;line-height:1.5!important}
.activityItem{padding:14px!important;align-items:flex-start!important}.activityItem b{font-size:14px!important;line-height:1.45!important}.activityItem span{font-size:12px!important;line-height:1.5!important}
.order b{font-size:12px!important;line-height:1.4!important}.order small{font-size:11px!important;line-height:1.55!important}.tag{font-size:10px!important;padding:6px 8px!important}.stage h4{font-size:12px!important}
.primary,.softbtn,.start,.tab{font-size:14px!important}.notice{font-size:12px!important;line-height:1.75!important;padding:16px 18px!important}
.calcrow{font-size:13px!important}.field label,.control label{font-size:12px!important}.wahead b{font-size:14px!important}.wahead small{font-size:11px!important}.msg{font-size:13px!important;line-height:1.55!important}.time{font-size:10px!important}.step strong{font-size:13px!important}.step span{font-size:11px!important}
.uploadZone{font-size:13px!important;line-height:1.6!important}.mockStatus{font-size:13px!important;line-height:1.6!important}.toast{font-size:13px!important}
.dashboardGrid .card div[style*="font-size:10px"],.dashboardGrid .card div[style*="font-size: 10px"],#pricingTab div[style*="font-size:10px"],#pricingTab div[style*="font-size: 10px"]{font-size:13px!important;line-height:1.5!important}
@media(max-width:930px){body{font-size:15px!important}.sub{font-size:13px!important}.kpi span{font-size:12px!important}.kpi b{font-size:27px!important}.activityItem b{font-size:13px!important}.activityItem span{font-size:11px!important}.notice{font-size:11px!important}.primary,.softbtn,.start,.tab{font-size:13px!important}}
</style>
<script>
(function(){
  function byId(id){return document.getElementById(id)}
  function showToast(msg){var t=byId('toast');if(!t)return;t.textContent=msg;t.classList.add('show');setTimeout(function(){t.classList.remove('show')},2200)}
  function openTab(name){
    document.querySelectorAll('.tab').forEach(function(t){t.classList.toggle('active',t.dataset.tab===name)});
    ['sales','studio','pricing','production','dashboard'].forEach(function(k){var s=byId(k+'Tab');if(s)s.classList.toggle('hidden',k!==name)});
    window.scrollTo({top:0,behavior:'smooth'});
  }
  document.querySelectorAll('.tab').forEach(function(t){t.onclick=function(){openTab(t.dataset.tab)}});

  var chat=byId('chat'), running=false;
  function addMsg(text,type){if(!chat)return;var d=document.createElement('div');d.className='msg '+type;d.innerHTML=text+'<span class="time">22:20</span>';chat.appendChild(d);chat.scrollTop=chat.scrollHeight}
  function mark(n){var s=byId('s'+n);if(!s)return;s.classList.add('active');setTimeout(function(){s.classList.remove('active');s.classList.add('done');var dot=s.querySelector('.dot');if(dot)dot.textContent='✓'},350)}
  var startBtn=byId('startBtn');
  if(startBtn) startBtn.onclick=function(){
    if(running)return;running=true;
    if(chat)chat.innerHTML='<div class="msg in">Merhaba 👋 Ürün, adet ve baskı detaylarını yazın; logonuzu da gönderirseniz tişört üzerinde önizleme ve fiyat hazırlayabilirim.<span class="time">22:20</span></div>';
    document.querySelectorAll('.step').forEach(function(s){s.className='step';var d=s.querySelector('.dot');if(d)d.textContent=d.parentElement&&d.parentElement.id?d.parentElement.id.replace('s',''):d.textContent});
    var q=byId('quote');if(q)q.classList.remove('show');
    var steps=[
      function(){addMsg('Merhaba, 250 adet siyah oversize tişört yaptırmak istiyoruz.','out')},
      function(){addMsg('Tabii. Beden dağılımı ve baskı konumu nasıl olacak?','in');mark(1)},
      function(){addMsg('S–XL karışık. Ön göğüste logomuz olacak.','out');mark(2)},
      function(){addMsg('Harika. Premium 30/1 penye üzerinden ilerleyebilirim. Logonuzu gönderdiğinizde tişört üzerinde önizleme de hazırlayacağım.','in');mark(3)},
      function(){addMsg('Logo gönderildi 📎 company-logo.png','out');mark(4)},
      function(){addMsg('Logo alındı ✓ 250 adet için fiyat ve termin hesaplıyorum…','in')},
      function(){addMsg('Teklif hazır ✓<br><b>250 adet Premium Oversize</b><br>Birim: <b>189 TL</b><br>Toplam: <b>47.250 TL</b><br>Termin: <b>7–9 iş günü</b>','in');mark(5);if(q)q.classList.add('show')}
    ];
    steps.forEach(function(fn,i){setTimeout(fn,550+i*650)});setTimeout(function(){running=false},5200);
  };
  var toStudio=byId('toStudio');if(toStudio)toStudio.onclick=function(){openTab('studio')};

  var shirt=byId('shirtPath'),logoPreview=byId('logoPreview'),logoText=byId('logoText');
  document.querySelectorAll('.swatch').forEach(function(s){s.onclick=function(){document.querySelectorAll('.swatch').forEach(function(x){x.classList.remove('active')});s.classList.add('active');if(shirt)shirt.setAttribute('fill',s.dataset.color)}});
  var upload=byId('logoUpload');if(upload)upload.onchange=function(e){var file=e.target.files&&e.target.files[0];if(!file)return;var r=new FileReader();r.onload=function(ev){if(logoPreview){logoPreview.src=ev.target.result;logoPreview.style.display='block'}if(logoText)logoText.style.display='none';showToast('Logo mockup üzerine yerleştirildi')};r.readAsDataURL(file)};
  var pos=byId('position');if(pos)pos.onchange=function(e){var v=e.target.value;if(!logoPreview||!logoText)return;if(v==='chest'){logoPreview.style.left='42%';logoPreview.style.top='39%';logoPreview.style.width='58px';logoText.style.left='42%';logoText.style.top='39%'}else if(v==='large'){logoPreview.style.left='50%';logoPreview.style.top='48%';logoPreview.style.width='145px';logoText.style.left='50%';logoText.style.top='48%'}else{logoPreview.style.left='50%';logoPreview.style.top='42%';logoPreview.style.width='82px';logoText.style.left='50%';logoText.style.top='42%'}};
  var gm=byId('generateMock');if(gm)gm.onclick=function(){var s=byId('mockStatus');if(s)s.classList.add('show');showToast('Müşteriye gönderilecek mockup hazır')};

  function calc(){var product=byId('product'),pr=byId('print'),qty=byId('qty'),term=byId('term');if(!product||!pr||!qty||!term)return;var p=+product.value, pv=+pr.value, q=Math.max(10,+qty.value||10),tv=+term.value;var disc=q>=1000?.14:q>=500?.10:q>=250?.06:q>=100?.03:0;var base=p+pv,unit=Math.round(base*(1-disc)*tv),total=unit*q;var map={baseLine:base+' TL',discountLine:'-%'+Math.round(disc*100),unitLine:unit+' TL',totalLine:total.toLocaleString('tr-TR')+' TL',offerTotal:total.toLocaleString('tr-TR')+' TL'};Object.keys(map).forEach(function(id){var el=byId(id);if(el)el.textContent=map[id]})}
  ['product','print','qty','term'].forEach(function(id){var el=byId(id);if(el){el.oninput=calc;el.onchange=calc}});calc();
  var aq=byId('approveQuote');if(aq)aq.onclick=function(){showToast('Sipariş üretim kuyruğuna aktarıldı');setTimeout(function(){openTab('production')},500)};
  var so=byId('sendOffer');if(so)so.onclick=function(){showToast('WhatsApp teklif önizlemesi hazırlandı')};
  var mo=byId('moveOrder');if(mo)mo.onclick=function(e){e.target.textContent='✓ #842 Hazırlık Aşamasında';e.target.disabled=true;var n=byId('newOrder');if(n){var tag=n.querySelector('.tag');if(tag)tag.textContent='Hazırlığa alındı'}showToast('Sipariş durumu güncellendi')};
  var rb=byId('reportBtn');if(rb)rb.onclick=function(){showToast('Günlük yönetici raporu oluşturuldu')};
})();
</script>
