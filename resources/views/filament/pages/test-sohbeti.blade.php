<x-filament-panels::page>

<style>
    .studio-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(340px, .85fr);
        gap: 20px;
        align-items: stretch;
    }

    .studio-main,
    .studio-side {
        min-width: 0;
        display: flex;
    }

    /*
    |--------------------------------------------------------------------------
    | ÜST BİLGİ ALANI
    |--------------------------------------------------------------------------
    */

    .studio-hero {
        margin-bottom: 18px;
        padding: 24px 26px;
        border-radius: 22px;
        color: #fff;
        background:
            radial-gradient(
                circle at top right,
                rgba(59,130,246,.35),
                transparent 30%
            ),
            linear-gradient(
                135deg,
                #111827 0%,
                #172554 55%,
                #1e3a8a 100%
            );
        box-shadow: 0 16px 40px rgba(15,23,42,.14);
    }

    .studio-kicker {
        display: inline-flex;
        padding: 6px 11px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 999px;
        background: rgba(255,255,255,.08);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .05em;
    }

    .studio-title {
        margin: 12px 0 0;
        font-size: 27px;
        font-weight: 800;
    }

    .studio-subtitle {
        margin: 8px 0 0;
        max-width: 720px;
        color: rgba(255,255,255,.76);
        font-size: 14px;
        line-height: 1.7;
    }

    /*
    |--------------------------------------------------------------------------
    | SOL VE SAĞ KARTLAR
    |--------------------------------------------------------------------------
    |
    | İKİSİ DE TAM AYNI YÜKSEKLİKTE
    |
    */

    .chat-card,
    .settings-card {
        width: 100%;
        height: 720px;

        display: flex;
        flex-direction: column;

        overflow: hidden;

        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #fff;

        box-shadow:
            0 8px 25px rgba(15,23,42,.06);
    }

    /*
    |--------------------------------------------------------------------------
    | SOL - SOHBET ÜST ALANI
    |--------------------------------------------------------------------------
    */

    .chat-head {
        flex-shrink: 0;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 14px;

        padding: 16px 18px;

        border-bottom: 1px solid #e5e7eb;
        background: #fff;
    }

    .chat-person {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .chat-avatar {
        display: flex;

        width: 43px;
        height: 43px;

        flex-shrink: 0;

        align-items: center;
        justify-content: center;

        border-radius: 13px;

        background: #eff6ff;

        font-size: 21px;
    }

    .chat-name {
        margin: 0;

        color: #111827;

        font-size: 14px;
        font-weight: 800;
    }

    .chat-company {
        margin: 3px 0 0;

        color: #6b7280;

        font-size: 12px;
    }

    .clear-button {
        flex-shrink: 0;

        padding: 9px 13px;

        border: 1px solid #d1d5db;
        border-radius: 10px;

        background: #fff;
        color: #374151;

        font-size: 12px;
        font-weight: 800;

        cursor: pointer;
    }

    .clear-button:hover {
        background: #f9fafb;
    }

    /*
    |--------------------------------------------------------------------------
    | SOL - MESAJ ALANI
    |--------------------------------------------------------------------------
    |
    | SADECE BURASI KAYAR
    |
    */

    .messages {
        flex: 1 1 auto;
        min-height: 0;

        overflow-y: auto;

        padding: 20px;

        background: #f8fafc;
    }

    .message-row {
        display: flex;
        margin-bottom: 13px;
    }

    .message-row.user {
        justify-content: flex-end;
    }

    .message-row.bot {
        justify-content: flex-start;
    }

    .message {
        max-width: 76%;

        padding: 11px 14px;

        border-radius: 16px;

        font-size: 14px;
        line-height: 1.6;

        word-break: break-word;
    }

    .message.user {
        border-bottom-right-radius: 5px;

        background: #2563eb;
        color: #fff;
    }

    .message.bot {
        border: 1px solid #e5e7eb;

        border-bottom-left-radius: 5px;

        background: #fff;
        color: #1f2937;
    }

    /*
    |--------------------------------------------------------------------------
    | SOL - MESAJ YAZMA
    |--------------------------------------------------------------------------
    |
    | ALTTA SABİT
    |
    */

    .compose {
        flex-shrink: 0;

        display: flex;
        gap: 10px;

        padding: 15px;

        border-top: 1px solid #e5e7eb;

        background: #fff;
    }

    .compose-input {
        flex: 1;

        min-width: 0;
        min-height: 47px;

        padding: 11px 14px;

        border: 1px solid #d1d5db;
        border-radius: 12px;

        outline: none;

        background: #fff;

        color: #111827;

        font-size: 14px;
    }

    .compose-input:focus,
    .studio-input:focus,
    .studio-textarea:focus,
    .studio-select:focus {
        border-color: #2563eb;
        outline: none;

        box-shadow:
            0 0 0 3px rgba(37,99,235,.10);
    }

    .send-button {
        flex-shrink: 0;

        min-width: 105px;

        border: 0;
        border-radius: 12px;

        background: #2563eb;
        color: #fff;

        font-size: 13px;
        font-weight: 800;

        cursor: pointer;
    }

    .send-button:hover {
        background: #1d4ed8;
    }

    /*
    |--------------------------------------------------------------------------
    | SAĞ - BAŞLIK
    |--------------------------------------------------------------------------
    */

    .settings-header {
        flex-shrink: 0;

        padding: 18px 20px;

        border-bottom: 1px solid #e5e7eb;

        background: #f9fafb;
    }

    .settings-header h3 {
        margin: 0;

        color: #111827;

        font-size: 16px;
        font-weight: 800;
    }

    .settings-header p {
        margin: 5px 0 0;

        color: #6b7280;

        font-size: 12px;
        line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | SAĞ - AYARLAR
    |--------------------------------------------------------------------------
    |
    | SAĞ PANELİN SADECE BU BÖLÜMÜ KAYAR
    |
    */

    .settings-body {
        flex: 1 1 auto;
        min-height: 0;

        overflow-y: auto;

        padding: 18px;

        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .settings-body::-webkit-scrollbar {
        width: 7px;
    }

    .settings-body::-webkit-scrollbar-track {
        background: transparent;
    }

    .settings-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .settings-body::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .field {
        margin-bottom: 17px;
    }

    .field:last-child {
        margin-bottom: 0;
    }

    .field-label {
        display: block;

        margin-bottom: 7px;

        color: #374151;

        font-size: 12px;
        font-weight: 800;
    }

    .studio-input,
    .studio-select,
    .studio-textarea {
        box-sizing: border-box;

        width: 100%;

        border: 1px solid #d1d5db;
        border-radius: 11px;

        background: #fff;

        color: #111827;

        font-size: 13px;
    }

    .studio-input,
    .studio-select {
        min-height: 43px;

        padding: 9px 11px;
    }

    .studio-textarea {
        min-height: 90px;

        padding: 10px 11px;

        resize: vertical;

        line-height: 1.5;
    }

    .field-help {
        margin-top: 5px;

        color: #9ca3af;

        font-size: 11px;
        line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | OTOMATİK TAKİP
    |--------------------------------------------------------------------------
    */

    .switch-row {
        display: flex;

        align-items: center;
        justify-content: space-between;

        gap: 12px;

        padding: 12px;

        border: 1px solid #e5e7eb;
        border-radius: 12px;

        background: #f9fafb;
    }

    .switch-title {
        color: #111827;

        font-size: 12px;
        font-weight: 800;
    }

    .switch-text {
        margin-top: 3px;

        color: #6b7280;

        font-size: 11px;
    }

    /*
    |--------------------------------------------------------------------------
    | SAĞ - ALT BUTONLAR
    |--------------------------------------------------------------------------
    |
    | KAYDIRMA YAPILSA BİLE BUTONLAR HER ZAMAN GÖRÜNÜR
    |
    */

    .settings-actions {
        flex-shrink: 0;

        display: flex;
        flex-direction: column;

        gap: 10px;

        padding: 16px 18px 18px;

        border-top: 1px solid #e5e7eb;

        background: #fff;
    }

    .save-button,
    .approve-button {
        min-height: 47px;

        border: 0;
        border-radius: 12px;

        color: #fff;

        font-size: 13px;
        font-weight: 800;

        cursor: pointer;
    }

    .save-button {
        background: #2563eb;
    }

    .save-button:hover {
        background: #1d4ed8;
    }

    .approve-button {
        background: #16a34a;
    }

    .approve-button:hover {
        background: #15803d;
    }

    .save-button:disabled,
    .approve-button:disabled,
    .send-button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    /*
    |--------------------------------------------------------------------------
    | BİLGİ KUTUSU
    |--------------------------------------------------------------------------
    */

    .update-info {
        margin-bottom: 14px;

        padding: 12px 14px;

        border: 1px solid #dbeafe;
        border-radius: 12px;

        background: #eff6ff;

        color: #1e40af;

        font-size: 11px;
        line-height: 1.6;
    }

    /*
    |--------------------------------------------------------------------------
    | TABLET
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1000px) {

        .studio-layout {
            grid-template-columns: 1fr;
        }

        .studio-main,
        .studio-side {
            display: block;
        }

        .chat-card,
        .settings-card {
            height: 680px;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MOBİL
    |--------------------------------------------------------------------------
    */

    @media (max-width: 650px) {

        .studio-hero {
            padding: 20px;
        }

        .studio-title {
            font-size: 23px;
        }

        .chat-card,
        .settings-card {
            height: 620px;
        }

        .chat-head {
            align-items: flex-start;
        }

        .compose {
            flex-direction: column;
        }

        .send-button {
            width: 100%;
            min-height: 46px;
        }

        .message {
            max-width: 89%;
        }
    }

</style>


<div class="studio-hero">

    <div class="studio-kicker">
        ✦ ASİLKANSOFT AI TEST STÜDYOSU
    </div>

    <h2 class="studio-title">
        Ayarla, test et, geliştir
    </h2>

    <p class="studio-subtitle">
        Sağ taraftaki bilgileri değiştirebilir, ayarları kaydedebilir ve
        WhatsApp'a geçmeden önce yapay zekânın yeni ayarlarla nasıl cevap verdiğini
        anında test edebilirsiniz.
    </p>

</div>


<div class="studio-layout">

    {{-- SOL: CANLI SOHBET --}}

    <section class="studio-main">

        <div class="chat-card">

            <div class="chat-head">

                <div class="chat-person">

                    <div class="chat-avatar">
                        🤖
                    </div>

                    <div>

                        <p class="chat-name">
                            {{ $botName ?: 'Yapay Zekâ Asistanı' }}
                        </p>

                        <p class="chat-company">
                            {{ $companyName ?: 'Firma bilgisi girilmedi' }}
                        </p>

                    </div>

                </div>


                <button
                    type="button"
                    wire:click="sohbetiTemizle"
                    wire:loading.attr="disabled"
                    wire:target="sohbetiTemizle"
                    class="clear-button"
                >
                    Sohbeti Temizle
                </button>

            </div>


            <div class="messages">

                @foreach ($mesajlar as $mesajKaydi)

                    @if ($mesajKaydi['rol'] === 'user')

                        <div class="message-row user">

                            <div class="message user">
                                {{ $mesajKaydi['metin'] }}
                            </div>

                        </div>

                    @else

                        <div class="message-row bot">

                            <div class="message bot">
                                {{ $mesajKaydi['metin'] }}
                            </div>

                        </div>

                    @endif

                @endforeach


                <div
                    wire:loading
                    wire:target="mesajGonder"
                    class="message-row bot"
                >

                    <div class="message bot">
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
                    class="compose-input"
                >


                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="mesajGonder"
                    class="send-button"
                >

                    <span
                        wire:loading.remove
                        wire:target="mesajGonder"
                    >
                        Gönder
                    </span>

                    <span
                        wire:loading
                        wire:target="mesajGonder"
                    >
                        Bekleyin...
                    </span>

                </button>

            </form>

        </div>

    </section>


    {{-- SAĞ: CANLI AYARLAR --}}

    <aside class="studio-side">

        <div class="settings-card">

            <div class="settings-header">

                <h3>
                    ⚙️ Canlı Yapay Zekâ Ayarları
                </h3>

                <p>
                    Değişikliklerinizi kaydedin ve yeni ayarları hemen test edin.
                </p>

            </div>


            <div class="settings-body">

                <div class="update-info">

                    <strong>
                        Nasıl çalışır?
                    </strong>

                    <br>

                    Bilgileri değiştir → Ayarları Kaydet ve Testi Yenile →
                    sohbet sıfırlansın → yeni ayarlarla tekrar konuş.

                </div>


                <div class="field">

                    <label class="field-label">
                        Yapay Zekâ Adı
                    </label>

                    <input
                        type="text"
                        wire:model="botName"
                        class="studio-input"
                    >

                </div>


                <div class="field">

                    <label class="field-label">
                        Firma Adı
                    </label>

                    <input
                        type="text"
                        wire:model="companyName"
                        class="studio-input"
                    >

                </div>


                <div class="field">

                    <label class="field-label">
                        Yapay Zekâ Rolü
                    </label>

                    <select
                        wire:model="role"
                        class="studio-select"
                    >

                        <option value="sales">
                            Satış Uzmanı
                        </option>

                        <option value="support">
                            Müşteri Temsilcisi
                        </option>

                        <option value="technical">
                            Teknik Destek
                        </option>

                        <option value="assistant">
                            Sekreter / Asistan
                        </option>

                    </select>

                </div>


                <div class="field">

                    <label class="field-label">
                        Firma Hakkında
                    </label>

                    <textarea
                        wire:model="companyDescription"
                        class="studio-textarea"
                        rows="5"
                    ></textarea>

                    <div class="field-help">
                        Yapay zekâ firmanızı anlatırken bu bilgileri kullanır.
                    </div>

                </div>


                <div class="field">

                    <label class="field-label">
                        Çalışma Saatleri
                    </label>

                    <textarea
                        wire:model="workingHours"
                        class="studio-textarea"
                        rows="3"
                    ></textarea>

                </div>


                <div class="field">

                    <label class="field-label">
                        Kargo / Teslimat / Hizmet Bölgesi
                    </label>

                    <textarea
                        wire:model="cargoInformation"
                        class="studio-textarea"
                        rows="5"
                    ></textarea>

                </div>


                <div class="field">

                    <label class="field-label">
                        Ödeme Bilgileri
                    </label>

                    <textarea
                        wire:model="paymentInformation"
                        class="studio-textarea"
                        rows="4"
                    ></textarea>

                </div>


                <div class="field">

                    <label class="field-label">
                        İade / Değişim / İptal
                    </label>

                    <textarea
                        wire:model="returnPolicy"
                        class="studio-textarea"
                        rows="4"
                    ></textarea>

                </div>


                <div class="field">

                    <label class="field-label">
                        Özel Firma Kuralları
                    </label>

                    <textarea
                        wire:model="companyRules"
                        class="studio-textarea"
                        rows="6"
                    ></textarea>

                    <div class="field-help">
                        Örn: Fiyat uydurma, kesin teslimat sözü verme,
                        stok bilgisini tahmin etme.
                    </div>

                </div>


                <div class="field">

                    <label class="field-label">
                        Konuşma ve Satış Talimatları
                    </label>

                    <textarea
                        wire:model="systemPrompt"
                        class="studio-textarea"
                        rows="6"
                    ></textarea>

                    <div class="field-help">
                        Yapay zekânın müşterilerle nasıl konuşmasını istediğinizi yazın.
                    </div>

                </div>


                <div class="field">

                    <div class="switch-row">

                        <div>

                            <div class="switch-title">
                                Otomatik Takip
                            </div>

                            <div class="switch-text">
                                Cevap vermeyen müşterilere hatırlatma gönder.
                            </div>

                        </div>

                        <input
                            type="checkbox"
                            wire:model.live="followUpEnabled"
                        >

                    </div>

                </div>


                @if ($followUpEnabled)

                    <div class="field">

                        <label class="field-label">
                            1. Hatırlatma Süresi
                        </label>

                        <select
                            wire:model="firstFollowUpMinutes"
                            class="studio-select"
                        >

                            <option value="60">1 Saat</option>
                            <option value="120">2 Saat</option>
                            <option value="180">3 Saat</option>
                            <option value="360">6 Saat</option>
                            <option value="720">12 Saat</option>
                            <option value="1440">24 Saat</option>
                            <option value="2880">2 Gün</option>
                            <option value="4320">3 Gün</option>
                            <option value="7200">5 Gün</option>
                            <option value="10080">7 Gün</option>

                        </select>

                    </div>


                    <div class="field">

                        <label class="field-label">
                            1. Hatırlatma Mesajı
                        </label>

                        <textarea
                            wire:model="firstFollowUpMessage"
                            class="studio-textarea"
                            rows="4"
                        ></textarea>

                    </div>


                    <div class="field">

                        <div class="switch-row">

                            <div>

                                <div class="switch-title">
                                    2. Hatırlatma
                                </div>

                                <div class="switch-text">
                                    İkinci ve son takip mesajını gönder.
                                </div>

                            </div>

                            <input
                                type="checkbox"
                                wire:model.live="secondFollowUpEnabled"
                            >

                        </div>

                    </div>


                    @if ($secondFollowUpEnabled)

                        <div class="field">

                            <label class="field-label">
                                2. Hatırlatma Süresi
                            </label>

                            <select
                                wire:model="secondFollowUpMinutes"
                                class="studio-select"
                            >

                                <option value="1440">1 Gün</option>
                                <option value="2880">2 Gün</option>
                                <option value="4320">3 Gün</option>
                                <option value="5760">4 Gün</option>
                                <option value="7200">5 Gün</option>
                                <option value="10080">7 Gün</option>
                                <option value="14400">10 Gün</option>
                                <option value="20160">14 Gün</option>

                            </select>

                        </div>


                        <div class="field">

                            <label class="field-label">
                                2. ve Son Hatırlatma Mesajı
                            </label>

                            <textarea
                                wire:model="secondFollowUpMessage"
                                class="studio-textarea"
                                rows="4"
                            ></textarea>

                        </div>

                    @endif

                @endif

            </div>


            <div class="settings-actions">

                <button
                    type="button"
                    wire:click="ayarlariKaydet"
                    wire:loading.attr="disabled"
                    wire:target="ayarlariKaydet"
                    class="save-button"
                >

                    <span
                        wire:loading.remove
                        wire:target="ayarlariKaydet"
                    >
                        💾 Ayarları Kaydet ve Testi Yenile
                    </span>

                    <span
                        wire:loading
                        wire:target="ayarlariKaydet"
                    >
                        Ayarlar kaydediliyor...
                    </span>

                </button>


                <button
                    type="button"
                    wire:click="whatsappBaglantisinaGec"
                    wire:loading.attr="disabled"
                    wire:target="whatsappBaglantisinaGec"
                    class="approve-button"
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

    </aside>

</div>

</x-filament-panels::page>