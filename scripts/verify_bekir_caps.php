<?php
require '/app/vendor/autoload.php';require '/tmp/bekir-hat.php';
$app=require '/app/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Http;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Cache;
Http::preventStrayRequests();Http::fake(['*'=>Http::response(['key'=>['id'=>'HAT_TEST']],200)]);
$logo=imagecreatetruecolor(100,60);imagefill($logo,0,0,imagecolorallocate($logo,20,80,160));ob_start();imagepng($logo);$b64=base64_encode(ob_get_clean());imagedestroy($logo);
$s=app(App\Services\Textile\TextileWhatsAppInboundService::class);$parse=new ReflectionMethod($s,'parseText');$stateMethod=new ReflectionMethod($s,'state');
DB::beginTransaction();
try {
 foreach(['Pamuklu Şapka','Polyester Şapka'] as $i=>$product){
  $parsed=$parse->invoke($s,$stateMethod->invoke($s,null),$product.' beyaz 50 adet');
  if(($parsed['product']??null)!==$product)throw new RuntimeException('parse failed');
  $phone='0000000077'.$i;$key='textile_demo_state:53:'.$phone;
  Cache::store('database')->put($key,['product'=>$product,'product_category'=>'cap','quantity'=>50,'color'=>'white','color_label'=>'Beyaz','logo_received'=>true,'logo_base64'=>$b64,'logo_mime'=>'image/png','position'=>null,'mockup_sent'=>false],now()->addMinutes(3));
  $before=Http::recorded()->count();
  $s->processPayload(['instance'=>'bekir-tekstil-53','event'=>'messages.upsert','data'=>['key'=>['remoteJid'=>$phone.'@s.whatsapp.net','id'=>'HAT_QA_'.bin2hex(random_bytes(5)),'fromMe'=>false],'message'=>['conversation'=>'ön orta']]],true);
  $st=Cache::store('database')->get($key);
  if(!($st['mockup_sent']??false)||($st['product']??null)!==$product)throw new RuntimeException('cap not rendered');
  if(Http::recorded()->count()-$before!==2)throw new RuntimeException('expected image and summary');
  echo "PASS $product: accepted, rendered, image and summary\n";
 }
 echo "PASS no customer messages sent\n";
} finally {DB::rollBack();}
