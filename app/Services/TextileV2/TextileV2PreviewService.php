<?php
namespace App\Services\TextileV2;
use App\Services\Textile\TextileMockupService;
class TextileV2PreviewService {
 public function __construct(private readonly TextileMockupService $mockups) {}
 public function create(array $s): string {
  $imgs=[];
  foreach($s['positions'] as $p){
   $view=str_starts_with($p,'back')?'back':'front';
   $imgs[]=$this->mockups->create((string)$s['logo_base64'],$p,(string)$s['color'],(string)$s['product_label'],[], $view, true);
  }
  return count($imgs)===1?$imgs[0]:$this->combine($imgs);
 }
 private function combine(array $images): string {
  $decoded=[];
  foreach($images as $enc){ $raw=base64_decode((string)$enc,true); $im=is_string($raw)?@imagecreatefromstring($raw):false; if($im!==false)$decoded[]=$im; }
  if(!$decoded) throw new \RuntimeException('Önizleme görselleri birleştirilemedi.');
  $tile=1200;$cols=min(2,count($decoded));$rows=(int)ceil(count($decoded)/$cols);
  $canvas=imagecreatetruecolor($tile*$cols,$tile*$rows);$white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);
  foreach($decoded as $i=>$im){$x=($i%$cols)*$tile;$y=intdiv($i,$cols)*$tile;imagecopyresampled($canvas,$im,$x,$y,0,0,$tile,$tile,imagesx($im),imagesy($im));imagedestroy($im);}
  ob_start();imagejpeg($canvas,null,94);$jpg=ob_get_clean();imagedestroy($canvas);
  return base64_encode((string)$jpg);
 }
}
