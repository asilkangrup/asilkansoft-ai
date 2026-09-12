<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $bot = DB::table('ai_bots')->where('id', 53)->where('user_id', 47)->lockForUpdate()->first();
            if (! $bot || $bot->company_name !== 'Bekir Tekstil') {
                throw new RuntimeException('Bekir identity mismatch');
            }
            $rule = "\n\nHAZIR RENKLİ BASKI ÖNİZLEMELERİ: Sıfır yaka, polo yaka, oversize tişört, pamuklu şapka ve polyester şapkanın her birinde siyah, beyaz, lacivert, bordo, bej, kırmızı, mavi, turkuaz, yeşil, sarı, turuncu, pembe, kahverengi, gri ve füme için hazır görsel bulunur. Renk seçimini bu ürünlerde kısıtlama. Tişört ön ve arka baskılarını ayrı fotoğraflar halinde gönder. Şapka baskı önizlemesi ön yüz içindir. Bu görsel kataloğu fiziksel stok veya üretim uygunluğu garantisi değildir; doğrulanmamış stok veya teslimat sözü verme.";
            $updates = ['updated_at' => now()];
            foreach (['system_prompt', 'company_rules'] as $field) {
                $text = (string) $bot->$field;
                if (! str_contains($text, 'HAZIR RENKLİ BASKI ÖNİZLEMELERİ:')) {
                    $updates[$field] = $text.$rule;
                }
            }
            DB::table('ai_bots')->where('id', 53)->where('user_id', 47)->update($updates);
        });
    }

    public function down(): void {}
};
