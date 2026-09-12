<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  DB::transaction(function():void {
   $b=DB::table('ai_bots')->where('id',53)->where('user_id',47)->lockForUpdate()->first();
   if(!$b || $b->company_name!=='Bekir Tekstil')throw new RuntimeException('Bekir identity mismatch');
   $from=['Sıfır yaka tişört, polo yaka tişört ve oversize tişört sunulur.','sıfır yaka tişört, polo yaka tişört veya oversize tişört','- model/yaka: sıfır yaka, polo yaka veya oversize','Sıfır yaka, polo yaka ve oversize tişört üzerine'];
   $to=['Sıfır yaka tişört, polo yaka tişört, oversize tişört ve şapka sunulur.','sıfır yaka tişört, polo yaka tişört, oversize tişört veya şapka','- model/yaka: sıfır yaka, polo yaka, oversize veya şapka','Sıfır yaka, polo yaka, oversize tişört ve şapka üzerine'];
   $d=[];foreach(['system_prompt','company_rules','company_description'] as $f)$d[$f]=str_replace($from,$to,(string)$b->$f);
   $d['updated_at']=now();DB::table('ai_bots')->where('id',53)->where('user_id',47)->update($d);
  });
 }
 public function down():void {}
};
