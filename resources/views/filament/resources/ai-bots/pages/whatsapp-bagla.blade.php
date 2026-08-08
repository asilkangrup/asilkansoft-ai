<x-filament-panels::page>

<style>
    .wa-wrap {
        max-width: 980px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .wa-hero {
        padding: 28px;
        border-radius: 24px;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(34, 197, 94, .35), transparent 32%),
            linear-gradient(135deg, #111827 0%, #064e3b 55%, #166534 100%);
        box-shadow: 0 18px 42px rgba(15, 23, 42, .15);
    }

    .wa-hero-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
    }

    .wa-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 11px;
        border-radius: 999px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.15);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
    }

    .wa-title {
        margin: 13px 0 0;
        font-size: 28px;
        line-height: 1.2;
        font-weight: 800;
    }

    .wa-subtitle {
        margin: 9px 0 0;
        max-width: 620px;
        color: rgba(255,255,255,.76);
        font-size: 14px;
        line-height: 1.7;
    }

    .wa-refresh {
        flex-shrink: 0;
        padding: 11px 16px;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 11px;
        background: rgba(255,255,255,.1);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .2s ease;
    }

    .wa-refresh:hover {
        background: rgba(255,255,255,.16);
    }

    .wa-refresh:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .wa-card {
        padding: 32px;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        background: #fff;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        text-align: center;
    }

    .wa-status-icon {
        display: flex;
        width: 76px;
        height: 76px;
        margin: 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 22px;
        background: #dcfce7;
        font-size: 36px;
    }

    .wa-status-title {
        margin: 18px 0 0;
        color: #166534;
        font-size: 24px;
        font-weight: 800;
    }

    .wa-status-text {
        margin: 8px auto 0;
        max-width: 560px;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.7;
    }

    .wa-qr-title {
        margin: 0;
        color: #111827;
        font-size: 20px;
        font-weight: 800;
    }

    .wa-qr-box {
        width: fit-content;
        margin: 22px auto 0;
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 6px 20px rgba(15, 23, 42, .06);
    }

    .wa-qr-box img {
        display: block;
        width: 310px;
        height: 310px;
        object-fit: contain;
    }

    .wa-help {
        margin-top: 18px;
        color: #374151;
        font-size: 14px;
        font-weight: 700;
    }

    .wa-help-small {
        margin-top: 7px;
        color: #9ca3af;
        font-size: 12px;
    }

    .wa-preparing-icon {
        display: flex;
        width: 76px;
        height: 76px;
        margin: 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 22px;
        background: #eff6ff;
        font-size: 36px;
    }

    .wa-preparing-title {
        margin: 18px 0 0;
        color: #111827;
        font-size: 21px;
        font-weight: 800;
    }

    .wa-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-top: 24px;
        flex-wrap: wrap;
    }

    .wa-primary {
        padding: 12px 18px;
        border: 0;
        border-radius: 11px;
        background: #16a34a;
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .2s ease;
    }

    .wa-primary:hover {
        background: #15803d;
    }

    .wa-primary:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .wa-secondary {
        padding: 12px 18px;
        border: 1px solid #d1d5db;
        border-radius: 11px;
        background: #fff;
        color: #374151;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .wa-secondary:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .wa-info {
        padding: 18px 20px;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        background: #f9fafb;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.7;
    }

    @media (max-width: 700px) {
        .wa-hero-inner {
            flex-direction: column;
            align-items: stretch;
        }

        .wa-refresh {
            width: 100%;
        }

        .wa-card {
            padding: 24px 18px;
        }

        .wa-qr-box img {
            width: 250px;
            height: 250px;
        }
    }
</style>

<div class="wa-wrap">

    <section class="wa-hero">

        <div class="wa-hero-inner">

            <div>
                <div class="wa-kicker">
                    💬 WHATSAPP BAĞLANTI MERKEZİ
                </div>

                <h2 class="wa-title">
                    {{ $record->name }}
                </h2>

                <p class="wa-subtitle">
                    WhatsApp numaranızı yapay zekâ sistemine bağlayın ve müşterilerinizle
                    otomatik görüşmeye başlayın.
                </p>
            </div>

            <button
                type="button"
                wire:click="baglantiyiYenile"
                wire:loading.attr="disabled"
                wire:target="baglantiyiYenile"
                class="wa-refresh"
            >
                <span wire:loading.remove wire:target="baglantiyiYenile">
                    Durumu Yenile
                </span>

                <span wire:loading wire:target="baglantiyiYenile">
                    Kontrol Ediliyor...
                </span>
            </button>

        </div>

    </section>


    <section class="wa-card">

        @if ($record->whatsapp_status === 'connected')

            <div class="wa-status-icon">
                ✓
            </div>

            <h3 class="wa-status-title">
                WhatsApp Bağlı
            </h3>

            <p class="wa-status-text">
                Numaranız yapay zekâ sistemine başarıyla bağlandı.
                Sisteminiz WhatsApp mesajlarını almaya hazır.
            </p>

            <div class="wa-actions">
                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    wire:target="baglantiyiYenile"
                    class="wa-secondary"
                >
                    <span wire:loading.remove wire:target="baglantiyiYenile">
                        Bağlantıyı Kontrol Et
                    </span>

                    <span wire:loading wire:target="baglantiyiYenile">
                        Kontrol Ediliyor...
                    </span>
                </button>
            </div>

        @elseif (! empty($record->whatsapp_qr))

            <h3 class="wa-qr-title">
                WhatsApp'ı Telefonunuzdan Bağlayın
            </h3>

            <p class="wa-status-text">
                Telefonunuzdaki WhatsApp uygulamasından aşağıdaki QR kodu okutun.
            </p>

            <div class="wa-qr-box">
                <img
                    src="{{ $record->whatsapp_qr }}"
                    alt="WhatsApp QR Kod"
                >
            </div>

            <div class="wa-help">
                WhatsApp → Ayarlar → Bağlı Cihazlar → Cihaz Bağla
            </div>

            <div class="wa-help-small">
                QR kodunun süresi dolarsa aşağıdaki butondan yeni kod oluşturabilirsiniz.
            </div>

            <div class="wa-actions">
                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    wire:target="baglantiyiYenile"
                    class="wa-primary"
                >
                    <span wire:loading.remove wire:target="baglantiyiYenile">
                        QR Kodunu Yenile
                    </span>

                    <span wire:loading wire:target="baglantiyiYenile">
                        Yenileniyor...
                    </span>
                </button>
            </div>

        @else

            <div class="wa-preparing-icon">
                📱
            </div>

            <h3 class="wa-preparing-title">
                WhatsApp Bağlantısı Hazırlanıyor
            </h3>

            <p class="wa-status-text">
                Bağlantı bilgilerinizi kontrol ederek QR kodunu oluşturabilirsiniz.
            </p>

            <div class="wa-actions">
                <button
                    type="button"
                    wire:click="baglantiyiYenile"
                    wire:loading.attr="disabled"
                    wire:target="baglantiyiYenile"
                    class="wa-primary"
                >
                    <span wire:loading.remove wire:target="baglantiyiYenile">
                        WhatsApp Bağlantısını Hazırla
                    </span>

                    <span wire:loading wire:target="baglantiyiYenile">
                        Hazırlanıyor...
                    </span>
                </button>
            </div>

        @endif

    </section>


    <div class="wa-info">
        <strong>İpucu:</strong>
        Bağlantı sırasında aynı butona tekrar tekrar basmanıza gerek yoktur.
        İşlem tamamlanana kadar buton otomatik olarak kilitlenir.
    </div>

</div>

</x-filament-panels::page>