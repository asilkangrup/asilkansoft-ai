<?php

namespace App\Console\Commands;

use App\Models\OutreachLead;
use App\Services\WaiLeadDemoService;
use App\Services\WaiSalesOutreachService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendWaiLeadDemo extends Command
{
    protected $signature = 'wai:send-lead-demo {phone}';

    protected $description = 'Create and send a sector-specific WAI demo to an outreach lead';

    public function handle(
        WaiLeadDemoService $demoService,
        WaiSalesOutreachService $salesService,
        WhatsAppService $whatsAppService,
    ): int {
        $digits = preg_replace('/\D+/', '', (string) $this->argument('phone')) ?: '';
        if (str_starts_with($digits, '0')) {
            $digits = '9'.$digits;
        }
        if (str_starts_with($digits, '5')) {
            $digits = '90'.$digits;
        }

        $phone = '+'.$digits;
        $lead = OutreachLead::query()
            ->where('user_id', 44)
            ->where('phone_e164', $phone)
            ->first();

        if (! $lead) {
            $this->error('Lead not found: '.$phone);
            return self::FAILURE;
        }

        $sector = $salesService->sectorFor($lead);
        $description = $this->descriptionFor($lead->company_name, $sector);

        $demo = $demoService->create([
            'company_name' => $lead->company_name,
            'company_description' => $description,
            'role' => 'support',
        ]);

        if (($demo['status'] ?? null) !== 'created') {
            $this->error('Demo could not be created.');
            return self::FAILURE;
        }

        $url = (string) $demo['url'];
        $message = implode("\n", [
            'Size özel test yapay zekasını hazırladım ✅',
            '',
            'Bu demo şu an yalnızca '.$lead->company_name.' ve sektörünüzü biliyor. Buna rağmen sektörünüze uygun gerçek bir çalışan gibi müşterilerinizi karşılayıp taleplerini profesyonel şekilde yönetebilir.',
            '',
            'Canlı kurulumda fiyatlarınızı, ürün/hizmetlerinizi, çalışma saatlerinizi, şirket kurallarınızı, kampanyalarınızı ve istediğiniz yönlendirme akışlarını tamamen size özel tanımlıyoruz.',
            '',
            'Test linkiniz:',
            $url,
            '',
            'Müşterinizmiş gibi birkaç farklı soru yazarak deneyebilirsiniz. Beğenirseniz 3 gün ücretsiz canlı kullanım ve kurulum desteği sağlayabiliriz.',
        ]);

        $whatsAppService->sendText(
            'wai-sales-48-clean',
            $digits,
            $message,
        );

        $this->info('Demo sent: '.$url);

        return self::SUCCESS;
    }

    private function descriptionFor(string $company, string $sector): string
    {
        return match ($sector) {
            'Teknik Servis / Tamir' => $company.' için profesyonel teknik servis ve tesisat demo asistanı. Müşterinin arıza türünü, konumunu ve aciliyetini doğal şekilde anlamalı; gerekiyorsa fotoğraf istemeli; servis, randevu veya teklif aşamasına yönlendirmeli. Bilinmeyen fiyatları uydurmamalı.',
            'Oto Servis / Otomotiv' => $company.' için oto servis demo asistanı. Araç marka-model, arıza, bakım ve randevu ihtiyacını doğal şekilde toplamalı ve uygun servis adımına yönlendirmeli. Bilinmeyen fiyatları uydurmamalı.',
            'Mobilya / Ev Dekorasyon' => $company.' için mobilya satış demo asistanı. Ürün türü, ölçü, model, renk ve teslimat ihtiyacını doğal şekilde anlamalı ve teklif aşamasına ilerletmeli. Bilinmeyen fiyatları uydurmamalı.',
            'Güzellik / Estetik' => $company.' için güzellik merkezi demo asistanı. İşlem türü, uygun gün/saat ve randevu ihtiyacını doğal şekilde anlamalı ve randevu aşamasına ilerletmeli. Bilinmeyen fiyatları uydurmamalı.',
            'Emlak' => $company.' için emlak demo asistanı. Bölge, bütçe, gayrimenkul türü ve kriterleri doğal şekilde toplayıp uygun portföy sürecine ilerletmeli.',
            default => $company.' için sektörüne uygun profesyonel WhatsApp demo asistanı. Müşterinin ihtiyacını kısa ve doğal sorularla anlayıp satış, teklif veya randevu aşamasına ilerletmeli; bilinmeyen bilgileri uydurmamalı.',
        };
    }
}
