<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Throwable;

class WhatsAppBagla extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AiBotResource::class;

    protected string $view = 'filament.resources.ai-bots.pages.whatsapp-bagla';

    public function mount(
        int|string $record,
        WhatsAppService $whatsAppService
    ): void {
        $this->record = $this->resolveRecord($record);

        $this->baglantiBilgileriniYenile($whatsAppService);
    }

    public function getTitle(): string
    {
        return 'WhatsApp Bağlantısı';
    }

    public function baglantiyiYenile(
        WhatsAppService $whatsAppService
    ): void {
        $this->baglantiBilgileriniYenile($whatsAppService);

        Notification::make()
            ->title('WhatsApp durumu güncellendi')
            ->success()
            ->send();
    }

    private function baglantiBilgileriniYenile(
        WhatsAppService $whatsAppService
    ): void {
        if (blank($this->record->whatsapp_instance)) {
            return;
        }

        try {
            $state = $whatsAppService->connectionState(
                $this->record->whatsapp_instance
            );

            if (in_array($state, ['open', 'connected'], true)) {
                $this->record->update([
                    'whatsapp_status' => 'connected',
                    'whatsapp_qr' => null,
                ]);
            } else {
                $qrCode = $whatsAppService->getQrCode(
                    $this->record->whatsapp_instance
                );

                $this->record->update([
                    'whatsapp_status' => 'connecting',
                    'whatsapp_qr' => $qrCode,
                ]);
            }

            $this->record->refresh();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('WhatsApp durumu alınamadı')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}