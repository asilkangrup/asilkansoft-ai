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

    protected string $view =
        'filament.resources.ai-bots.pages.whatsapp-bagla';

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
    ): mixed {
        return $this->baglantiBilgileriniYenile($whatsAppService);
    }

    private function baglantiBilgileriniYenile(
        WhatsAppService $whatsAppService
    ): mixed {
        if (blank($this->record->whatsapp_instance)) {
            return null;
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

                Notification::make()
                    ->title('WhatsApp başarıyla bağlandı')
                    ->body(
                        'Numaranız yapay zekâ sistemine bağlandı.'
                    )
                    ->success()
                    ->send();

                /*
                |--------------------------------------------------------------------------
                | BAĞLANTI TAMAMLANINCA YAPAY ZEKALAR LİSTESİNE DÖN
                |--------------------------------------------------------------------------
                */

                return redirect(
                    AiBotResource::getUrl('index')
                );
            }

            $qrCode = $whatsAppService->getQrCode(
                $this->record->whatsapp_instance
            );

            $this->record->update([
                'whatsapp_status' => 'connecting',
                'whatsapp_qr' => $qrCode,
            ]);

            $this->record->refresh();

            return null;

        } catch (Throwable $exception) {

            report($exception);

            Notification::make()
                ->title('WhatsApp durumu alınamadı')
                ->body(
                    'Bağlantı kontrol edilirken bir sorun oluştu.'
                )
                ->danger()
                ->send();

            return null;
        }
    }
}