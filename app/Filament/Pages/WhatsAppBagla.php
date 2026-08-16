<?php

namespace App\Filament\Resources\AiBots\Pages;

use App\Filament\Resources\AiBots\AiBotResource;
use App\Services\OrganizationAccessService;
use App\Services\WhatsAppService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppBagla extends Page
{
    use InteractsWithRecord;

    protected static string $resource =
        AiBotResource::class;

    protected string $view =
        'filament.resources.ai-bots.pages.whatsapp-bagla';

    /*
    |--------------------------------------------------------------------------
    | YETKİ
    |--------------------------------------------------------------------------
    */

    protected function accessService(): OrganizationAccessService
    {
        return app(
            OrganizationAccessService::class
        );
    }

    protected function canManageWhatsApp(): bool
    {
        $role =
            $this->accessService()
                ->currentRole();

        return in_array(
            $role,
            [
                'admin',
                'owner',
                'manager',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAYFA AÇILIŞI
    |--------------------------------------------------------------------------
    |
    | Yetki kontrolü resolveRecord işleminden önce yapılır.
    | Resource query ayrıca başka işletmenin bot ID'sine erişimi engeller.
    |
    */

    public function mount(
        int|string $record,
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canManageWhatsApp()) {
            abort(403);
        }

        $this->record =
            $this->resolveRecord(
                $record
            );

        $this->whatsappHazirla(
            $whatsAppService
        );
    }

    public function getTitle(): string
    {
        return 'WhatsApp Bağlantısı';
    }

    /*
    |--------------------------------------------------------------------------
    | DURUMU YENİLE
    |--------------------------------------------------------------------------
    */

    public function baglantiyiYenile(
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canManageWhatsApp()) {
            abort(403);
        }

        $this->whatsappHazirla(
            $whatsAppService
        );
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP BAĞLANTISINI HAZIRLA
    |--------------------------------------------------------------------------
    */

    private function whatsappHazirla(
        WhatsAppService $whatsAppService
    ): void {
        if (! $this->canManageWhatsApp()) {
            abort(403);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | KAYDI GÜNCELLE
            |--------------------------------------------------------------------------
            */

            $this->record->refresh();

            /*
            |--------------------------------------------------------------------------
            | INSTANCE YOKSA OTOMATİK OLUŞTUR
            |--------------------------------------------------------------------------
            */

            if (
                blank(
                    $this->record
                        ->whatsapp_instance
                )
            ) {
                $firmaAdi =
                    Str::slug(
                        $this->record
                            ->company_name
                        ?: $this->record
                            ->name
                    );

                $instanceName =
                    trim(
                        $firmaAdi
                        .'-'
                        .$this->record->id,
                        '-'
                    );

                /*
                |--------------------------------------------------------------------------
                | EVOLUTION INSTANCE OLUŞTUR
                |--------------------------------------------------------------------------
                */

                $response =
                    $whatsAppService
                        ->createInstance(
                            $instanceName
                        );

                /*
                |--------------------------------------------------------------------------
                | İLK QR KODU AL
                |--------------------------------------------------------------------------
                */

                $qrCode =
                    data_get(
                        $response,
                        'qrcode.base64'
                    )
                    ?? data_get(
                        $response,
                        'qrcode.code'
                    )
                    ?? data_get(
                        $response,
                        'base64'
                    )
                    ?? data_get(
                        $response,
                        'code'
                    );

                /*
                |--------------------------------------------------------------------------
                | BOT KAYDINI GÜNCELLE
                |--------------------------------------------------------------------------
                */

                $this->record->update([
                    'whatsapp_instance' =>
                        $instanceName,

                    'whatsapp_status' =>
                        'connecting',

                    'whatsapp_qr' =>
                        is_string(
                            $qrCode
                        )
                            ? $qrCode
                            : null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | WEBHOOK'U OTOMATİK KUR
                |--------------------------------------------------------------------------
                */

                $webhookUrl =
                    rtrim(
                        (string) config(
                            'app.url'
                        ),
                        '/'
                    )
                    .'/api/whatsapp/webhook';

                $whatsAppService
                    ->setWebhook(
                        $instanceName,
                        $webhookUrl
                    );

                $this->record->refresh();
            }

            /*
            |--------------------------------------------------------------------------
            | INSTANCE VARSA WEBHOOK'U GARANTİLE
            |--------------------------------------------------------------------------
            */

            if (
                filled(
                    $this->record
                        ->whatsapp_instance
                )
            ) {
                $webhookUrl =
                    rtrim(
                        (string) config(
                            'app.url'
                        ),
                        '/'
                    )
                    .'/api/whatsapp/webhook';

                $whatsAppService
                    ->setWebhook(
                        $this->record
                            ->whatsapp_instance,
                        $webhookUrl
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | BAĞLANTI DURUMUNU KONTROL ET
            |--------------------------------------------------------------------------
            */

            $state =
                $whatsAppService
                    ->connectionState(
                        $this->record
                            ->whatsapp_instance
                    );

            /*
            |--------------------------------------------------------------------------
            | WHATSAPP ZATEN BAĞLI
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $state,
                    [
                        'open',
                        'connected',
                    ],
                    true
                )
            ) {
                $this->record->update([
                    'whatsapp_status' =>
                        'connected',

                    'whatsapp_qr' =>
                        null,
                ]);

                $this->record->refresh();

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | BAĞLI DEĞİLSE QR KOD AL
            |--------------------------------------------------------------------------
            */

            $qrCode =
                $whatsAppService
                    ->getQrCode(
                        $this->record
                            ->whatsapp_instance
                    );

            $this->record->update([
                'whatsapp_status' =>
                    'connecting',

                'whatsapp_qr' =>
                    $qrCode,
            ]);

            $this->record->refresh();

        } catch (Throwable $exception) {

            report($exception);

            Notification::make()
                ->title(
                    'WhatsApp bağlantısı hazırlanamadı'
                )
                ->body(
                    $exception->getMessage()
                )
                ->danger()
                ->persistent()
                ->send();
        }
    }
}