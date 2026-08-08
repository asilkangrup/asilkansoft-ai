<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\WhatsAppService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Siparişi Sil'),
        ];
    }

    protected function afterSave(): void
    {
        try {
            $order = $this->record->fresh();

            if (blank($order->whatsapp_number)) {
                return;
            }

            $aiBot = $order->aiBot;

            if (
                ! $aiBot
                || blank($aiBot->whatsapp_instance)
            ) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | ONAYLANDI
            |--------------------------------------------------------------------------
            */

            if (
                $order->status === 'confirmed'
                && ! $order->confirmed_notification_sent_at
            ) {
                $this->whatsappGonder(
                    $aiBot->whatsapp_instance,
                    $order->whatsapp_number,
                    "✅ Siparişiniz onaylandı.\n\nSiparişiniz firma tarafından onaylandı ve işleme alındı."
                );

                $order->update([
                    'confirmed_notification_sent_at' => now(),
                ]);

                $this->basariBildirimi(
                    'Sipariş onay bildirimi gönderildi'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | HAZIRLANIYOR
            |--------------------------------------------------------------------------
            */

            if (
                $order->status === 'preparing'
                && ! $order->preparing_notification_sent_at
            ) {
                $this->whatsappGonder(
                    $aiBot->whatsapp_instance,
                    $order->whatsapp_number,
                    "📦 Siparişiniz hazırlanıyor.\n\nSiparişiniz özenle hazırlanıyor. Kargoya verildiğinde takip bilgileri sizinle paylaşılacaktır."
                );

                $order->update([
                    'preparing_notification_sent_at' => now(),
                ]);

                $this->basariBildirimi(
                    'Hazırlanıyor bildirimi gönderildi'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | KARGOYA VERİLDİ
            |--------------------------------------------------------------------------
            */

            if ($order->status === 'shipped') {

                if ($order->shipping_notification_sent_at) {
                    Notification::make()
                        ->title('Kargo bildirimi daha önce gönderildi')
                        ->body('Aynı kargo bilgisi tekrar gönderilmedi.')
                        ->info()
                        ->send();

                    return;
                }

                if (
                    blank($order->shipping_company)
                    || blank($order->tracking_number)
                ) {
                    Notification::make()
                        ->title('Kargo bildirimi gönderilemedi')
                        ->body(
                            'Kargo firması veya takip numarası eksik.'
                        )
                        ->warning()
                        ->send();

                    return;
                }

                $message =
                    "🚚 Siparişiniz kargoya verildi.\n\n".
                    "Kargo Firması: {$order->shipping_company}\n".
                    "Takip Numarası: {$order->tracking_number}";

                if (! blank($order->tracking_url)) {
                    $message .=
                        "\nTakip Linki: {$order->tracking_url}";
                }

                $message .=
                    "\n\nSiparişinizin durumunu kargo firması üzerinden takip edebilirsiniz.";

                $this->whatsappGonder(
                    $aiBot->whatsapp_instance,
                    $order->whatsapp_number,
                    $message
                );

                $order->update([
                    'shipping_notification_sent_at' => now(),
                ]);

                $this->basariBildirimi(
                    'Kargo bildirimi gönderildi'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | TESLİM EDİLDİ
            |--------------------------------------------------------------------------
            */

            if (
                $order->status === 'delivered'
                && ! $order->delivered_notification_sent_at
            ) {
                $this->whatsappGonder(
                    $aiBot->whatsapp_instance,
                    $order->whatsapp_number,
                    "✅ Siparişiniz teslim edildi.\n\nBizi tercih ettiğiniz için teşekkür ederiz. Siparişinizle ilgili herhangi bir konuda bize tekrar yazabilirsiniz."
                );

                $order->update([
                    'delivered_notification_sent_at' => now(),
                ]);

                $this->basariBildirimi(
                    'Teslim bildirimi gönderildi'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | İPTAL EDİLDİ
            |--------------------------------------------------------------------------
            */

            if (
                $order->status === 'cancelled'
                && ! $order->cancelled_notification_sent_at
            ) {
                $this->whatsappGonder(
                    $aiBot->whatsapp_instance,
                    $order->whatsapp_number,
                    "❌ Siparişiniz iptal edildi.\n\nSiparişinizle ilgili bilgi almak veya yeniden sipariş oluşturmak isterseniz bize yazabilirsiniz."
                );

                $order->update([
                    'cancelled_notification_sent_at' => now(),
                ]);

                $this->basariBildirimi(
                    'İptal bildirimi gönderildi'
                );

                return;
            }

        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('WhatsApp bildirimi gönderilemedi')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function whatsappGonder(
        string $instanceName,
        string $number,
        string $message
    ): void {
        app(WhatsAppService::class)->sendText(
            instanceName: $instanceName,
            number: $number,
            text: $message,
        );
    }

    private function basariBildirimi(string $title): void
    {
        Notification::make()
            ->title($title)
            ->body('Müşteriye WhatsApp mesajı başarıyla gönderildi.')
            ->success()
            ->send();
    }
}