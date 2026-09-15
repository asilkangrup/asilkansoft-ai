<?php
namespace App\Services\TextileV2;
use Illuminate\Support\Str;
class TextileV2StateService {
 public function initial(): array { return ['product'=>null,'product_label'=>null,'color'=>null,'color_label'=>null,'quantity'=>null,'positions'=>[],'logo_base64'=>null,'logo_received'=>false,'mockup_sent'=>false,'approved'=>false,'approved_notified'=>false]; }
 public function normalize(mixed $v): array { return is_array($v)?array_replace($this->initial(),$v):$this->initial(); }
 public function parse(array $s,string $text): array {
  $l=Str::lower(trim($text));
  foreach(['oversize'=>['oversize','Premium Oversize Tişört'],'regular'=>['regular','Regular Fit Tişört'],'polo yaka'=>['polo','Polo Yaka Tişört'],'polo'=>['polo','Polo Yaka Tişört']] as $n=>[$c,$label]) if(str_contains($l,$n)){ $s['product']=$c;$s['product_label']=$label;$s['mockup_sent']=false;break; }
  foreach(['siyah'=>['black','Siyah'],'beyaz'=>['white','Beyaz'],'lacivert'=>['navy','Lacivert'],'bordo'=>['burgundy','Bordo'],'bej'=>['beige','Bej'],'kırmızı'=>['red','Kırmızı'],'kirmizi'=>['red','Kırmızı'],'mavi'=>['blue','Mavi'],'turkuaz'=>['turquoise','Turkuaz'],'yeşil'=>['green','Yeşil'],'yesil'=>['green','Yeşil'],'sarı'=>['yellow','Sarı'],'sari'=>['yellow','Sarı'],'turuncu'=>['orange','Turuncu'],'gri'=>['gray','Gri']] as $n=>[$c,$label]) if(str_contains($l,$n)){ $s['color']=$c;$s['color_label']=$label;$s['mockup_sent']=false;break; }
  if(preg_match('/\b([1-9][0-9]{0,4})\s*(?:adet|tane)\b/u',$l,$m)){
   $s['quantity']=min(50000,(int)$m[1]);$s['mockup_sent']=false;
  } elseif(($s['product']??null)&&!($s['quantity']??null)&&preg_match('/^\s*([1-9][0-9]{0,4})\s*$/u',$l,$m)){
   $s['quantity']=min(50000,(int)$m[1]);$s['mockup_sent']=false;
  } elseif(($s['product']??null)&&!($s['quantity']??null)&&preg_match('/(?:^|\s)([1-9][0-9]{0,4})(?:\s|$)/u',$l,$m)){
   $s['quantity']=min(50000,(int)$m[1]);$s['mockup_sent']=false;
  }
  $p=[]; foreach(['ön sol göğüs'=>'left_chest','on sol gogus'=>'left_chest','sol göğüs'=>'left_chest','sol gogus'=>'left_chest','ön sağ göğüs'=>'right_chest','on sag gogus'=>'right_chest','sağ göğüs'=>'right_chest','sag gogus'=>'right_chest','ön orta'=>'front_center','on orta'=>'front_center','ön büyük'=>'front_large','on buyuk'=>'front_large','arka büyük'=>'back_large','arka buyuk'=>'back_large','sırt'=>'back_large','sirt'=>'back_large','arkada'=>'back_large','sağ kol'=>'right_sleeve','sag kol'=>'right_sleeve','sol kol'=>'left_sleeve'] as $n=>$c) if(str_contains($l,$n)) $p[]=$c;
  if($p){ $s['positions']=array_values(array_unique($p));$s['mockup_sent']=false; }
  if(($s['mockup_sent']??false)&&preg_match('/\b(onaylıyorum|onayliyorum|uygun)\b/u',$l)) $s['approved']=true;
  return $s;
 }
 public function ready(array $s): bool { return ($s['product']??null)&&in_array(($s['color']??null),['black','white'],true)&&($s['quantity']??null)&&!empty($s['positions'])&&($s['logo_received']??false)&&!($s['mockup_sent']??false); }
 public function next(array $s,string $text): string {
  if(!($s['product']??null)) return preg_match('/^\s*(merhaba|selam|selamlar|iyi günler|iyi gunler|hello)\b/u',Str::lower($text))?"Merhaba 👋\n\nİstanbul Tişört Baskı'ya hoş geldiniz. Baskılı tişört siparişinizi birlikte hazırlayalım. *Regular, oversize veya polo yaka* mı düşünüyorsunuz?":'Nasıl bir tişört düşünüyorsunuz: *regular, oversize veya polo yaka*?';
  if(!($s['color']??null)&&!($s['quantity']??null)) return 'Tabii, *'.$s['product_label'].'* ile ilerleyelim. Hangi renk ve kaç adet düşünüyorsunuz?';
  if(!($s['color']??null)) return 'Tamamdır. Tişört hangi renk olsun?';
  if(!($s['quantity']??null)) return 'Tamamdır. Kaç adet düşünüyorsunuz?';
  if(empty($s['positions'])) return 'Baskıyı nereye uygulayalım? Örneğin *ön sol göğüs, ön orta, ön büyük veya arka büyük* yazabilirsiniz.';
  if(!($s['logo_received']??false)) return 'Sipariş detaylarını aldım ✓ Şimdi baskıda kullanacağınız logo veya görseli gönderebilir misiniz?';
  if(($s['mockup_sent']??false)&&!($s['approved']??false)) return 'Önizleme uygunsa *Onaylıyorum* yazabilirsiniz. Değişiklik isterseniz neyi değiştireceğimizi yazmanız yeterli.';
  return '';
 }
 public function faq(string $text): ?string { $l=Str::lower(trim($text)); if(str_contains($l,'kumaş')||str_contains($l,'kumas')) return 'Tişörtlerimizde *30/1 süprem penye, %100 pamuk, yaklaşık 150–160 gr/m²* kumaş kullanıyoruz.'; if(str_contains($l,'minimum')||str_contains($l,'en az kaç')||str_contains($l,'en az kac')) return 'Tişörtü biz üretiyorsak minimum adet yok; *1 adet* de hazırlayabiliriz. Kendi tişörtünüze baskıda minimum *30 adet* çalışıyoruz.'; if(str_contains($l,'numune')) return 'Evet, *1 adet numune* hazırlama seçeneğimiz bulunuyor.'; return null; }
 public function asksColors(string $text): bool { $l=Str::lower(trim($text)); foreach(['hangi renk','renkleriniz','renk seçenek','renk secenek','kartela','renk katalo'] as $n) if(str_contains($l,$n)) return true; return false; }
 public function restart(string $text): bool { $l=Str::lower(trim($text)); return str_contains($l,'yeniden başla')||str_contains($l,'yeniden basla')||str_contains($l,'sıfırla')||str_contains($l,'sifirla'); }
 public function label(string $p): string { return match($p){'left_chest'=>'Ön sol göğüs','right_chest'=>'Ön sağ göğüs','front_center'=>'Ön orta','front_large'=>'Ön büyük','back_large'=>'Arka büyük','left_sleeve'=>'Sol kol','right_sleeve'=>'Sağ kol',default=>$p}; }
}
