<?php
require '/app/vendor/autoload.php';
require '/tmp/bekir-sides-renderer.php';
require '/tmp/bekir-sides-inbound.php';
$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Http;use Illuminate\Support\Facades\Cache;
function ck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$original=file_get_contents('/app/app/Services/Textile/TextileMockupService.php');
eval('?>'.str_replace('class TextileMockupService','class LegacyRendererCheck',$original));
$r=app(App\Services\Textile\TextileMockupService::class);$old=new App\Services\Textile\LegacyRendererCheck();
$logo=imagecreatetruecolor(200,100);imagefill($logo,0,0,imagecolorallocate($logo,255,255,255));imagestring($logo,5,50,40,'TEST',imagecolorallocate($logo,10,80,180));ob_start();imagepng($logo);$b64=base64_encode(ob_get_clean());imagedestroy($logo);
ck($r->create($b64,'left_chest','white','Polo Yaka Tişört')===$old->create($b64,'left_chest','white','Polo Yaka Tişört'),'legacy rendering identical');
foreach(['Polo Yaka Tişört','Sıfır Yaka Tişört'] as $product){
foreach(['back_large','right_chest'] as $other){
$views=$r->createViews($b64,'left_chest','white',$product,[['position'=>$other,'logo_base64'=>$b64]]);
ck(count($views)===2,'two photos '.$product.' '.$other);
foreach($views as $v)ck($v['image']===$r->create($b64,$v['position'],'white',$product,[],$v['view']),'each photo has one print '.$v['position']);
if($product==='Polo Yaka Tişört'&&$other==='back_large')file_put_contents('/tmp/bekir-white-back-qa.jpg',base64_decode($views[1]['image']));
}}
Http::preventStrayRequests();Http::fake(['*'=>Http::response(['key'=>['id'=>'TEST_ONLY']],200)]);
$bot=App\Models\AiBot::findOrFail(53);$service=app(App\Services\Textile\TextileWhatsAppInboundService::class);
DB::beginTransaction();
try{
foreach(['ön sol göğüs ve arka büyük','ön sol göğüs ve ön sağ göğüs'] as $i=>$text){
$phone='0000000040'.$i;$key='textile_demo_state:53:'.$phone;
Cache::store('database')->put($key,['product'=>'Polo Yaka Tişört','product_category'=>'shirt','color'=>'white','color_label'=>'Beyaz','quantity'=>50,'logo_received'=>true,'logo_base64'=>$b64,'logo_mime'=>'image/png','position'=>null,'mockup_sent'=>false],now()->addMinutes(5));
$before=Http::recorded()->count();
$service->processPayload(['instance'=>$bot->whatsapp_instance,'event'=>'messages.upsert','data'=>['key'=>['remoteJid'=>$phone.'@s.whatsapp.net','id'=>'SIDES_QA_'.bin2hex(random_bytes(4)),'fromMe'=>false],'message'=>['conversation'=>$text]]],true);
$state=Cache::store('database')->get($key);
ck(($state['mockup_sent']??false),'inbound completes '.$text);
ck(Http::recorded()->count()-$before===3,'two media plus one summary '.$text);
}
ck(Http::recorded(fn($q)=>str_contains($q->url(),'/message/sendMedia/'))->count()===4,'four intercepted image requests');
echo "No messages delivered externally; test conversation rows rolled back.\n";
}finally{DB::rollBack();}
