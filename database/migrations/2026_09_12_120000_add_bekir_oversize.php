<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        DB::transaction(function (): void {
            $bot=DB::table('ai_bots')->where('id',53)->where('user_id',47)->lockForUpdate()->first();
            if (!$bot || $bot->company_name !== 'Bekir Tekstil') throw new RuntimeException('Bekir identity mismatch');
            $from=['Yalnız sıfır yaka tişört ve polo yaka tişört sunulur.','yalnızca sıfır yaka tişört veya polo yaka tişört','- model/yaka: sıfır yaka veya polo yaka','Sıfır yaka ve polo yaka tişört üzerine'];
            $to=['Sıfır yaka tişört, polo yaka tişört ve oversize tişört sunulur.','sıfır yaka tişört, polo yaka tişört veya oversize tişört','- model/yaka: sıfır yaka, polo yaka veya oversize','Sıfır yaka, polo yaka ve oversize tişört üzerine'];
            $data=[];
            foreach(['system_prompt','company_rules','company_description'] as $field) $data[$field]=str_replace($from,$to,(string)$bot->$field);
            $data['updated_at']=now();
            DB::table('ai_bots')->where('id',53)->where('user_id',47)->update($data);
        });
    }
    public function down(): void {}
};
