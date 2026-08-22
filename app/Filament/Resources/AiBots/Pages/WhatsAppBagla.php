<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppBagla extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AiBotResource::class;

    protected string $view = 'filament.resources.ai-bots.pages.whatsapp-bagla';

    public function mount(int|string $record, WhatsAppService $whatsAppService): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            AiBotResource::canEdit($this->record),
            403
        );

        $this->whatsappHazirla($whatsAppService);
    }

    public function getTitle(): string
    {
        return 'WhatsApp Bağlantısı';
    }

    public function baglantiyiYenile(WhatsAppService $whatsAppService): void
    {
        abort_unless(
            AiBotResource::canEdit($this->record),
            403
        );

        $this->whatsappHazirla($whatsAppService);
    }

    private function whatsappHazirla(WhatsAppService $whatsAppService): void
    {
        abort_unless(
            AiBotResource::canEdit($this->record),
            403
        );

        try {
            $this->record->refresh();

            if (blank($this->record->whatsapp_instance)) {
                $firmaAdi = Str::slug(
                    $this->record->company_name ?: $this->record->name
                );

                $instanceName = trim(
                    $firmaAdi . '-' . $this->record->id,
                    '-'
                );

                $response = $whatsAppService->createInstance($instanceName);

                $qrCode = data_get($response, 'qrcode.base64')
                    ?? data_get($response, 'qrcode.code')
                    ?? data_get($response, 'base64')
                    ?? data_get($response, 'code');

                $this->record->update([
                    'whatsapp_instance' => $instanceName,
                    'whatsapp_status' => 'connecting',
                    'whatsapp_qr' => is_string($qrCode) ? $qrCode : null,
                ]);

                $webhookUrl = rtrim((string) config('app.url'), '/')
                    . '/api/whatsapp/webhook';

                $whatsAppService->setWebhook(
                    $instanceName,
                    $webhookUrl
                );

                $this->record->refresh();
            }

            if (filled($this->record->whatsapp_instance)) {
                $webhookUrl = rtrim((string) config('app.url'), '/')
                    . '/api/whatsapp/webhook';

                $whatsAppService->setWebhook(
                    $this->record->whatsapp_instance,
                    $webhookUrl
                );
            }

            $state = $whatsAppService->connectionState(
                $this->record->whatsapp_instance
            );

            if (in_array($state, ['open', 'connected'], true)) {
                $this->record->update([
                    'whatsapp_status' => 'connected',
                    'whatsapp_qr' => null,
                ]);

                $this->record->refresh();

                return;
            }

            $qrCode = $whatsAppService->getQrCode(
                $this->record->whatsapp_instance
            );

            $this->record->update([
                'whatsapp_status' => 'connecting',
                'whatsapp_qr' => $qrCode,
            ]);

            $this->record->refresh();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('WhatsApp bağlantısı hazırlanamadı')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
