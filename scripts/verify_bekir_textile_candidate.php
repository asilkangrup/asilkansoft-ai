<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Candidate and live classes are loaded under separate names before any writes.
$old = file_get_contents('/app/app/Services/Textile/TextileWhatsAppInboundService.php');
$new = file_get_contents('/tmp/TextileWhatsAppInboundServiceCandidate.php');
eval('?>'.str_replace('class TextileWhatsAppInboundService', 'class TextileOriginalCheck', $old));
eval('?>'.str_replace('class TextileWhatsAppInboundService', 'class TextileCandidateCheck', $new));
$a = (new ReflectionClass(App\Services\Textile\TextileOriginalCheck::class))->newInstanceWithoutConstructor();
$b = (new ReflectionClass(App\Services\Textile\TextileCandidateCheck::class))->newInstanceWithoutConstructor();
function callPrivate($object, $name, ...$args) { return (new ReflectionMethod($object, $name))->invoke($object, ...$args); }
function check($ok, $label) { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
$source = App\Models\AiBot::findOrFail(51);
$target = App\Models\AiBot::findOrFail(53);
$state = callPrivate($a,'state',null);
foreach (['100 adet siyah oversize sol göğüs','50 adet beyaz polo yaka ön orta','sıfır yaka','100','beyaz'] as $text) {
    $x = callPrivate($a,'parseText',$state,$text);
    $y = callPrivate($b,'parseText',$state,$text);
    check($x===$y, 'source parser unchanged: '.$text);
    foreach (['nextQuestion','quote','mockupMessage'] as $method) {
        if ($method==='mockupMessage' && !$x['position']) continue;
        check(callPrivate($a,$method,$x)===callPrivate($b,$method,$y), 'source '.$method.' unchanged');
    }
    check(callPrivate($a,'liveConversationInstructions',$x,$source)===callPrivate($b,'liveConversationInstructions',$y,$source), 'source live context unchanged');
}
$bekir = callPrivate($b,'parseText',$state,'50 adet lacivert polo yaka sol göğüs');
$bekir['bekir_catalogue']=true;
$context=callPrivate($b,'liveConversationInstructions',$bekir,$target);
check(str_contains($context,'Adet: 50') && str_contains($context,'Polo Yaka'), 'Bekir gets collected order context');
check(!str_contains($context,'Fatih Uzunkaya') && !str_contains($context,'TR18 0020') && !str_contains($context,'750 TL'), 'no source payment or price leakage');
check(str_contains($context,'Kullanım amacı ASLA sorulmaz'), 'no usage-purpose question');
check(callPrivate($b,'quote',$bekir)===null, 'unknown Bekir price not fabricated');
check(str_contains(callPrivate($b,'nextQuestion',$bekir),'logo'), 'ready order asks for artwork');
$logo=imagecreatetruecolor(160,80);
$bg=imagecolorallocate($logo,255,255,255);imagefill($logo,0,0,$bg);
imagestring($logo,5,25,30,'TEST',imagecolorallocate($logo,10,80,180));
ob_start();imagepng($logo);$bytes=ob_get_clean();imagedestroy($logo);
$renderer=app(App\Services\Textile\TextileMockupService::class);
foreach (['Sıfır Yaka Tişört','Polo Yaka Tişört'] as $product) {
    $encoded=$renderer->create(base64_encode($bytes),'front_left','black',$product);
    $image=base64_decode($encoded,true);$size=getimagesizefromstring($image);
    check(strlen($image)>10000 && $size[0]>=1000 && $size[1]>=1000, 'real JPEG mockup: '.$product);
}
echo "All checks passed. No WhatsApp messages sent; no customer settings modified.\n";
