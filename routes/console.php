<?php

use App\Models\AiBot;
use App\Models\RealEstateOutboundDelivery;
use App\Services\RealEstateIsolationService;
use App\Services\RealEstateOutboundDeliveryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| ISOLATED EMLAK AI OUTBOUND RECOVERY
|--------------------------------------------------------------------------
|
| An uncertain delivery is never resent automatically. After an operator
| checks WhatsApp/Evolution externally, this command can either confirm that
| the message was already delivered or abandon the delivery. Neither action
| performs a network send.
|
*/

Artisan::command(
    'real-estate:resolve-outbound {id} {resolution : sent|abandoned} {--provider-id=}',
    function (): int {
        $delivery = RealEstateOutboundDelivery::query()
            ->whereKey((int) $this->argument('id'))
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->where('organization_id', RealEstateIsolationService::ORGANIZATION_ID)
            ->where('ai_bot_id', RealEstateIsolationService::BOT_ID)
            ->where('instance', RealEstateIsolationService::INSTANCE)
            ->first();

        if (! $delivery) {
            $this->error('İzole Emlak AI outbound kaydı bulunamadı.');

            return 1;
        }

        $resolution = strtolower(trim((string) $this->argument('resolution')));

        if (! in_array($resolution, ['sent', 'abandoned'], true)) {
            $this->error('resolution yalnızca sent veya abandoned olabilir.');

            return 1;
        }

        if (! in_array($delivery->status, ['sending', 'uncertain'], true)) {
            $this->error('Yalnız sending/uncertain kayıtlar manuel olarak çözülebilir.');

            return 1;
        }

        if ($resolution === 'abandoned') {
            $delivery->forceFill([
                'status' => 'abandoned',
                'last_error' => 'Operatör kontrolü sonrası yeniden gönderilmeden abandoned olarak kapatıldı.',
            ])->save();

            $this->info('Outbound kayıt abandoned olarak kapatıldı; hiçbir WhatsApp mesajı gönderilmedi.');

            return 0;
        }

        $providerId = trim((string) $this->option('provider-id'));

        $delivery->forceFill([
            'status' => 'sent',
            'whatsapp_message_id' => $providerId !== ''
                ? $providerId
                : $delivery->whatsapp_message_id,
            'sent_at' => $delivery->sent_at ?: now(),
            'last_error' => 'Operatör WhatsApp/Evolution kontrolü sonrası teslimatı manuel olarak confirmed sent işaretledi.',
        ])->save();

        $service = app(RealEstateOutboundDeliveryService::class);
        $service->persistAssistantMessage($delivery->fresh());

        $bot = AiBot::query()
            ->whereKey(RealEstateIsolationService::BOT_ID)
            ->where('user_id', RealEstateIsolationService::USER_ID)
            ->firstOrFail();

        $service->consumeTrialOnce($delivery->fresh(), $bot);

        $this->info('Outbound kayıt sent olarak doğrulandı; hiçbir yeni WhatsApp mesajı gönderilmedi.');

        return 0;
    }
)->purpose('Resolve an isolated Emlak AI uncertain outbound without resending it.');

/*
|--------------------------------------------------------------------------
| CRM TAKİP / ÖNCELİKLİ LEAD BİLDİRİMLERİ
|--------------------------------------------------------------------------
*/

Schedule::command(
    'wai:send-follow-up-notifications'
)
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| WAI CRM ALARM MERKEZİ
|--------------------------------------------------------------------------
|
| Kritik CRM sinyallerini sık aralıklarla kontrol eder.
|
*/

Schedule::command(
    'wai:check-crm-alarms'
)
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| WAI GÜNLÜK YÖNETİCİ ÖZETİ
|--------------------------------------------------------------------------
*/

Schedule::command(
    'wai:generate-manager-summaries'
)
    ->dailyAt('08:00')
    ->withoutOverlapping();
