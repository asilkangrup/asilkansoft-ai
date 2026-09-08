@include('filament.pages.sigorta-yenileme-premium')
<style>
.fi-header,.fi-page-header,#wai-sidebar-toggle{display:none!important}
.fi-main-ctn{padding-top:0!important}.fi-page,.fi-main{padding-top:0!important;margin-top:0!important}
body.fi-body{background:#06111f!important}
.ren-shell{padding-top:16px!important}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('*').forEach((el)=>{
  if(el.children.length===0&&el.textContent.trim()==='WAI Insurance OS') el.textContent='TPC Insurance OS';
  if(el.children.length===0&&el.textContent.trim()==='Kurumsal Sigorta Operasyon Platformu') el.textContent='Doğuş Topçu Sigorta • Kurumsal Operasyon Platformu';
 });
});
</script>
