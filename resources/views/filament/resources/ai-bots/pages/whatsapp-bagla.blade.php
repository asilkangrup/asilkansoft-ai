<x-filament-panels::page>

<style>
    .wa-wrap {
        max-width: 850px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .wa-header {
        padding: 24px;

        border: 1px solid #e5e7eb;
        border-radius: 22px;

        background: #fff;

        box-shadow:
            0 8px 25px rgba(15,23,42,.06);
    }

    .wa-header-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .wa-title {
        margin: 0;
        color: #111827;
        font-size: 22px;
        font-weight: 800;
    }

    .wa-description {
        margin: 7px 0 0;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.6;
    }

    .refresh-button {
        padding: 10px 15px;

        border: 1px solid #d1d5db;
        border-radius: 11px;

        background: #fff;
        color: #374151;

        font-size: 12px;
        font-weight: 800;

        cursor: pointer;
    }

    .wa-card {
        padding: 32px;

        border: 1px solid #e5e7eb;
        border-radius: 22px;

        background: #fff;

        text-align: center;

        box-shadow:
            0 8px 25px rgba(15,23,42,.06);
    }

    .status-icon {
        font-size: 50px;
    }

    .status-title {
        margin-top: 13px;
        color: #111827;
        font-size: 21px;
        font-weight: 800;
    }

    .status-title.connected {
        color: #16a34a;
    }

    .status-text {
        margin: 8px auto 0;
        max-width: 520px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.7;
    }

    .qr-box {
        width: fit-content;
        margin: 24px auto 0;

        padding: 18px;

        border: 1px solid #e5e7eb;
        border-radius: 18px;

        background: #fff;

        box-shadow:
            0 8px 25px rgba(15,23,42,.06);
    }

    .qr-image {
        display: block;
        width: 300px;
        height: 300px;
        object-fit: contain;
    }

    .instructions {
        margin-top: 20px;

        padding: 15px;

        border-radius: 14px;

        background: #f0fdf4;
        color: #166534;

        font-size: 13px;
        font-weight: 700;
        line-height: 1.7;
    }

    .wa-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;

        margin-top: 24px;
    }

    .connected-button {
        display: flex;
        align-items: center;
        justify-content: center;

        min-height: 50px;

        border: 0;
        border-radius: 13px;

        background: #16a34a;
        color: #fff !important;

        text-decoration: none !important;

        font-size: 14px;
        font-weight: 800;

        transition: .2s ease;
    }

    .connected-button:hover {
        background: #15803d;
        transform: translateY(-1px);
    }

    .secondary-button {
        display: flex;
        align-items: center;
        justify-content: center;

        min-height: 46px;

        border: 1px solid #d1d5db;
        border-radius: 13px;

        background: #fff;
        color: #374151 !important;

        text-decoration: none !important;

        font-size: 13px;
        font-weight: 800;
    }

    @media (max-width:600px) {

        .wa-header-top {
            flex-direction: column;
            align-items: stretch;
        }

        .refresh-button {
            width: 100%;
        }

        .wa-card {
            padding: 22px;
        }

        .qr-image {
            width: 240px;
            height: 240px;
        }
    }
</style>


<div class="wa-wrap">

    <div class="wa-header">

        <div class="wa-header-top">

            <div>

                <h2 class="wa-title">
                    WhatsApp Bağlantısı
                </h2>

                <p class="wa-description">
                    <strong>
                        {{ $record->name }}
                    </strong>

                    yapay zekânızı WhatsApp hesabınıza bağlayın.
                </p>

            </div>


            <button
                type="button"
                wire:click="baglantiyiYenile"
                wire:loading.attr="disabled"
                wire:target="baglantiyiYenile"
                class="refresh-button"
            >
                ↻ Durumu Yenile
            </button>

        </div>

    </div>


    <div class="wa-card">

        @if ($record->whatsapp_status === 'connected')

            <div class="status-icon">
                ✅
            </div>

            <div class="status-title connected">
                WhatsApp Başarıyla Bağlandı
            </div>

            <p class="status-text">
                Numaranız yapay zekâ sistemine bağlı.
                Yapay zekânız artık WhatsApp üzerinden
                müşterilerinize cevap verebilir.
            </p>


            <div class="wa-actions">

                <a
                    href="{{ \App\Filament\Resources\AiBots\AiBotResource::getUrl('index') }}"
                    class="connected-button"
                >
                    ✓ Yapay Zekâlarıma Git →
                </a>

            </div>


        @elseif (! empty($record->whatsapp_qr))

            <div class="status-icon">
                📱
            </div>

            <div class="status-title">
                QR Kodu Telefonunuzdan Okutun
            </div>

            <p class="status-text">
                WhatsApp hesabınızı bağlamak için
                telefonunuzdaki WhatsApp uygulamasını kullanın.
            </p>


            <div class="qr-box">

                <img
                    src="{{ $record->whatsapp_qr }}"
                    alt="WhatsApp QR Kod"
                    class="qr-image"
                >

            </div>


            <div class="instructions">

                WhatsApp
                →
                Ayarlar
                →
                Bağlı Cihazlar
                →
                Cihaz Bağla

            </div>


            <div class="wa-actions">

                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    wire:target="baglantiyiYenile"
                    class="secondary-button"
                >
                    ↻ Bağlantı Durumunu Kontrol Et
                </button>


                <a
                    href="{{ \App\Filament\Resources\AiBots\AiBotResource::getUrl('index') }}"
                    class="connected-button"
                >
                    ✓ WhatsApp'ı Bağladım
                </a>

            </div>


        @else

            <div class="status-icon">
                ⏳
            </div>

            <div class="status-title">
                WhatsApp Bağlantısı Hazırlanıyor
            </div>

            <p class="status-text">
                QR kod hazırlanıyor.
                Birkaç saniye sonra Durumu Yenile butonuna basın.
            </p>


            <div class="wa-actions">

                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    wire:target="baglantiyiYenile"
                    class="connected-button"
                >
                    ↻ QR Kodu Getir
                </button>

            </div>

        @endif

    </div>

</div>

</x-filament-panels::page>