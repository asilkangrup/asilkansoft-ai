<?php
require '/app/vendor/autoload.php';
if (is_file('/tmp/ready-renderer.php')) require '/tmp/ready-renderer.php';
if (is_file('/tmp/legacy-renderer.php')) require '/tmp/legacy-renderer.php';
$app=require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc=new App\Services\Textile\TextileMockupService;
$old=class_exists(App\Services\Textile\LegacyTextileMockupService::class) ? new App\Services\Textile\LegacyTextileMockupService : null;
// Run against an isolated copy: the missing-file test must never affect customers.
$testPublic=sys_get_temp_dir().'/bekir-ready-test-'.getmypid();
Illuminate\Support\Facades\File::copyDirectory(public_path('assets/textile/catalog'),$testPublic.'/assets/textile/catalog');
$app->usePublicPath($testPublic);
register_shutdown_function(fn()=>Illuminate\Support\Facades\File::deleteDirectory($testPublic));
function verify($ok,$message) { if(!$ok) throw new RuntimeException($message); }
$logo=imagecreatetruecolor(100,60);
imagealphablending($logo,false);imagesavealpha($logo,true);
imagefill($logo,0,0,imagecolorallocatealpha($logo,0,0,0,127));
ob_start();imagepng($logo);$empty=base64_encode(ob_get_clean());
imagefilledellipse($logo,50,30,80,40,imagecolorallocate($logo,255,255,255));
ob_start();imagepng($logo);$art=base64_encode(ob_get_clean());imagedestroy($logo);
$products=['Sıfır Yaka Tişört'=>['regular',['front','back']], 'Polo Yaka Tişört'=>['polo',['front','back']], 'Premium Oversize Tişört'=>['oversize',['front','back']], 'Pamuklu Şapka'=>['cap-cotton',['front']], 'Polyester Şapka'=>['cap-polyester',['front']]];
$colors=['black','white','navy','burgundy','beige','red','blue','turquoise','green','yellow','orange','pink','brown','gray','charcoal'];
$load=new ReflectionMethod($svc,'loadStudioTemplate');$count=0;
foreach($products as $product=>[$kind,$faces]) foreach($colors as $color) foreach($faces as $face) {
 [$canvas,$loadedKind,$loadedColor]=$load->invoke($svc,$product,$color,$face,true);
 verify($loadedColor===$color,'wrong template color');
 ob_start();imagejpeg($canvas,null,91);$expected=base64_encode(ob_get_clean());imagedestroy($canvas);
 $position=$face==='back'?'back_large':'left_chest';
 $views=$svc->createViews($empty,$position,$color,$product);
 verify(count($views)===1 && $views[0]['view']===$face,'wrong view');
 verify($expected===$views[0]['image'],'Template pixels changed outside artwork: '.$kind.' '.$color.' '.$face);
 $count++;
}
foreach($products as $product=>[$kind,$faces]) {
 $views=$svc->createViews($art,'left_chest','blue',$product,count($faces)===2?[['position'=>'back_large','logo_base64'=>$art]]:[]);
 verify(count($views)===count($faces),'separate photo count');
 foreach($views as $v) file_put_contents('/tmp/ready-qa-'.$kind.'-'.$v['view'].'.jpg',base64_decode($v['image']));
}
if ($old) foreach(['Premium Oversize Tişört','Polo Yaka Tişört','Pamuklu Şapka','Sıfır Yaka Tişört'] as $product) foreach(['black','white','red','blue'] as $color) {
 verify($svc->create($art,'left_chest',$color,$product)===$old->create($art,'left_chest',$color,$product),'Legacy changed: '.$product.' '.$color);
}
try { $svc->createViews($art,'front_center','unlisted','Sıfır Yaka Tişört'); throw new LogicException('Unsupported color accepted'); } catch(RuntimeException $e) { verify(!($e instanceof LogicException),'missing colour fallback'); }
$path=public_path('assets/textile/catalog/ready/regular-red-front.jpg');
rename($path,$path.'.test-hidden');
try {
 try { $svc->createViews($art,'left_chest','red','Sıfır Yaka Tişört'); throw new LogicException('Missing asset accepted'); } catch(RuntimeException $e) { verify(!($e instanceof LogicException),'missing asset fallback'); }
} finally { rename($path.'.test-hidden',$path); }
echo "PASS: $count ready templates; transparent artwork preserves all template pixels; separate face outputs; 16 legacy outputs unchanged; missing color/asset rejects without recoloring.\n";
