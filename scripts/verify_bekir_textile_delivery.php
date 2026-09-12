<?php
require '/app/vendor/autoload.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
Http::preventStrayRequests();
Http::fake(['*'=>Http::response(['key'=>['id'=>'BEKIR_TEST_ONLY']],200)]);
$bot=App\Models\AiBot::findOrFail(53);
$service=app(App\Services\Textile\TextileWhatsAppInboundService::class);
$logo=imagecreatetruecolor(160,80); imagefill($logo,0,0,imagecolorallocate($logo,255,255,255));
imagestring($logo,5,25,30,'TEST',imagecolorallocate($logo,10,80,180));
ob_start();imagepng($logo);$bytes=ob_get_clean();imagedestroy($logo);
DB::beginTransaction();
try {
foreach (['Sıfır Yaka Tişört','Polo Yaka Tişört'] as $index=>$product) {
 $phone='0000000000'.($index+1);
 $state=['product'=>$product,'product_category'=>'shirt','color'=>'black','color_label'=>'Siyah','quantity'=>null,'logo_received'=>true,'logo_base64'=>base64_encode($bytes),'position'=>'front_center','mockup_sent'=>false];
 Cache::store('database')->put('textile_demo_state:53:'.$phone,$state,now()->addMinutes(5));
 $payload=['instance'=>$bot->whatsapp_instance,'event'=>'messages.upsert','data'=>['key'=>['remoteJid'=>$phone.'@s.whatsapp.net','id'=>'BEKIR_QA_'.bin2hex(random_bytes(4)),'fromMe'=>false],'message'=>['conversation'=>'önizlemeyi hazırla']]];
 if (!$service->processPayload($payload,true)) throw new RuntimeException('Payload rejected');
 $after=Cache::store('database')->get('textile_demo_state:53:'.$phone);
 if (!($after['mockup_sent']??false)) throw new RuntimeException('Mockup not sent: '.$product);
 $record=App\Models\ChatMessage::where('session_id','whatsapp:53:'.$phone)->where('message_type','image')->first();
 if (!$record) throw new RuntimeException('Image record missing');
 echo 'PASS full inbound -> real mockup -> intercepted WhatsApp image: '.$product."\n";
}
if (Http::recorded()->count() !== 4) throw new RuntimeException('Expected four outbound requests');
if (Http::recorded(fn($r)=>str_contains($r->url(),'/message/sendMedia/') && strlen($r['media'])>10000)->count() !== 2) throw new RuntimeException('Expected two image payloads');
echo "PASS 2 image + 2 summary requests; no external messages; transaction rolled back.\n";
} finally { DB::rollBack(); }
