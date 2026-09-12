<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $source = DB::table('ai_bots')->where('id', 51)->where('user_id', 46)->first();
            $target = DB::table('ai_bots')->where('id', 53)->where('user_id', 47)->lockForUpdate()->first();
            if (! $source || ! $target || $source->company_name !== 'İstanbul Tişört Baskı' || $target->company_name !== 'Bekir Tekstil') {
                throw new RuntimeException('Textile source/target identity mismatch; no settings changed.');
            }
            $prompt = str_replace('İstanbul Tişört Baskı', 'Bekir Tekstil', $source->system_prompt);
            $prompt = preg_replace('/^- ürün türü:.*$/mu', '- ürün türü: yalnızca sıfır yaka tişört veya polo yaka tişört', $prompt);
            $prompt = preg_replace('/^- model\/kalıp:.*$/mu', '- model/yaka: sıfır yaka veya polo yaka', $prompt);
            $prompt = str_ireplace(['oversize', 'regular'], ['sıfır yaka', 'sıfır yaka'], $prompt);
            $rules = <<<'RULES'
BEKİR TEKSTİL ÇALIŞMA KURALLARI
Yalnız sıfır yaka tişört ve polo yaka tişört sunulur. Başka ürün, kalıp veya yedi çeşit kalite seçeneği sunma.
Kullanım amacı sorulmaz. Model, adet, renk, logo ve baskı konumu konuşmadan alınır; verilmiş bilgi tekrar sorulmaz.
Müşteri bilgileri hangi sırada verdiyse kabul et; tek mesajdaki tüm detayları koru ve yalnız eksik olan tek bilgiyi sor.
Logo, model, renk ve baskı konumu tamamlanınca gerçek baskı önizlemesi otomatik hazırlanıp WhatsApp'tan gönderilir. Ek hazırlama komutu, onay, beden, ölçü veya adet bekleyerek önizlemeyi geciktirme.
Logo orijinal haliyle korunur; aynı render ve gönderim altyapısı kullanılır. Görsel gerçekten gönderilmeden gönderdim deme.
Kısa, doğal, profesyonel konuş; serbest sorulara doğrudan cevap ver. Görselden sonra onay ve sipariş akışına devam et.
Firma adı, WhatsApp bağlantısı ve ödeme bilgileri Bekir'e aittir. Başka firmanın IBAN, telefon, adres, fiyat, stok, teslimat veya minimum adetini Bekir'in bilgisi gibi kullanma.
Doğrulanmış Bekir fiyatı yoksa rakam uydurma; sipariş detaylarına göre net fiyatın iletileceğini söyle. Ödeme linki uydurma.
Müşteri yazmadan otomatik takip mesajı gönderme.
RULES;
            $prompt .= "\n\n".$rules;
            DB::table('ai_bots')->where('id', 53)->where('user_id', 47)->update([
                'system_prompt' => $prompt,
                'company_rules' => $rules,
                'company_description' => 'Sıfır yaka ve polo yaka tişört üzerine logo/baskı ve sipariş hizmeti.',
                'role' => $source->role,
                'openai_model' => $source->openai_model,
                'business_sector' => 'textile',
                'lead_scoring_profile' => $source->lead_scoring_profile,
                'follow_up_enabled' => false,
                'second_follow_up_enabled' => false,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Restore only bot 53 from the deployment backup if an operator requests rollback.
    }
};
