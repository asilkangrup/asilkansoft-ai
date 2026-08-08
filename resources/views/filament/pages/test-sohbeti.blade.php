<x-filament-panels::page>

<style>
    .test-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.7fr) minmax(300px, .8fr);
        gap: 20px;
        align-items: start;
    }

    .test-main,
    .test-side {
        min-width: 0;
    }

    .test-header {
        padding: 24px;
        border-radius: 22px;
        color: #fff;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, .35), transparent 30%),
            linear-gradient(135deg, #111827 0%, #172554 55%, #1e3a8a 100%);
        box-shadow: 0 16px 40px rgba(15, 23, 42, .14);
        margin-bottom: 18px;
    }

    .test-kicker {
        display: inline-flex;
        padding: 6px 11px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.16);
        background: rgba(255,255,255,.08);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .05em;
    }

    .test-title {
        margin: 12px 0 0;
        font-size: 27px;
        font-weight: 800;
    }

    .test-subtitle {
        margin: 8px 0 0;
        color: rgba(255,255,255,.74);
        font-size: 14px;
        line-height: 1.7;
    }

    .test-chat {
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #f8fafc;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .chat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 18px;
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
    }

    .chat-identity {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .chat-avatar {
        display: flex;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: #eff6ff;
        font-size: 21px;
    }

    .chat-name {
        margin: 0;
        font-size: 14px;
        font-weight: 800;
        color: #111827;
    }

    .chat-meta {
        margin: 3px 0 0;
        font-size: 12px;
        color: #6b7280;
    }

    .clear-btn {
        padding: 9px 13px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: #fff;
        color: #374151;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
    }

    .messages {
        min-height: 430px;
        max-height: 560px;
        overflow-y: auto;
        padding: 20px;
    }

    .msg-row {
        display: flex;
        margin-bottom: 13px;
    }

    .msg-row.user {
        justify-content: flex-end;
    }

    .msg-row.bot {
        justify-content: flex-start;
    }

    .msg {
        max-width: 76%;
        padding: 11px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.6;
        word-break: break-word;
    }

    .msg.user {
        background: #2563eb;
        color: #fff;
        border-bottom-right-radius: 5px;
    }

    .msg.bot {
        background: #fff;
        color: #1f2937;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 5px;
    }

    .compose {
        display: flex;
        gap: 10px;
        padding: 15px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }

    .compose input {
        flex: 1;
        min-height: 46px;
        padding: 11px 14px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        outline: none;
        font-size: 14px;
    }

    .compose input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
    }

    .send-btn {
        min-width: 105px;
        border: 0;
        border-radius: 12px;
        background: #2563eb;
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .side-card {
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .side-head {
        padding: 18px 20px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .side-head h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .side-head p {
        margin: 4px 0 0;
        font-size: 12px;
        color: #6b7280;
    }

    .settings {
        padding: 18px 20px;
    }

    .setting-item {
        padding: 13px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .setting-item:last-child {
        border-bottom: 0;
    }

    .setting-label {
        font-size: 11px;
        font-weight: 800;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .setting-value {
        margin-top: 4px;
        color: #111827;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.5;
        word-break: break-word;
    }

    .side-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 0 20px 20px;
    }

    .edit-btn,
    .approve-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 46px;
        border-radius: 12px;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .edit-btn {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151 !important;
    }

    .approve-btn {
        border: 0;
        background: #16a34a;
        color: #fff !important;
    }

    .approve-btn:hover {
        background: #15803d;
    }

    .flow-note {
        margin-top: 18px;
        padding: 14px 16px;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: #eff6ff;
        color: #1e40af;
        font-size: 12px;
        line-height: 1.6;
    }

    @media (max-width: 950px) {
        .test-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 650px) {
        .compose {
            flex-direction: column;
        }

        .send-btn {
            min-height: 46px;
            width: 100%;
        }

        .msg {
            max-width: 88%;
        }
    }
</style>

<div class="test-layout">

    <div class="test-main">

        <div class="test-header">
            <div class="test-kicker">
                🤖 CANLI YAPAY ZEKÂ TESTİ
            </div>

            <h2 class="test-title">
                WhatsApp'a geçmeden önce test edin
            </h2>

            <p class="test-subtitle">
                Buradaki yapay zekâ, WhatsApp'ta kullanacağınız aynı firma bilgileri,
                ürünler, kurallar ve özel talimatlarla cevap verir.
            </p>
        </div>

        <div class="test-chat">

            <div class="chat-top">

                <div class="chat-identity">
                    <div class="chat-avatar">
                        🤖
                    </div>

                    <div>
                        <p class="chat-name">
                            {{ $aiBot?->name ?? 'Yapay Zekâ Asistanı' }}
                        </p>

                        <p class="chat-meta">
                            {{ $aiBot?->company_name ?? 'Firma bilgisi bulunamadı' }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="sohbetiTemizle"
                    wire:loading.attr="disabled"
                    wire:target="sohbetiTemizle"
                    class="clear-btn"
                >
                    Sohbeti Temizle
                </button>

            </div>

            <div class="messages">

                @foreach ($mesajlar as $mesajKaydi)

                    @if ($mesajKaydi['rol'] === 'user')

                        <div class="msg-row user">
                            <div class="msg user">
                                {{ $mesajKaydi['metin'] }}
                            </div>
                        </div>

                    @else

                        <div class="msg-row bot">
                            <div class="msg bot">
                                {{ $mesajKaydi['metin'] }}
                            </div>
                        </div>

                    @endif

                @endforeach

                <div
                    wire:loading
                    wire:target="mesajGonder"
                    class="msg-row bot"
                >
                    <div class="msg bot">
                        Yapay zekâ düşünüyor...
                    </div>
                </div>

            </div>

            <form
                wire:submit="mesajGonder"
                class="compose"
            >
                <input
                    type="text"
                    wire:model="mesaj"
                    placeholder="Müşteriniz gibi bir soru yazın..."
                    autocomplete="off"
                >

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="mesajGonder"
                    class="send-btn"
                >
                    <span wire:loading.remove wire:target="mesajGonder">
                        Gönder
                    </span>

                    <span wire:loading wire:target="mesajGonder">
                        Gönderiliyor...
                    </span>
                </button>
            </form>

        </div>

    </div>

    <aside class="test-side">

        <div class="side-card">

            <div class="side-head">
                <h3>
                    Yapay Zekâ Ayarları
                </h3>

                <p>
                    Test sırasında kullanılan aktif bilgiler
                </p>
            </div>

            <div class="settings">

                <div class="setting-item">
                    <div class="setting-label">
                        Firma
                    </div>

                    <div class="setting-value">
                        {{ $aiBot?->company_name ?: 'Tanımlanmadı' }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        Yapay Zekâ Rolü
                    </div>

                    <div class="setting-value">
                        {{ $roleLabel }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        Çalışma Saatleri
                    </div>

                    <div class="setting-value">
                        {{ $aiBot?->working_hours ?: 'Tanımlanmadı' }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        Kargo / Teslimat
                    </div>

                    <div class="setting-value">
                        {{ $aiBot?->cargo_information ?: 'Tanımlanmadı' }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        Ödeme
                    </div>

                    <div class="setting-value">
                        {{ $aiBot?->payment_information ?: 'Tanımlanmadı' }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        Otomatik Takip
                    </div>

                    <div class="setting-value">
                        {{ $aiBot?->follow_up_enabled ? 'Aktif' : 'Kapalı' }}
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        WhatsApp
                    </div>

                    <div class="setting-value">
                        {{ $whatsappLabel }}
                    </div>
                </div>

            </div>

            <div class="side-actions">

                <a
                    href="{{ $editUrl }}"
                    class="edit-btn"
                >
                    Ayarları Düzenle
                </a>

                <button
                    type="button"
                    wire:click="whatsappBaglantisinaGec"
                    wire:loading.attr="disabled"
                    wire:target="whatsappBaglantisinaGec"
                    class="approve-btn"
                >
                    <span
                        wire:loading.remove
                        wire:target="whatsappBaglantisinaGec"
                    >
                        ✓ Testi Onayla ve WhatsApp'ı Bağla
                    </span>

                    <span
                        wire:loading
                        wire:target="whatsappBaglantisinaGec"
                    >
                        WhatsApp'a geçiliyor...
                    </span>
                </button>

            </div>

        </div>

        <div class="flow-note">
            Önce müşteriniz gibi birkaç soru sorun. Cevapları kontrol edin.
            Gerekirse ayarları düzenleyin. Cevaplardan memnunsanız testi onaylayıp
            WhatsApp bağlantısına geçin.
        </div>

    </aside>

</div>

</x-filament-panels::page>