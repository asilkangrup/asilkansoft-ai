<?php

namespace App\Services;

use App\Models\AiBot;
use App\Models\WaiDemoLead;
use App\Models\WaiDemoMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WaiLeadDemoService
{
    public function create(array $lead): array
    {
        $company = trim((string) ($lead['company_name'] ?? ''));
        $sector = trim((string) ($lead['sector'] ?? ''));
        $description = trim((string) ($lead['company_description'] ?? ''));
        $role = trim((string) ($lead['role'] ?? 'sales')) ?: 'sales';

        if ($company === '') {
            return ['status' => 'waiting'];
        }

        if ($description === '') {
            $description = $this->sectorDescription($company, $sector);
        }

        $token = Str::random(48);
        $payload = [
            'company_name' => $company,
            'sector' => $sector,
            'company_description' => mb_substr($description, 0, 3000),
            'role' => in_array($role, ['sales', 'support', 'assistant', 'technical'], true)
                ? $role
                : 'sales',
            'source' => 'whatsapp_sales',
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays(3)->toIso8601String(),
            'basic_demo' => true,
        ];

        Cache::put(
            'wai_lead_demo:'.$token,
            $payload,
            now()->addDays(3)
        );

        if (Schema::hasTable('wai_demo_leads')) {
            WaiDemoLead::query()->create([
                'token' => $token,
                'company_name' => $company,
                'company_description' => $payload['company_description'],
                'source' => $payload['source'],
            ]);
        }

        return [
            'status' => 'created',
            'token' => $token,
            'url' => url('/demo/lead/'.$token),
            'expires_at' => $payload['expires_at'],
        ];
    }

    private function sectorDescription(string $company, string $sector): string
    {
        $base = match ($sector) {
            'Oto Servis / Otomotiv' => 'Müşteri araç marka-modelini, model yılını, arıza veya yapılacak işlemi söyleyebilir. Yapay zeka gerekli kısa bilgileri sırayla toplar; bakım, arıza, servis ve randevu taleplerini profesyonel biçimde karşılar. Gerçek fiyat veya kampanya bilgisi verilmediyse uydurmaz.',
            'Mobilya / Ev Dekorasyon' => 'Müşterinin aradığı ürün türünü, ölçüyü, modeli, rengi, kullanım alanını ve gerekirse teslimat ihtiyacını doğal sorularla toplar. Teklif hazırlamaya uygun bir talep özeti çıkarır. Gerçek fiyat, stok veya teslim süresi verilmediyse uydurmaz.',
            'Güzellik / Estetik' => 'Müşterinin ilgilendiği işlem veya hizmeti, uygun gün-saat tercihini ve gerekli temel bilgileri doğal şekilde toplar; fiyat ve randevu sorularını karşılar. Gerçek fiyat veya kampanya verilmediyse uydurmaz.',
            'Kuaför / Berber' => 'Hizmet türü, uygun saat ve randevu talebini doğal biçimde alır; müşteriyi randevuya doğru ilerletir. Gerçek fiyat ve müsaitlik verilmediyse uydurmaz.',
            'Emlak' => 'Müşterinin satılık veya kiralık talebini; bölge, bütçe, oda veya metrekare gibi kriterleri doğal sırayla toplar ve uygun portföy sürecine hazırlar. Gerçek portföy veya fiyat bilgisi verilmediyse uydurmaz.',
            'Sağlık / Klinik' => 'Müşterinin ilgilendiği hizmeti ve randevu talebini profesyonel ve ölçülü şekilde karşılar, gerekli temel bilgileri toplar. Tanı koymaz ve verilmemiş fiyat veya tıbbi bilgi uydurmaz.',
            'Teknik Servis / Tamir' => 'Müşterinin cihazını veya problemini, arıza belirtisini ve servis talebini kısa sorularla netleştirir; teklif veya servis kaydı için gerekli bilgileri toplar. Gerçek ücret veya çözüm süresi verilmediyse uydurmaz.',
            'Yeme-İçme / Kafe' => 'Menü, rezervasyon, sipariş ve genel müşteri sorularını doğal biçimde karşılar. Gerçek menü, fiyat veya kampanya verilmediyse uydurmaz.',
            'Fotoğraf / Organizasyon' => 'Etkinlik türü, tarih, kişi sayısı veya paket beklentisini doğal sorularla toplar ve teklif talebini hazırlar. Gerçek paket fiyatı verilmediyse uydurmaz.',
            'Temizlik / Ev Hizmetleri' => 'Konum, hizmet türü, işin kapsamı ve istenen tarihi kısa sorularla toplar; teklif veya randevu aşamasına hazırlar. Gerçek fiyat verilmediyse uydurmaz.',
            default => 'Müşteriyi profesyonel şekilde karşılar, ihtiyacını kısa sorularla anlar, sık sorulan soruları yanıtlar ve satış veya randevu aşamasına ilerletir. Gerçek fiyat, stok, kampanya veya şirket politikası verilmediyse uydurmaz.',
        };

        return "{$company} için hazırlanmış temel WAI demosu. Sektör: ".($sector !== '' ? $sector : 'Yerel İşletme').". {$base} Bu demo yalnızca işletme adı ve sektör bilgisiyle hazırlanmıştır; gerçek kurulumda fiyatlar, ürün/hizmetler, çalışma saatleri, şirket kuralları, kampanyalar ve yönlendirme akışları tamamen işletmeye özel tanımlanacaktır.";
    }

    public function get(string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        $payload = Cache::get('wai_lead_demo:'.$token);

        return is_array($payload) ? $payload : null;
    }

    public function markOpened(string $token): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        WaiDemoLead::query()->where('token', $token)->whereNull('first_opened_at')->update(['first_opened_at' => now()]);
    }

    public function recordTestConversation(string $token, string $userMessage, string $assistantMessage): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }

        $lead = WaiDemoLead::query()->where('token', $token)->first();
        if (! $lead) {
            return;
        }

        if (Schema::hasTable('wai_demo_messages')) {
            WaiDemoMessage::query()->create(['wai_demo_lead_id' => $lead->id, 'role' => 'user', 'message' => mb_substr(trim($userMessage), 0, 5000)]);
            WaiDemoMessage::query()->create(['wai_demo_lead_id' => $lead->id, 'role' => 'assistant', 'message' => mb_substr(trim($assistantMessage), 0, 10000)]);
        }

        $lead->increment('test_message_count');
        $lead->forceFill(['first_opened_at' => $lead->first_opened_at ?: now(), 'last_tested_at' => now()])->save();
    }

    public function markTestMessage(string $token): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }
        $lead = WaiDemoLead::query()->where('token', $token)->first();
        if (! $lead) {
            return;
        }
        $lead->increment('test_message_count');
        $lead->forceFill(['first_opened_at' => $lead->first_opened_at ?: now(), 'last_tested_at' => now()])->save();
    }

    public function markConnectStarted(string $token, ?AiBot $bot = null): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }
        WaiDemoLead::query()->where('token', $token)->update(['whatsapp_connect_started_at' => now(), 'temporary_bot_id' => $bot?->id, 'whatsapp_instance' => $bot?->whatsapp_instance]);
    }

    public function markConnected(string $token, ?AiBot $bot = null): void
    {
        if (! Schema::hasTable('wai_demo_leads')) {
            return;
        }
        $lead = WaiDemoLead::query()->where('token', $token)->first();
        if (! $lead) {
            return;
        }
        $lead->forceFill([
            'whatsapp_connect_started_at' => $lead->whatsapp_connect_started_at ?: now(),
            'whatsapp_connected_at' => $lead->whatsapp_connected_at ?: now(),
            'temporary_bot_id' => $bot?->id ?: $lead->temporary_bot_id,
            'whatsapp_instance' => $bot?->whatsapp_instance ?: $lead->whatsapp_instance,
        ])->save();
    }
}
