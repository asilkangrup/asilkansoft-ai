<x-filament-panels::page>

<style>
    .ai-test-wrap {
        max-width: 980px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .ai-test-header {
        padding: 24px 26px;
        border-radius: 22px;
        color: #ffffff;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, .35), transparent 32%),
            linear-gradient(135deg, #111827 0%, #172554 55%, #1e3a8a 100%);
        box-shadow: 0 16px 40px rgba(15, 23, 42, .15);
    }

    .ai-test-header-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .ai-test-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 11px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.15);
        background: rgba(255,255,255,.08);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .05em;
    }

    .ai-test-title {
        margin: 12px 0 0;
        font-size: 27px;
        line-height: 1.2;
        font-weight: 800;
    }

    .ai-test-subtitle {
        margin: 8px 0 0;
        max-width: 650px;
        color: rgba(255,255,255,.75);
        font-size: 14px;
        line-height: 1.6;
    }

    .ai-test-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 12px;
        color: #bbf7d0;
        font-size: 12px;
        font-weight: 700;
    }

    .ai-test-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, .15);
    }

    .ai-clear-button {
        flex-shrink: 0;
        padding: 10px 15px;
        border: 1px solid rgba(255,255,255,.20);
        border-radius: 11px;
        background: rgba(255,255,255,.10);
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .2s ease;
    }

    .ai-clear-button:hover {
        background: rgba(255,255,255,.17);
    }

    .ai-clear-button:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .ai-chat-card {
        display: flex;
        flex-direction: column;
        min-height: 500px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #f8fafc;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .ai-chat-topbar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .ai-avatar {
        display: flex;
        width: 42px;
        height: 42px;
        flex: 0 0 42px;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        background: #eff6ff;
        font-size: 21px;
    }

    .ai-chat-name {
        margin: 0;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }

    .ai-chat-desc {
        margin: 3px 0 0;
        color: #6b7280;
        font-size: 12px;
    }

    .ai-messages {
        flex: 1;
        min-height: 400px;
        padding: 24px;
        overflow-y: auto;
        background:
            radial-gradient(circle at 20% 10%, rgba(59,130,246,.04), transparent 26%),
            #f8fafc;
    }

    .ai-message-row {
        display: flex;
        margin-bottom: 14px;
    }

    .ai-message-row.user {
        justify-content: flex-end;
    }

    .ai-message-row.bot {
        justify-content: flex-start;
    }

    .ai-message {
        max-width: 72%;
        padding: 11px 14px;
        border-radius: 16px;
        font-size: 14px;
        line-height: 1.6;
        word-break: break-word;
        box-shadow: 0 2px 6px rgba(15, 23, 42, .05);
    }

    .ai-message.user {
        border-bottom-right-radius: 5px;
        background: #2563eb;
        color: #ffffff;
    }

    .ai-message.bot {
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 5px;
        background: #ffffff;
        color: #1f2937;
    }

    .ai-thinking {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        border-bottom-left-radius: 5px;
        background: #ffffff;
        color: #6b7280;
        font-size: 13px;
    }

    .ai-thinking-dots {
        display: inline-flex;
        gap: 3px;
    }

    .ai-thinking-dots span {
        width: 5px;
        height: 5px;
        border-radius: 999px;
        background: #9ca3af;
        animation: ai-dot 1.2s infinite ease-in-out;
    }

    .ai-thinking-dots span:nth-child(2) {
        animation-delay: .15s;
    }

    .ai-thinking-dots span:nth-child(3) {
        animation-delay: .30s;
    }

    @keyframes ai-dot {
        0%, 80%, 100% {
            transform: scale(.7);
            opacity: .5;
        }

        40% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .ai-compose {
        padding: 16px;
        border-top: 1px solid #e5e7eb;
        background: #ffffff;
    }

    .ai-compose-inner {
        display: flex;
        gap: 10px;
    }

    .ai-input {
        width: 100%;
        min-height: 48px;
        padding: 12px 15px;
        border: 1px solid #d1d5db;
        border-radius: 13px;
        outline: none;
        background: #ffffff;
        color: #111827;
        font-size: 14px;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .ai-input:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
    }

    .ai-input::placeholder {
        color: #9ca3af;
    }

    .ai-send {
        min-width: 105px;
        padding: 0 18px;
        border: 0;
        border-radius: 13px;
        background: #2563eb;
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: background .2s ease;
    }

    .ai-send:hover {
        background: #1d4ed8;
    }

    .ai-send:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .ai-tip {
        padding: 15px 18px;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #ffffff;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.6;
    }

    @media (max-width: 700px) {
        .ai-test-header-inner {
            flex-direction: column;
            align-items: stretch;
        }

        .ai-clear-button {
            width: 100%;
        }

        .ai-test-header {
            padding: 22px;
        }

        .ai-test-title {
            font-size: 23px;
        }

        .ai-messages {
            padding: 16px;
        }

        .ai-message {
            max-width: 88%;
        }

        .ai-compose-inner {
            flex-direction: column;
        }

        .ai-send {
            min-height: 46px;
            width: 100%;
        }
    }
</style>


<div class="ai-test-wrap">

    <section class="ai-test-header">

        <div class="ai-test-header-inner">

            <div>

                <div class="ai-test-kicker">
                    🤖 YAPAY ZEKÂ TEST MERKEZİ
                </div>

                <h2 class="ai-test-title">
                    Yapay Zekânızı Test Edin
                </h2>

                <p class="ai-test-subtitle">
                    Müşterilerinizle nasıl konuşacağını canlıya almadan önce burada deneyin.
                    Sorular sorun, fiyat ve firma bilgilerini test edin ve cevapları kontrol edin.
                </p>

                <div class="ai-test-status">
                    <span class="ai-test-status-dot"></span>
                    Yapay zekâ teste hazır
                </div>

            </div>

            <button
                type="button"
                wire:click="sohbetiTemizle"
                wire:loading.attr="disabled"
                wire:target="sohbetiTemizle"
                class="ai-clear-button"
            >
                <span wire:loading.remove wire:target="sohbetiTemizle">
                    Sohbeti Temizle
                </span>

                <span wire:loading wire:target="sohbetiTemizle">
                    Temizleniyor...
                </span>
            </button>

        </div>

    </section>


    <section class="ai-chat-card">

        <div class="ai-chat-topbar">

            <div class="ai-avatar">
                🤖
            </div>

            <div>
                <h3 class="ai-chat-name">
                    Yapay Zekâ Asistanınız
                </h3>

                <p class="ai-chat-desc">
                    Test konuşması • Sadece sizin panelinizde görünür
                </p>
            </div>

        </div>


        <div class="ai-messages" id="ai-test-messages">

            @foreach ($mesajlar as $mesajKaydi)

                @if ($mesajKaydi['rol'] === 'user')

                    <div class="ai-message-row user">
                        <div class="ai-message user">
                            {{ $mesajKaydi['metin'] }}
                        </div>
                    </div>

                @else

                    <div class="ai-message-row bot">
                        <div class="ai-message bot">
                            {{ $mesajKaydi['metin'] }}
                        </div>
                    </div>

                @endif

            @endforeach


            <div
                wire:loading
                wire:target="mesajGonder"
                class="ai-message-row bot"
            >
                <div class="ai-thinking">

                    Yapay zekâ düşünüyor

                    <span class="ai-thinking-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>

                </div>
            </div>

        </div>


        <form
            wire:submit="mesajGonder"
            class="ai-compose"
        >

            <div class="ai-compose-inner">

                <input
                    type="text"
                    wire:model="mesaj"
                    placeholder="Müşteriniz gibi bir soru yazın..."
                    autocomplete="off"
                    class="ai-input"
                >

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="mesajGonder"
                    class="ai-send"
                >
                    <span wire:loading.remove wire:target="mesajGonder">
                        Gönder
                    </span>

                    <span wire:loading wire:target="mesajGonder">
                        Gönderiliyor...
                    </span>
                </button>

            </div>

        </form>

    </section>


    <div class="ai-tip">
        <strong>Test önerisi:</strong>
        Firmanızın fiyatlarını, çalışma saatlerini, kargo koşullarını ve müşterinin
        zor sorularına vereceği cevapları burada deneyebilirsiniz.
    </div>

</div>


<script>
    document.addEventListener('livewire:navigated', () => {
        const container = document.getElementById('ai-test-messages');

        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    });
</script>

</x-filament-panels::page>