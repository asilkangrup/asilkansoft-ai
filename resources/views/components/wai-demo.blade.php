REMIUM.blade.php


{{-- 
|--------------------------------------------------------------------------
| WAI PUBLIC LIVE DEMO
|--------------------------------------------------------------------------
|
| Landing page üzerinde çalışan public WAI demo.
|
| Akış:
| 1. İşletme adı
| 2. İşletme açıklaması
| 3. AI rolü
| 4. Gerçek OpenAI demo sohbeti
|
| WhatsApp webhook / Evolution API / mevcut AiBot sistemine dokunmaz.
|
--}}

<section class="wd-section" id="wai-demo">

    <div class="wd-light wd-light-left"></div>
    <div class="wd-light wd-light-right"></div>

    <div class="container">

        {{-- =========================================================
             INTRO
        ========================================================== --}}

        <div class="wd-intro reveal">

            <div class="section-tag">
                Üye olmadan deneyin
            </div>

            <h2 class="section-title">
                WAI'yi anlatmayalım.
                <span>Size çalıştıralım.</span>
            </h2>

            <p class="section-copy">
                İşletmenizi birkaç kısa adımda tanıtın.
                Ardından müşterinizmiş gibi mesaj gönderin ve
                WAI'nin işletmeniz adına nasıl konuştuğunu görün.
            </p>

        </div>

        {{-- =========================================================
             DEMO APP
        ========================================================== --}}

        <div class="wd-app reveal" id="waiDemoApp">

            {{-- HEADER --}}

            <header class="wd-app-header">

                <div class="wd-brand">

                    <div class="wd-brand-logo">
                        W
                    </div>

                    <div class="wd-brand-copy">
                        <strong>WAI Demo</strong>
                        <span>Canlı ürün deneyimi</span>
                    </div>

                </div>

                <div class="wd-live">
                    <i></i>
                    Demo aktif
                </div>

            </header>

            {{-- PROGRESS --}}

            <div class="wd-progress" id="waiDemoProgress">

                <div class="wd-progress-meta">

                    <span id="waiDemoStepLabel">
                        Adım 1 / 3
                    </span>

                    <strong id="waiDemoPercent">
                        %33
                    </strong>

                </div>

                <div class="wd-progress-track">
                    <span id="waiDemoProgressBar"></span>
                </div>

            </div>

            {{-- =====================================================
                 STEPS
            ====================================================== --}}

            <div class="wd-stage">

                {{-- STEP 1 --}}

                <section
                    class="wd-step active"
                    data-wd-step="1"
                >

                    <div class="wd-step-content">

                        <div class="wd-step-head">

                            <span class="wd-step-number">
                                01
                            </span>

                            <span class="wd-step-kicker">
                                WAI sizi tanımaya başlasın
                            </span>

                        </div>

                        <h3>
                            İşletmenizin adı nedir?
                        </h3>

                        <p class="wd-description">
                            WAI müşterilerinizle konuşurken işletmenizin
                            adını doğal şekilde kullanacak.
                        </p>

                        <div class="wd-field">

                            <label for="wdCompany">
                                İşletme adı
                            </label>

                            <div class="wd-input-wrap">

                                <input
                                    type="text"
                                    id="wdCompany"
                                    maxlength="80"
                                    autocomplete="organization"
                                    placeholder="Örn. Hilal Plise"
                                >

                                <span class="wd-input-icon">
                                    ✦
                                </span>

                            </div>

                        </div>

                        <div class="wd-example">
                            Örnek: Hilal Plise, AsilkanSoft, Yüncüoğlu Çiftliği
                        </div>

                    </div>

                    <footer class="wd-step-footer">

                        <span class="wd-safe">
                            🔒 Bilgileriniz henüz sunucuya gönderilmez.
                        </span>

                        <button
                            type="button"
                            class="wd-primary"
                            data-wd-next
                        >
                            <span>Devam Et</span>
                            <b>→</b>
                        </button>

                    </footer>

                </section>

                {{-- STEP 2 --}}

                <section
                    class="wd-step"
                    data-wd-step="2"
                >

                    <div class="wd-step-content">

                        <button
                            type="button"
                            class="wd-back"
                            data-wd-back
                        >
                            ← Geri
                        </button>

                        <div class="wd-step-head">

                            <span class="wd-step-number">
                                02
                            </span>

                            <span class="wd-step-kicker">
                                İşletmenizi öğretin
                            </span>

                        </div>

                        <h3>
                            Ne satıyorsunuz veya hangi hizmeti veriyorsunuz?
                        </h3>

                        <p class="wd-description">
                            Birkaç cümle yeterli. WAI demo sırasında yalnızca
                            verdiğiniz işletme bilgilerini kullanacak.
                        </p>

                        <div class="wd-field">

                            <label for="wdDescription">
                                İşletmenizi kısaca anlatın
                            </label>

                            <div class="wd-textarea-wrap">

                                <textarea
                                    id="wdDescription"
                                    rows="5"
                                    maxlength="500"
                                    placeholder="Örn. Kuşadası'nda plise sineklik, kedi tüllü sineklik ve plise perde satışı ve montajı yapıyoruz. Ücretsiz keşif hizmetimiz bulunuyor."
                                ></textarea>

                                <span class="wd-character-count">
                                    <b id="wdDescriptionCount">0</b>/500
                                </span>

                            </div>

                        </div>

                        <span class="wd-fast-label">
                            Hızlı örnekler
                        </span>

                        <div class="wd-examples">

                            <button
                                type="button"
                                data-wd-description="Zeytin ve naturel sızma zeytinyağı satıyoruz. Türkiye geneline kargo gönderiyoruz."
                            >
                                🫒 Zeytin & Zeytinyağı
                            </button>

                            <button
                                type="button"
                                data-wd-description="Plise sineklik, kedi tüllü sineklik ve plise perde satışı ve montajı yapıyoruz. Ücretsiz keşif hizmetimiz bulunuyor."
                            >
                                🏠 Sineklik
                            </button>

                            <button
                                type="button"
                                data-wd-description="İşletmelere web sitesi, reklam yönetimi ve yapay zekâ yazılım hizmetleri sunuyoruz."
                            >
                                💻 Yazılım & Ajans
                            </button>

                        </div>

                    </div>

                    <footer class="wd-step-footer">

                        <span class="wd-safe">
                            Bu bilgileri üyelikten sonra değiştirebilirsiniz.
                        </span>

                        <button
                            type="button"
                            class="wd-primary"
                            data-wd-next
                        >
                            <span>Devam Et</span>
                            <b>→</b>
                        </button>

                    </footer>

                </section>

                {{-- STEP 3 --}}

                <section
                    class="wd-step"
                    data-wd-step="3"
                >

                    <div class="wd-step-content">

                        <button
                            type="button"
                            class="wd-back"
                            data-wd-back
                        >
                            ← Geri
                        </button>

                        <div class="wd-step-head">

                            <span class="wd-step-number">
                                03
                            </span>

                            <span class="wd-step-kicker">
                                WAI'nin görevini seçin
                            </span>

                        </div>

                        <h3>
                            Müşterilerinizle nasıl ilgilensin?
                        </h3>

                        <p class="wd-description">
                            Demo için temel görevini seçin.
                            Üyelikten sonra ayrıntılı konuşma talimatları ekleyebilirsiniz.
                        </p>

                        <div class="wd-role-grid">

                            <button
                                type="button"
                                class="wd-role active"
                                data-wd-role="sales"
                                data-wd-role-label="Satış Uzmanı"
                            >

                                <span class="wd-role-icon">
                                    ↗
                                </span>

                                <span class="wd-role-copy">
                                    <strong>Satış Uzmanı</strong>
                                    <small>
                                        İhtiyacı anlar ve satış görüşmesini ilerletir.
                                    </small>
                                </span>

                                <span class="wd-role-check">
                                    ✓
                                </span>

                            </button>

                            <button
                                type="button"
                                class="wd-role"
                                data-wd-role="support"
                                data-wd-role-label="Müşteri Temsilcisi"
                            >

                                <span class="wd-role-icon">
                                    ◉
                                </span>

                                <span class="wd-role-copy">
                                    <strong>Müşteri Temsilcisi</strong>
                                    <small>
                                        Soruları cevaplar ve müşteriye yardımcı olur.
                                    </small>
                                </span>

                                <span class="wd-role-check">
                                    ✓
                                </span>

                            </button>

                            <button
                                type="button"
                                class="wd-role"
                                data-wd-role="assistant"
                                data-wd-role-label="Sekreter / Asistan"
                            >

                                <span class="wd-role-icon">
                                    ✦
                                </span>

                                <span class="wd-role-copy">
                                    <strong>Sekreter / Asistan</strong>
                                    <small>
                                        Bilgi toplar ve doğru noktaya yönlendirir.
                                    </small>
                                </span>

                                <span class="wd-role-check">
                                    ✓
                                </span>

                            </button>

                            <button
                                type="button"
                                class="wd-role"
                                data-wd-role="technical"
                                data-wd-role-label="Teknik Destek"
                            >

                                <span class="wd-role-icon">
                                    ⌘
                                </span>

                                <span class="wd-role-copy">
                                    <strong>Teknik Destek</strong>
                                    <small>
                                        Problemi anlar ve çözüm için ilerler.
                                    </small>
                                </span>

                                <span class="wd-role-check">
                                    ✓
                                </span>

                            </button>

                        </div>

                    </div>

                    <footer class="wd-step-footer">

                        <span class="wd-safe">
                            WAI işletmenize göre hazırlanacak.
                        </span>

                        <button
                            type="button"
                            class="wd-primary"
                            id="wdCreateDemo"
                        >
                            <span>WAI'yi Hazırla</span>
                            <b>✦</b>
                        </button>

                    </footer>

                </section>

                {{-- =================================================
                     REAL AI CHAT
                ================================================== --}}

                <section
                    class="wd-step wd-chat-step"
                    data-wd-step="4"
                >

                    <div class="wd-chat">

                        {{-- CHAT HEADER --}}

                        <header class="wd-chat-header">

                            <button
                                type="button"
                                class="wd-chat-back"
                                data-wd-back
                                aria-label="Geri dön"
                            >
                                ←
                            </button>

                            <div class="wd-avatar">
                                W
                                <i></i>
                            </div>

                            <div class="wd-chat-profile">

                                <strong id="wdBotTitle">
                                    WAI Satış Uzmanı
                                </strong>

                                <span>
                                    çevrimiçi
                                </span>

                            </div>

                            <div class="wd-demo-badge">
                                DEMO
                            </div>

                        </header>

                        {{-- MESSAGES --}}

                        <div
                            class="wd-messages"
                            id="wdMessages"
                        >

                            <div class="wd-system-message">
                                WAI işletmeniz için hazırlandı
                            </div>

                            <div
                                class="wd-bubble wai"
                                id="wdWelcomeBubble"
                            >

                                <div>

                                    <span id="wdWelcome">
                                        Merhaba 👋 Size nasıl yardımcı olabilirim?
                                    </span>

                                    <time>
                                        şimdi
                                        <b>✓✓</b>
                                    </time>

                                </div>

                            </div>

                        </div>

                        {{-- DEMO STATUS --}}

                        <div class="wd-demo-status">

                            <span>
                                <i></i>
                                Gerçek AI demo
                            </span>

                            <strong>
                                <b id="wdRemaining">5</b>
                                mesaj kaldı
                            </strong>

                        </div>

                        {{-- QUICK MESSAGES --}}

                        <div class="wd-quick-messages">

                            <button
                                type="button"
                                data-wd-quick="Ne iş yapıyorsunuz?"
                            >
                                Ne iş yapıyorsunuz?
                            </button>

                            <button
                                type="button"
                                data-wd-quick="Fiyat bilgisi alabilir miyim?"
                            >
                                Fiyat bilgisi
                            </button>

                            <button
                                type="button"
                                data-wd-quick="Sipariş vermek istiyorum."
                            >
                                Sipariş
                            </button>

                        </div>

                        {{-- COMPOSER --}}

                        <form
                            class="wd-composer"
                            id="wdChatForm"
                        >

                            <input
                                id="wdChatInput"
                                type="text"
                                maxlength="500"
                                autocomplete="off"
                                placeholder="Müşterinizmiş gibi yazın..."
                            >

                            <button
                                type="submit"
                                aria-label="Gönder"
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="m22 2-7 20-4-9-9-4Z"></path>
                                    <path d="M22 2 11 13"></path>
                                </svg>

                            </button>

                        </form>

                        {{-- CONVERSION --}}

                        <div
                            class="wd-conversion"
                            id="wdConversion"
                        >

                            <div class="wd-conversion-icon">
                                ✦
                            </div>

                            <div class="wd-conversion-copy">

                                <strong>
                                    WAI'niz hazır.
                                </strong>

                                <span>
                                    Şimdi aynı yapay zekâyı gerçek WhatsApp
                                    müşterilerinizle konuşturun.
                                </span>

                            </div>

                            <a
                                href="/admin/register"
                                class="wd-connect"
                            >
                                WhatsApp'ıma Bağla
                                <b>→</b>
                            </a>

                        </div>

                    </div>

                </section>

            </div>

        </div>

        {{-- =========================================================
             EXPLANATION
        ========================================================== --}}

        <div class="wd-under reveal">

            <div>
                <strong>01</strong>
                <span>İşletmenizi tanıtın</span>
            </div>

            <i></i>

            <div>
                <strong>02</strong>
                <span>Müşteriniz gibi yazın</span>
            </div>

            <i></i>

            <div>
                <strong>03</strong>
                <span>WhatsApp'a bağlayın</span>
            </div>

        </div>

    </div>

</section>

<style>
    /*
    |--------------------------------------------------------------------------
    | WAI PUBLIC DEMO
    |--------------------------------------------------------------------------
    */

    .wd-section {
        position: relative;
        padding: 135px 0;
        overflow: hidden;

        background:
            radial-gradient(
                circle at 50% 0%,
                rgba(92,255,157,.045),
                transparent 32%
            ),
            #050806;
    }

    .wd-light {
        position: absolute;
        pointer-events: none;
        border-radius: 50%;
    }

    .wd-light-left {
        width: 720px;
        height: 720px;
        left: -430px;
        top: 60px;

        background:
            radial-gradient(
                circle,
                rgba(92,255,157,.08),
                transparent 68%
            );
    }

    .wd-light-right {
        width: 680px;
        height: 680px;
        right: -420px;
        bottom: -120px;

        background:
            radial-gradient(
                circle,
                rgba(92,255,157,.06),
                transparent 70%
            );
    }

    /*
    |--------------------------------------------------------------------------
    | INTRO
    |--------------------------------------------------------------------------
    */

    .wd-intro {
        position: relative;
        z-index: 2;

        max-width: 930px;
        margin: 0 auto 56px;

        text-align: center;
    }

    .wd-intro .section-tag {
        justify-content: center;
    }

    .wd-intro .section-title {
        margin-left: auto;
        margin-right: auto;
    }

    .wd-intro .section-title span {
        display: block;
        color: var(--green);
    }

    .wd-intro .section-copy {
        max-width: 670px;

        margin-left: auto;
        margin-right: auto;
    }

    /*
    |--------------------------------------------------------------------------
    | APP
    |--------------------------------------------------------------------------
    */

    .wd-app {
        position: relative;
        z-index: 3;

        width: min(900px, 100%);
        min-height: 680px;
        margin: 0 auto;

        overflow: hidden;

        border:
            1px solid rgba(92,255,157,.12);

        border-radius: 32px;

        background:
            radial-gradient(
                circle at 90% 0%,
                rgba(92,255,157,.06),
                transparent 30%
            ),
            linear-gradient(
                155deg,
                #0d140f,
                #070c08
            );

        box-shadow:
            0 70px 160px rgba(0,0,0,.50),
            0 22px 60px rgba(0,0,0,.30),
            inset 0 0 0 1px rgba(255,255,255,.018);
    }

    .wd-app::before {
        content: "";

        position: absolute;
        inset: 0;

        pointer-events: none;

        background-image:
            linear-gradient(
                rgba(255,255,255,.014) 1px,
                transparent 1px
            ),
            linear-gradient(
                90deg,
                rgba(255,255,255,.014) 1px,
                transparent 1px
            );

        background-size: 46px 46px;

        mask-image:
            linear-gradient(
                to bottom,
                black,
                transparent 76%
            );
    }

    /*
    |--------------------------------------------------------------------------
    | APP HEADER
    |--------------------------------------------------------------------------
    */

    .wd-app-header {
        position: relative;
        z-index: 4;

        height: 76px;

        padding: 0 23px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        border-bottom:
            1px solid rgba(255,255,255,.06);

        background:
            rgba(255,255,255,.012);
    }

    .wd-brand {
        display: flex;
        align-items: center;
        gap: 11px;
    }

    .wd-brand-logo {
        width: 40px;
        height: 40px;

        display: grid;
        place-items: center;

        border-radius: 12px;

        color: var(--green);

        border:
            1px solid rgba(92,255,157,.12);

        background:
            radial-gradient(
                circle at 30% 20%,
                rgba(92,255,157,.20),
                transparent 48%
            ),
            #08100a;

        font-family: 'Manrope', sans-serif;

        font-size: 13px;
        font-weight: 800;

        box-shadow:
            inset 0 0 20px rgba(92,255,157,.025);
    }

    .wd-brand-copy strong {
        display: block;

        font-size: 12px;
    }

    .wd-brand-copy span {
        display: block;

        margin-top: 3px;

        color: #536057;

        font-size: 7px;

        text-transform: uppercase;
        letter-spacing: .9px;
    }

    .wd-live {
        min-height: 32px;

        padding: 0 11px;

        display: inline-flex;
        align-items: center;

        gap: 7px;

        border:
            1px solid rgba(92,255,157,.08);

        border-radius: 999px;

        color: #8affb8;

        background:
            rgba(92,255,157,.045);

        font-size: 8px;
        font-weight: 800;
    }

    .wd-live i {
        width: 6px;
        height: 6px;

        border-radius: 50%;

        background: var(--green);

        box-shadow:
            0 0 12px rgba(92,255,157,.75);
    }

    /*
    |--------------------------------------------------------------------------
    | PROGRESS
    |--------------------------------------------------------------------------
    */

    .wd-progress {
        position: relative;
        z-index: 3;

        padding: 15px 23px 14px;

        border-bottom:
            1px solid rgba(255,255,255,.045);
    }

    .wd-progress-meta {
        margin-bottom: 9px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        color: #657168;

        font-size: 8px;
        font-weight: 700;
    }

    .wd-progress-meta strong {
        color: #9effc3;
    }

    .wd-progress-track {
        height: 4px;

        overflow: hidden;

        border-radius: 999px;

        background:
            rgba(255,255,255,.045);
    }

    .wd-progress-track span {
        display: block;

        width: 33.333%;
        height: 100%;

        border-radius: inherit;

        background:
            linear-gradient(
                90deg,
                #1ddd75,
                var(--green)
            );

        box-shadow:
            0 0 18px rgba(92,255,157,.35);

        transition:
            width .45s cubic-bezier(.2,.8,.2,1);
    }

    /*
    |--------------------------------------------------------------------------
    | STAGES
    |--------------------------------------------------------------------------
    */

    .wd-stage {
        position: relative;
        z-index: 3;

        min-height: 565px;
    }

    .wd-step {
        display: none;

        min-height: 565px;
    }

    .wd-step.active {
        display: flex;
        flex-direction: column;

        animation:
            wd-step-enter .32s ease;
    }

    @keyframes wd-step-enter {

        from {
            opacity: 0;
            transform: translateX(12px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .wd-step-content {
        width:
            min(620px, calc(100% - 48px));

        margin: 0 auto;

        padding:
            47px 0 32px;

        flex: 1;
    }

    .wd-step-head {
        margin-bottom: 18px;

        display: flex;
        align-items: center;

        gap: 12px;
    }

    .wd-step-number {
        width: 38px;
        height: 38px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border-radius: 11px;

        color: var(--green);

        border:
            1px solid rgba(92,255,157,.09);

        background:
            rgba(92,255,157,.06);

        font-size: 9px;
        font-weight: 800;
    }

    .wd-step-kicker {
        color: #87948b;

        font-size: 9px;
        font-weight: 700;

        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .wd-step h3 {
        max-width: 600px;

        font-family:
            'Manrope',
            sans-serif;

        font-size: 38px;

        line-height: 1.02;

        letter-spacing: -2px;
    }

    .wd-description {
        max-width: 570px;

        margin-top: 15px;

        color: #6d7971;

        font-size: 13px;

        line-height: 1.68;
    }

    /*
    |--------------------------------------------------------------------------
    | BACK
    |--------------------------------------------------------------------------
    */

    .wd-back {
        margin-bottom: 24px;

        border: 0;

        color: #657269;

        background: transparent;

        font-size: 9px;
        font-weight: 800;

        cursor: pointer;

        transition: .2s ease;
    }

    .wd-back:hover {
        color: var(--green);
    }

    /*
    |--------------------------------------------------------------------------
    | INPUTS
    |--------------------------------------------------------------------------
    */

    .wd-field {
        margin-top: 33px;
    }

    .wd-field label {
        display: block;

        margin-bottom: 8px;

        color: #8a968e;

        font-size: 9px;
        font-weight: 700;
    }

    .wd-input-wrap,
    .wd-textarea-wrap {
        position: relative;
    }

    .wd-input-wrap input,
    .wd-textarea-wrap textarea {
        width: 100%;

        border:
            1px solid rgba(255,255,255,.075);

        outline: 0;

        color: white;

        background:
            rgba(255,255,255,.025);

        transition:
            border-color .2s ease,
            background .2s ease,
            box-shadow .2s ease;
    }

    .wd-input-wrap input {
        height: 62px;

        padding:
            0 55px 0 18px;

        border-radius: 16px;

        font-family: inherit;
        font-size: 14px;
    }

    .wd-textarea-wrap textarea {
        min-height: 150px;

        resize: vertical;

        padding:
            17px 18px 31px;

        border-radius: 16px;

        font-family: inherit;
        font-size: 13px;

        line-height: 1.6;
    }

    .wd-input-wrap input::placeholder,
    .wd-textarea-wrap textarea::placeholder {
        color: #465149;
    }

    .wd-input-wrap input:focus,
    .wd-textarea-wrap textarea:focus {
        border-color:
            rgba(92,255,157,.27);

        background:
            rgba(92,255,157,.025);

        box-shadow:
            0 0 0 4px rgba(92,255,157,.035);
    }

    .wd-input-icon {
        position: absolute;

        right: 18px;
        top: 50%;

        transform:
            translateY(-50%);

        color: var(--green);
    }

    .wd-character-count {
        position: absolute;

        right: 14px;
        bottom: 10px;

        color: #4c5950;

        font-size: 8px;
    }

    .wd-character-count b {
        color: #7a877e;
    }

    .wd-example {
        margin-top: 9px;

        color: #465249;

        font-size: 8px;

        line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | EXAMPLES
    |--------------------------------------------------------------------------
    */

    .wd-fast-label {
        display: block;

        margin-top: 21px;
        margin-bottom: 8px;

        color: #59665d;

        font-size: 8px;
        font-weight: 700;
    }

    .wd-examples {
        display: flex;
        flex-wrap: wrap;

        gap: 7px;
    }

    .wd-examples button {
        min-height: 38px;

        padding: 0 12px;

        border:
            1px solid rgba(255,255,255,.055);

        border-radius: 10px;

        color: #818e85;

        background:
            rgba(255,255,255,.025);

        font-family: inherit;

        font-size: 8px;
        font-weight: 700;

        cursor: pointer;

        transition: .2s ease;
    }

    .wd-examples button:hover {
        color: #c6ffdb;

        border-color:
            rgba(92,255,157,.12);

        background:
            rgba(92,255,157,.04);
    }

    /*
    |--------------------------------------------------------------------------
    | ROLES
    |--------------------------------------------------------------------------
    */

    .wd-role-grid {
        margin-top: 30px;

        display: grid;
        grid-template-columns:
            repeat(2, 1fr);

        gap: 9px;
    }

    .wd-role {
        min-height: 105px;

        padding: 15px;

        display: flex;
        align-items: center;

        gap: 12px;

        border:
            1px solid rgba(255,255,255,.055);

        border-radius: 15px;

        color: inherit;

        background:
            rgba(255,255,255,.02);

        text-align: left;

        cursor: pointer;

        transition: .22s ease;
    }

    .wd-role:hover {
        border-color:
            rgba(92,255,157,.11);

        background:
            rgba(92,255,157,.025);
    }

    .wd-role.active {
        border-color:
            rgba(92,255,157,.23);

        background:
            linear-gradient(
                145deg,
                rgba(92,255,157,.085),
                rgba(92,255,157,.025)
            );
    }

    .wd-role-icon {
        width: 42px;
        height: 42px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border-radius: 12px;

        color: #758178;

        background:
            rgba(255,255,255,.035);

        font-size: 15px;
    }

    .wd-role.active .wd-role-icon {
        color: var(--green);

        background:
            rgba(92,255,157,.08);
    }

    .wd-role-copy {
        min-width: 0;

        flex: 1;
    }

    .wd-role-copy strong {
        display: block;

        font-size: 10px;
    }

    .wd-role-copy small {
        display: block;

        margin-top: 5px;

        color: #5a675e;

        font-size: 7px;

        line-height: 1.45;
    }

    .wd-role-check {
        width: 22px;
        height: 22px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border:
            1px solid rgba(255,255,255,.07);

        border-radius: 50%;

        color: transparent;

        font-size: 8px;
    }

    .wd-role.active .wd-role-check {
        border-color: transparent;

        color: #06150b;

        background: var(--green);
    }

    /*
    |--------------------------------------------------------------------------
    | STEP FOOTER
    |--------------------------------------------------------------------------
    */

    .wd-step-footer {
        min-height: 86px;

        padding: 15px 23px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 20px;

        border-top:
            1px solid rgba(255,255,255,.05);

        background:
            rgba(4,8,5,.35);
    }

    .wd-safe {
        color: #4e5b52;

        font-size: 8px;
    }

    .wd-primary {
        min-width: 165px;
        min-height: 51px;

        padding:
            0 9px 0 17px;

        display: inline-flex;
        align-items: center;
        justify-content: space-between;

        gap: 13px;

        border: 0;

        border-radius: 13px;

        color: #06140a;

        background:
            linear-gradient(
                135deg,
                #72ffab,
                #42f68c
            );

        box-shadow:
            0 15px 34px rgba(92,255,157,.11);

        font-family: inherit;

        font-size: 10px;
        font-weight: 800;

        cursor: pointer;

        transition:
            transform .2s ease,
            box-shadow .2s ease;
    }

    .wd-primary:hover {
        transform:
            translateY(-2px);

        box-shadow:
            0 20px 42px rgba(92,255,157,.16);
    }

    .wd-primary b {
        width: 32px;
        height: 32px;

        display: grid;
        place-items: center;

        border-radius: 9px;

        background:
            rgba(4,20,10,.09);
    }

    /*
    |--------------------------------------------------------------------------
    | CHAT
    |--------------------------------------------------------------------------
    */

    .wd-chat-step,
    .wd-chat {
        min-height: 565px;
    }

    .wd-chat {
        width: 100%;

        display: flex;
        flex-direction: column;
    }

    .wd-chat-header {
        min-height: 67px;

        padding: 0 17px;

        display: flex;
        align-items: center;

        gap: 10px;

        border-bottom:
            1px solid rgba(255,255,255,.05);

        background:
            rgba(255,255,255,.012);
    }

    .wd-chat-back {
        width: 33px;
        height: 33px;

        border: 0;

        border-radius: 9px;

        color: #7c8980;

        background:
            rgba(255,255,255,.03);

        cursor: pointer;
    }

    .wd-avatar {
        position: relative;

        width: 39px;
        height: 39px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border:
            1px solid rgba(92,255,157,.1);

        border-radius: 12px;

        color: var(--green);

        background:
            rgba(92,255,157,.065);

        font-family:
            'Manrope',
            sans-serif;

        font-size: 11px;
        font-weight: 800;
    }

    .wd-avatar i {
        position: absolute;

        width: 9px;
        height: 9px;

        right: -2px;
        bottom: -1px;

        border:
            2px solid #0a100c;

        border-radius: 50%;

        background: var(--green);

        box-shadow:
            0 0 9px rgba(92,255,157,.55);
    }

    .wd-chat-profile {
        min-width: 0;

        flex: 1;
    }

    .wd-chat-profile strong {
        display: block;

        overflow: hidden;

        white-space: nowrap;

        text-overflow: ellipsis;

        font-size: 10px;
    }

    .wd-chat-profile span {
        display: block;

        margin-top: 3px;

        color: var(--green);

        font-size: 7px;
    }

    .wd-demo-badge {
        height: 27px;

        padding: 0 9px;

        display: grid;
        place-items: center;

        border-radius: 8px;

        color: #a0ffbf;

        background:
            rgba(92,255,157,.065);

        font-size: 6px;
        font-weight: 800;

        letter-spacing: .8px;
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGES
    |--------------------------------------------------------------------------
    */

    .wd-messages {
        flex: 1;

        min-height: 295px;
        max-height: 355px;

        overflow-y: auto;

        padding:
            22px 20px 15px;

        display: flex;
        flex-direction: column;

        gap: 9px;

        scrollbar-width: thin;

        scrollbar-color:
            rgba(92,255,157,.14)
            transparent;
    }

    .wd-system-message {
        align-self: center;

        padding: 6px 9px;

        border-radius: 7px;

        color: #59665e;

        background:
            rgba(255,255,255,.025);

        font-size: 6px;
        font-weight: 700;
    }

    .wd-bubble {
        max-width: 74%;

        animation:
            wd-bubble-enter .24s ease;
    }

    @keyframes wd-bubble-enter {

        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .wd-bubble > div {
        padding: 10px 11px;

        border-radius: 11px;

        font-size: 9px;

        line-height: 1.58;
    }

    .wd-bubble.user {
        align-self: flex-end;
    }

    .wd-bubble.user > div {
        color: #c9ffdf;

        border:
            1px solid rgba(92,255,157,.045);

        border-top-right-radius: 3px;

        background:
            linear-gradient(
                145deg,
                rgba(38,159,90,.17),
                rgba(92,255,157,.08)
            );
    }

    .wd-bubble.wai {
        align-self: flex-start;
    }

    .wd-bubble.wai > div {
        color: #b7c2ba;

        border:
            1px solid rgba(255,255,255,.04);

        border-top-left-radius: 3px;

        background:
            rgba(255,255,255,.045);
    }

    .wd-bubble time {
        display: block;

        margin-top: 5px;

        color: #536057;

        font-size: 5px;

        text-align: right;
    }

    .wd-bubble time b {
        color: #56a4d6;
    }

    /*
    |--------------------------------------------------------------------------
    | TYPING
    |--------------------------------------------------------------------------
    */

    .wd-typing {
        min-width: 48px;

        display: inline-flex;
        align-items: center;

        gap: 4px;
    }

    .wd-typing i {
        width: 5px;
        height: 5px;

        border-radius: 50%;

        background: #758079;

        animation:
            wd-typing 1.1s infinite ease;
    }

    .wd-typing i:nth-child(2) {
        animation-delay: .12s;
    }

    .wd-typing i:nth-child(3) {
        animation-delay: .24s;
    }

    @keyframes wd-typing {

        0%,
        60%,
        100% {
            opacity: .35;
            transform: translateY(0);
        }

        30% {
            opacity: 1;
            transform: translateY(-3px);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DEMO STATUS
    |--------------------------------------------------------------------------
    */

    .wd-demo-status {
        padding:
            8px 17px;

        display: flex;
        align-items: center;
        justify-content: space-between;

        border-top:
            1px solid rgba(255,255,255,.035);

        border-bottom:
            1px solid rgba(255,255,255,.035);

        color: #56635a;

        font-size: 7px;
    }

    .wd-demo-status > span {
        display: inline-flex;
        align-items: center;

        gap: 6px;
    }

    .wd-demo-status i {
        width: 6px;
        height: 6px;

        border-radius: 50%;

        background: var(--green);

        box-shadow:
            0 0 10px rgba(92,255,157,.45);
    }

    .wd-demo-status strong {
        color: #859188;
    }

    .wd-demo-status strong b {
        color: var(--green);
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK MESSAGES
    |--------------------------------------------------------------------------
    */

    .wd-quick-messages {
        padding:
            9px 12px 0;

        display: flex;

        gap: 6px;

        overflow-x: auto;

        scrollbar-width: none;
    }

    .wd-quick-messages::-webkit-scrollbar {
        display: none;
    }

    .wd-quick-messages button {
        min-width: max-content;

        min-height: 32px;

        padding: 0 10px;

        border:
            1px solid rgba(255,255,255,.055);

        border-radius: 999px;

        color: #69766d;

        background:
            rgba(255,255,255,.022);

        font-family: inherit;

        font-size: 7px;
        font-weight: 700;

        cursor: pointer;

        transition: .2s ease;
    }

    .wd-quick-messages button:hover {
        color: #a8ffca;

        border-color:
            rgba(92,255,157,.11);

        background:
            rgba(92,255,157,.04);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPOSER
    |--------------------------------------------------------------------------
    */

    .wd-composer {
        min-height: 67px;

        padding:
            9px 12px 11px;

        display: flex;
        align-items: center;

        gap: 7px;
    }

    .wd-composer input {
        min-width: 0;

        flex: 1;

        height: 45px;

        padding: 0 14px;

        border:
            1px solid rgba(255,255,255,.06);

        outline: none;

        border-radius: 13px;

        color: #dce6df;

        background:
            rgba(255,255,255,.028);

        font-family: inherit;

        font-size: 10px;

        transition: .2s ease;
    }

    .wd-composer input::placeholder {
        color: #455148;
    }

    .wd-composer input:focus {
        border-color:
            rgba(92,255,157,.18);
    }

    .wd-composer input:disabled {
        opacity: .55;
    }

    .wd-composer button {
        width: 45px;
        height: 45px;

        display: grid;
        place-items: center;

        flex-shrink: 0;

        border: 0;

        border-radius: 13px;

        color: #06150b;

        background: var(--green);

        cursor: pointer;

        box-shadow:
            0 10px 25px rgba(92,255,157,.11);

        transition: .2s ease;
    }

    .wd-composer button:hover {
        transform:
            translateY(-1px);
    }

    .wd-composer button svg {
        width: 15px;
    }

    /*
    |--------------------------------------------------------------------------
    | CONVERSION CTA
    |--------------------------------------------------------------------------
    */

    .wd-conversion {
        display: none;

        margin:
            0 12px 12px;

        padding: 14px;

        grid-template-columns:
            42px minmax(0,1fr) auto;

        align-items: center;

        gap: 11px;

        border:
            1px solid rgba(92,255,157,.11);

        border-radius: 16px;

        background:
            radial-gradient(
                circle at 100% 0%,
                rgba(92,255,157,.09),
                transparent 42%
            ),
            rgba(92,255,157,.04);

        animation:
            wd-conversion-enter .35s ease;
    }

    .wd-conversion.show {
        display: grid;
    }

    @keyframes wd-conversion-enter {

        from {
            opacity: 0;
            transform: translateY(7px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .wd-conversion-icon {
        width: 42px;
        height: 42px;

        display: grid;
        place-items: center;

        border-radius: 12px;

        color: var(--green);

        background:
            rgba(92,255,157,.08);
    }

    .wd-conversion-copy strong {
        display: block;

        font-size: 9px;
    }

    .wd-conversion-copy span {
        display: block;

        margin-top: 4px;

        color: #607067;

        font-size: 6px;

        line-height: 1.45;
    }

    .wd-connect {
        min-height: 42px;

        padding: 0 13px;

        display: inline-flex;
        align-items: center;

        gap: 9px;

        border-radius: 11px;

        color: #06150b;

        background: var(--green);

        font-size: 8px;
        font-weight: 800;
    }

    /*
    |--------------------------------------------------------------------------
    | UNDER
    |--------------------------------------------------------------------------
    */

    .wd-under {
        position: relative;
        z-index: 2;

        width: min(720px, 100%);

        margin:
            35px auto 0;

        display: grid;

        grid-template-columns:
            1fr 80px 1fr 80px 1fr;

        align-items: center;
    }

    .wd-under > div {
        text-align: center;
    }

    .wd-under strong {
        display: block;

        color: var(--green);

        font-size: 9px;
    }

    .wd-under span {
        display: block;

        margin-top: 5px;

        color: #56635a;

        font-size: 8px;
    }

    .wd-under > i {
        height: 1px;

        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(92,255,157,.15),
                transparent
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MOBILE
    |--------------------------------------------------------------------------
    */

    @media (max-width: 720px) {

        .wd-section {
            padding:
                82px 0 90px;
        }

        .wd-intro {
            margin-bottom: 34px;
        }

        .wd-intro .section-title {
            font-size: 41px;

            line-height: .98;

            letter-spacing: -2.7px;
        }

        .wd-intro .section-title span {
            margin-top: 4px;
        }

        .wd-intro .section-copy {
            font-size: 13px;

            line-height: 1.65;
        }

        .wd-app {
            width: 100%;

            min-height: 640px;

            border-radius: 25px;
        }

        /*
        |----------------------------------------------------------------------
        | Mobile Header
        |----------------------------------------------------------------------
        */

        .wd-app-header {
            height: 63px;

            padding: 0 14px;
        }

        .wd-brand-logo {
            width: 34px;
            height: 34px;

            border-radius: 10px;
        }

        .wd-brand-copy strong {
            font-size: 10px;
        }

        .wd-brand-copy span {
            font-size: 5px;
        }

        .wd-live {
            min-height: 28px;

            padding: 0 8px;

            font-size: 6px;
        }

        /*
        |----------------------------------------------------------------------
        | Mobile Progress
        |----------------------------------------------------------------------
        */

        .wd-progress {
            padding:
                12px 14px 11px;
        }

        /*
        |----------------------------------------------------------------------
        | Mobile Step
        |----------------------------------------------------------------------
        */

        .wd-stage,
        .wd-step,
        .wd-chat {
            min-height: 565px;
        }

        .wd-step-content {
            width:
                calc(100% - 30px);

            padding:
                31px 0 22px;
        }

        .wd-step-head {
            margin-bottom: 15px;
        }

        .wd-step-number {
            width: 35px;
            height: 35px;
        }

        .wd-step-kicker {
            font-size: 7px;
        }

        .wd-step h3 {
            font-size: 31px;

            line-height: 1.02;

            letter-spacing: -1.7px;
        }

        .wd-description {
            margin-top: 12px;

            font-size: 11px;

            line-height: 1.6;
        }

        .wd-back {
            margin-bottom: 19px;
        }

        /*
        |----------------------------------------------------------------------
        | iOS 16px input prevents Safari zoom
        |----------------------------------------------------------------------
        */

        .wd-field {
            margin-top: 25px;
        }

        .wd-input-wrap input {
            height: 58px;

            font-size: 16px;
        }

        .wd-textarea-wrap textarea {
            min-height: 135px;

            font-size: 16px;
        }

        /*
        |----------------------------------------------------------------------
        | Examples
        |----------------------------------------------------------------------
        */

        .wd-examples {
            display: grid;

            grid-template-columns: 1fr;
        }

        .wd-examples button {
            min-height: 43px;

            text-align: left;
        }

        /*
        |----------------------------------------------------------------------
        | Roles
        |----------------------------------------------------------------------
        */

        .wd-role-grid {
            grid-template-columns: 1fr;

            margin-top: 22px;
        }

        .wd-role {
            min-height: 81px;
        }

        .wd-role-copy strong {
            font-size: 10px;
        }

        .wd-role-copy small {
            font-size: 7px;
        }

        /*
        |----------------------------------------------------------------------
        | Footer
        |----------------------------------------------------------------------
        */

        .wd-step-footer {
            min-height: 81px;

            padding: 12px 14px;
        }

        .wd-safe {
            display: none;
        }

        .wd-primary {
            width: 100%;

            min-height: 55px;

            font-size: 11px;
        }

        /*
        |----------------------------------------------------------------------
        | Chat Header
        |----------------------------------------------------------------------
        */

        .wd-chat-header {
            min-height: 61px;

            padding: 0 11px;
        }

        .wd-chat-back {
            width: 31px;
            height: 31px;
        }

        .wd-avatar {
            width: 36px;
            height: 36px;
        }

        .wd-chat-profile strong {
            font-size: 9px;
        }

        .wd-chat-profile span {
            font-size: 6px;
        }

        .wd-demo-badge {
            height: 25px;

            padding: 0 7px;
        }

        /*
        |----------------------------------------------------------------------
        | Chat Messages
        |----------------------------------------------------------------------
        */

        .wd-messages {
            min-height: 300px;
            max-height: 350px;

            padding:
                17px 11px 12px;
        }

        .wd-bubble {
            max-width: 88%;
        }

        .wd-bubble > div {
            padding: 9px 10px;

            font-size: 9px;
        }

        /*
        |----------------------------------------------------------------------
        | Status
        |----------------------------------------------------------------------
        */

        .wd-demo-status {
            padding: 8px 11px;
        }

        /*
        |----------------------------------------------------------------------
        | Quick
        |----------------------------------------------------------------------
        */

        .wd-quick-messages {
            padding:
                8px 9px 0;
        }

        /*
        |----------------------------------------------------------------------
        | Composer
        |----------------------------------------------------------------------
        */

        .wd-composer {
            padding:
                8px 9px
                calc(10px + env(safe-area-inset-bottom));
        }

        .wd-composer input {
            height: 48px;

            font-size: 16px;
        }

        .wd-composer button {
            width: 48px;
            height: 48px;
        }

        /*
        |----------------------------------------------------------------------
        | Conversion
        |----------------------------------------------------------------------
        */

        .wd-conversion {
            margin:
                0 9px 9px;

            padding: 11px;

            grid-template-columns:
                36px 1fr;
        }

        .wd-conversion-icon {
            width: 36px;
            height: 36px;
        }

        .wd-connect {
            grid-column:
                1 / -1;

            width: 100%;

            min-height: 47px;

            justify-content: center;

            font-size: 9px;
        }

        /*
        |----------------------------------------------------------------------
        | Under
        |----------------------------------------------------------------------
        */

        .wd-under {
            margin-top: 28px;

            grid-template-columns: 1fr;

            gap: 8px;
        }

        .wd-under > i {
            width: 1px;
            height: 17px;

            margin: 0 auto;

            background:
                linear-gradient(
                    transparent,
                    rgba(92,255,157,.16),
                    transparent
                );
        }

        .wd-under > div {
            padding: 6px 0;
        }
    }

    @media (max-width: 390px) {

        .wd-intro .section-title {
            font-size: 37px;
        }

        .wd-step h3 {
            font-size: 28px;
        }

        .wd-step-content {
            width:
                calc(100% - 24px);
        }

        .wd-app-header {
            padding: 0 11px;
        }
    }

/* WAI DEMO LIGHT PREMIUM FINAL */
.wd-section{padding:100px 0!important;background:radial-gradient(circle at 50% 0%,rgba(16,185,129,.08),transparent 32%),linear-gradient(180deg,#f8fbf9,#f2f6f3)!important}
.wd-intro{max-width:980px!important;margin-bottom:44px!important}
.wd-intro .section-title{color:#0c140f!important}
.wd-intro .section-copy{max-width:720px!important;color:#66736b!important;font-size:16px!important}
.wd-app{width:min(1180px,100%)!important;min-height:750px!important;border:1px solid rgba(15,23,42,.09)!important;background:radial-gradient(circle at 92% 0%,rgba(16,185,129,.07),transparent 30%),#fff!important;box-shadow:0 38px 90px rgba(15,23,42,.10)!important}
.wd-app::before{background-image:linear-gradient(rgba(15,23,42,.022) 1px,transparent 1px),linear-gradient(90deg,rgba(15,23,42,.022) 1px,transparent 1px)!important}
.wd-app-header{height:84px!important;padding:0 30px!important;border-color:#e1e8e3!important;background:#f8faf9!important}
.wd-brand-logo{width:48px!important;height:48px!important;color:#087a42!important;border-color:#ccefdc!important;background:#edfbf3!important;font-size:15px!important}
.wd-brand-copy strong{color:#17211b!important;font-size:15px!important}
.wd-brand-copy span{color:#748078!important;font-size:10px!important}
.wd-live{min-height:38px!important;color:#087a42!important;border-color:#ccefdc!important;background:#edfbf3!important;font-size:11px!important}
.wd-progress{padding:18px 30px 17px!important;border-color:#e1e8e3!important}
.wd-progress-meta{color:#657169!important;font-size:12px!important}.wd-progress-meta strong{color:#087a42!important}
.wd-progress-track{height:6px!important;background:#e8efea!important}
.wd-stage,.wd-step,.wd-chat-step,.wd-chat{min-height:625px!important}
.wd-step-content{width:min(800px,calc(100% - 64px))!important;padding:55px 0 38px!important}
.wd-step-number{width:46px!important;height:46px!important;color:#087a42!important;border-color:#ccefdc!important;background:#edfbf3!important;font-size:13px!important}
.wd-step-kicker{color:#56645b!important;font-size:13px!important}
.wd-step h3{max-width:760px!important;color:#0c140f!important;font-size:45px!important;letter-spacing:-2.4px!important}
.wd-description{max-width:700px!important;color:#68756d!important;font-size:16px!important}
.wd-back,.wd-field label{color:#56645b!important;font-size:12px!important}
.wd-input-wrap input,.wd-textarea-wrap textarea{color:#17211b!important;border-color:#dce5df!important;background:#f8faf9!important}
.wd-input-wrap input{height:70px!important;font-size:17px!important}.wd-textarea-wrap textarea{min-height:175px!important;font-size:16px!important}
.wd-input-wrap input::placeholder,.wd-textarea-wrap textarea::placeholder,.wd-composer input::placeholder{color:#9aa49e!important}
.wd-character-count,.wd-example,.wd-fast-label{color:#7b8780!important;font-size:11px!important}
.wd-examples button{min-height:44px!important;color:#56645b!important;border-color:#dfe7e2!important;background:#f7faf8!important;font-size:11px!important}
.wd-role{min-height:118px!important;border-color:#dfe7e2!important;background:#f8faf9!important}.wd-role:hover,.wd-role.active{border-color:#aee4c6!important;background:#effaf4!important}
.wd-role-copy strong{color:#18221c!important;font-size:13px!important}.wd-role-copy small{color:#748078!important;font-size:11px!important}
.wd-step-footer{min-height:96px!important;padding:17px 30px!important;border-color:#e1e8e3!important;background:#f8faf9!important}
.wd-safe{color:#748078!important;font-size:11px!important}.wd-primary{min-width:190px!important;min-height:56px!important;color:#fff!important;background:linear-gradient(135deg,#1fd47b,#0fb966)!important;font-size:13px!important}
.wd-chat-header{min-height:76px!important;padding:0 24px!important;border-color:#e1e8e3!important;background:#f8faf9!important}
.wd-chat-back{color:#56645b!important;background:#edf2ef!important}.wd-avatar{width:46px!important;height:46px!important;color:#087a42!important;border-color:#ccefdc!important;background:#edfbf3!important}
.wd-avatar i{border-color:#fff!important}.wd-chat-profile strong{color:#17211b!important;font-size:13px!important}.wd-chat-profile span{font-size:10px!important}.wd-demo-badge{color:#087a42!important;background:#eaf9f1!important;font-size:9px!important}
.wd-messages{min-height:355px!important;max-height:425px!important;padding:27px 28px 18px!important;background:radial-gradient(circle at 50% 100%,rgba(16,185,129,.04),transparent 35%),#fff!important}
.wd-system-message{color:#748078!important;background:#f0f4f1!important;font-size:9px!important}.wd-bubble{max-width:70%!important}.wd-bubble>div{padding:13px 14px!important;font-size:13px!important}
.wd-bubble.user>div{color:#08673a!important;border-color:#cdebd9!important;background:#eaf9f1!important}.wd-bubble.wai>div{color:#46534b!important;border-color:#e0e7e2!important;background:#f2f5f3!important}.wd-bubble time{color:#8c9690!important;font-size:8px!important}
.wd-demo-status{padding:10px 24px!important;color:#68756d!important;border-color:#e3e9e5!important;background:#fbfcfb!important;font-size:10px!important}
.wd-quick-messages{padding:12px 18px 0!important;background:#fff!important}.wd-quick-messages button{min-height:38px!important;color:#59665e!important;border-color:#dfe7e2!important;background:#f8faf9!important;font-size:10px!important}
.wd-composer{min-height:78px!important;padding:11px 18px 14px!important;background:#fff!important}.wd-composer input{height:53px!important;color:#17211b!important;border-color:#dce5df!important;background:#f7faf8!important;font-size:14px!important}.wd-composer button{width:53px!important;height:53px!important;color:#fff!important;background:#12c56d!important}
.wd-conversion{border-color:#ccefdc!important;background:#effaf4!important}.wd-conversion-copy strong{color:#17211b!important;font-size:12px!important}.wd-conversion-copy span{color:#68756d!important;font-size:10px!important}.wd-connect{color:#fff!important;background:#12c56d!important;font-size:11px!important}
.wd-under{width:min(850px,100%)!important}.wd-under strong,.wd-under span{font-size:11px!important}.wd-under span{color:#657169!important}
@media(max-width:720px){.wd-section{padding:76px 0 82px!important}.wd-step h3{font-size:32px!important}.wd-description{font-size:14px!important}.wd-bubble>div{font-size:12px!important}}

</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {

        /*
        |--------------------------------------------------------------------------
        | ROOT
        |--------------------------------------------------------------------------
        */

        const app =
            document.getElementById('waiDemoApp');

        if (!app) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------------------------------
        */

        const steps =
            app.querySelectorAll('[data-wd-step]');

        const nextButtons =
            app.querySelectorAll('[data-wd-next]');

        const backButtons =
            app.querySelectorAll('[data-wd-back]');

        const companyInput =
            document.getElementById('wdCompany');

        const descriptionInput =
            document.getElementById('wdDescription');

        const descriptionCount =
            document.getElementById('wdDescriptionCount');

        const roleButtons =
            app.querySelectorAll('[data-wd-role]');

        const createButton =
            document.getElementById('wdCreateDemo');

        const progress =
            document.getElementById('waiDemoProgress');

        const progressBar =
            document.getElementById('waiDemoProgressBar');

        const progressLabel =
            document.getElementById('waiDemoStepLabel');

        const progressPercent =
            document.getElementById('waiDemoPercent');

        const botTitle =
            document.getElementById('wdBotTitle');

        const welcome =
            document.getElementById('wdWelcome');

        const welcomeBubble =
            document.getElementById('wdWelcomeBubble');

        const messages =
            document.getElementById('wdMessages');

        const chatForm =
            document.getElementById('wdChatForm');

        const chatInput =
            document.getElementById('wdChatInput');

        const submitButton =
            chatForm?.querySelector(
                'button[type="submit"]'
            );

        const quickButtons =
            app.querySelectorAll('[data-wd-quick]');

        const remainingElement =
            document.getElementById('wdRemaining');

        const conversion =
            document.getElementById('wdConversion');

        /*
        |--------------------------------------------------------------------------
        | CONFIG
        |--------------------------------------------------------------------------
        */

        const demoEndpoint =
            @json(route('demo.chat'));

        const csrfToken =
            @json(csrf_token());

        /*
        |--------------------------------------------------------------------------
        | STATE
        |--------------------------------------------------------------------------
        */

        let currentStep = 1;

        let selectedRole = 'sales';

        let selectedRoleLabel =
            'Satış Uzmanı';

        let remainingMessages = 5;

        let conversationHistory = [];

        let isSending = false;

        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        const clean = value => {
            return String(value || '').trim();
        };

        const scrollMessagesToBottom = () => {

            if (!messages) {
                return;
            }

            requestAnimationFrame(() => {
                messages.scrollTop =
                    messages.scrollHeight;
            });

        };

        const showStep = step => {

            currentStep = step;

            steps.forEach(element => {

                const elementStep =
                    Number(
                        element.dataset.wdStep
                    );

                element.classList.toggle(
                    'active',
                    elementStep === step
                );

            });

            /*
            |--------------------------------------------------------------------------
            | Progress
            |--------------------------------------------------------------------------
            */

            if (step <= 3) {

                progress.style.display = '';

                const percentage =
                    Math.round(
                        (step / 3) * 100
                    );

                progressBar.style.width =
                    percentage + '%';

                progressLabel.textContent =
                    `Adım ${step} / 3`;

                progressPercent.textContent =
                    `%${percentage}`;

            } else {

                progress.style.display =
                    'none';

            }

            /*
            |--------------------------------------------------------------------------
            | Mobile Scroll
            |--------------------------------------------------------------------------
            */

            if (window.innerWidth <= 720) {

                setTimeout(() => {

                    app.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                }, 50);

            }

        };

        const highlightError = element => {

            if (!element) {
                return;
            }

            element.focus();

            const shell =
                element.closest('.wd-input-wrap') ||
                element.closest('.wd-textarea-wrap');

            if (!shell) {
                return;
            }

            shell.animate(
                [
                    {
                        transform:
                            'translateX(0)'
                    },
                    {
                        transform:
                            'translateX(-5px)'
                    },
                    {
                        transform:
                            'translateX(5px)'
                    },
                    {
                        transform:
                            'translateX(0)'
                    }
                ],
                {
                    duration: 260
                }
            );

        };

        const setSendingState =
            sending => {

                isSending = sending;

                if (chatInput) {
                    chatInput.disabled =
                        sending;
                }

                if (submitButton) {

                    submitButton.disabled =
                        sending;

                    submitButton.style.opacity =
                        sending ? '.5' : '1';

                }

                quickButtons.forEach(button => {

                    button.disabled =
                        sending;

                    button.style.opacity =
                        sending ? '.45' : '1';

                });

            };

        /*
        |--------------------------------------------------------------------------
        | NEXT
        |--------------------------------------------------------------------------
        */

        nextButtons.forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    if (currentStep === 1) {

                        if (
                            clean(
                                companyInput.value
                            ).length < 2
                        ) {

                            highlightError(
                                companyInput
                            );

                            return;
                        }

                    }

                    if (currentStep === 2) {

                        if (
                            clean(
                                descriptionInput.value
                            ).length < 10
                        ) {

                            highlightError(
                                descriptionInput
                            );

                            return;
                        }

                    }

                    if (currentStep < 3) {

                        showStep(
                            currentStep + 1
                        );

                    }

                }
            );

        });

        /*
        |--------------------------------------------------------------------------
        | BACK
        |--------------------------------------------------------------------------
        */

        backButtons.forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    if (isSending) {
                        return;
                    }

                    if (currentStep === 4) {

                        showStep(3);

                        return;
                    }

                    if (currentStep > 1) {

                        showStep(
                            currentStep - 1
                        );

                    }

                }
            );

        });

        /*
        |--------------------------------------------------------------------------
        | COMPANY ENTER
        |--------------------------------------------------------------------------
        */

        companyInput?.addEventListener(
            'keydown',
            event => {

                if (
                    event.key === 'Enter' &&
                    clean(
                        companyInput.value
                    ).length >= 2
                ) {

                    event.preventDefault();

                    showStep(2);

                }

            }
        );

        /*
        |--------------------------------------------------------------------------
        | DESCRIPTION COUNTER
        |--------------------------------------------------------------------------
        */

        descriptionInput?.addEventListener(
            'input',
            () => {

                descriptionCount.textContent =
                    descriptionInput.value.length;

            }
        );

        /*
        |--------------------------------------------------------------------------
        | DESCRIPTION EXAMPLES
        |--------------------------------------------------------------------------
        */

        app
            .querySelectorAll(
                '[data-wd-description]'
            )
            .forEach(button => {

                button.addEventListener(
                    'click',
                    () => {

                        descriptionInput.value =
                            button.dataset
                                .wdDescription || '';

                        descriptionCount.textContent =
                            descriptionInput
                                .value.length;

                        descriptionInput.focus();

                    }
                );

            });

        /*
        |--------------------------------------------------------------------------
        | ROLE
        |--------------------------------------------------------------------------
        */

        roleButtons.forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    roleButtons.forEach(
                        item => {
                            item.classList.remove(
                                'active'
                            );
                        }
                    );

                    button.classList.add(
                        'active'
                    );

                    selectedRole =
                        button.dataset.wdRole ||
                        'sales';

                    selectedRoleLabel =
                        button.dataset
                            .wdRoleLabel ||
                        'Satış Uzmanı';

                }
            );

        });

        /*
        |--------------------------------------------------------------------------
        | RESET CHAT
        |--------------------------------------------------------------------------
        */

        const resetChat = () => {

            conversationHistory = [];

            remainingMessages = 5;

            remainingElement.textContent =
                '5';

            conversion.classList.remove(
                'show'
            );

            chatInput.placeholder =
                'Müşterinizmiş gibi yazın...';

            /*
            |--------------------------------------------------------------------------
            | Kullanıcı ve AI test mesajlarını temizle.
            |--------------------------------------------------------------------------
            */

            messages
                .querySelectorAll(
                    '.wd-bubble:not(#wdWelcomeBubble)'
                )
                .forEach(element => {
                    element.remove();
                });

            document
                .getElementById(
                    'wdTypingBubble'
                )
                ?.remove();

        };

        /*
        |--------------------------------------------------------------------------
        | CREATE DEMO
        |--------------------------------------------------------------------------
        */

        createButton?.addEventListener(
            'click',
            () => {

                const company =
                    clean(
                        companyInput.value
                    );

                const description =
                    clean(
                        descriptionInput.value
                    );

                if (company.length < 2) {

                    showStep(1);

                    highlightError(
                        companyInput
                    );

                    return;
                }

                if (description.length < 10) {

                    showStep(2);

                    highlightError(
                        descriptionInput
                    );

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Header
                |--------------------------------------------------------------------------
                */

                botTitle.textContent =
                    `${company} · ${selectedRoleLabel}`;

                /*
                |--------------------------------------------------------------------------
                | Welcome
                |--------------------------------------------------------------------------
                */

                welcome.textContent =
                    `Merhaba 👋 ${company} adına size yardımcı oluyorum. Nasıl yardımcı olabilirim?`;

                /*
                |--------------------------------------------------------------------------
                | Reset
                |--------------------------------------------------------------------------
                */

                resetChat();

                /*
                |--------------------------------------------------------------------------
                | Show Chat
                |--------------------------------------------------------------------------
                */

                showStep(4);

                setTimeout(() => {

                    chatInput?.focus();

                    scrollMessagesToBottom();

                }, 300);

            }
        );

        /*
        |--------------------------------------------------------------------------
        | ADD MESSAGE
        |--------------------------------------------------------------------------
        */

        const addMessage =
            (text, type) => {

                const wrapper =
                    document.createElement(
                        'div'
                    );

                wrapper.className =
                    `wd-bubble ${type}`;

                const inner =
                    document.createElement(
                        'div'
                    );

                const textNode =
                    document.createElement(
                        'span'
                    );

                /*
                |--------------------------------------------------------------------------
                | textContent = güvenli çıktı.
                |--------------------------------------------------------------------------
                */

                textNode.textContent =
                    text;

                const time =
                    document.createElement(
                        'time'
                    );

                if (type === 'wai') {

                    time.appendChild(
                        document.createTextNode(
                            'şimdi '
                        )
                    );

                    const ticks =
                        document.createElement(
                            'b'
                        );

                    ticks.textContent =
                        '✓✓';

                    time.appendChild(
                        ticks
                    );

                } else {

                    time.textContent =
                        'şimdi';

                }

                inner.appendChild(
                    textNode
                );

                inner.appendChild(
                    time
                );

                wrapper.appendChild(
                    inner
                );

                messages.appendChild(
                    wrapper
                );

                scrollMessagesToBottom();

            };

        /*
        |--------------------------------------------------------------------------
        | TYPING
        |--------------------------------------------------------------------------
        */

        const removeTyping = () => {

            document
                .getElementById(
                    'wdTypingBubble'
                )
                ?.remove();

        };

        const addTyping = () => {

            removeTyping();

            const wrapper =
                document.createElement(
                    'div'
                );

            wrapper.className =
                'wd-bubble wai';

            wrapper.id =
                'wdTypingBubble';

            const inner =
                document.createElement(
                    'div'
                );

            const typing =
                document.createElement(
                    'span'
                );

            typing.className =
                'wd-typing';

            typing.innerHTML =
                '<i></i><i></i><i></i>';

            inner.appendChild(
                typing
            );

            wrapper.appendChild(
                inner
            );

            messages.appendChild(
                wrapper
            );

            scrollMessagesToBottom();

        };

        /*
        |--------------------------------------------------------------------------
        | ERROR BUBBLE
        |--------------------------------------------------------------------------
        */

        const addErrorMessage = text => {

            const wrapper =
                document.createElement(
                    'div'
                );

            wrapper.className =
                'wd-bubble wai';

            const inner =
                document.createElement(
                    'div'
                );

            inner.style.borderColor =
                'rgba(255,100,100,.16)';

            inner.style.background =
                'rgba(255,70,70,.045)';

            const span =
                document.createElement(
                    'span'
                );

            span.textContent =
                text;

            inner.appendChild(
                span
            );

            wrapper.appendChild(
                inner
            );

            messages.appendChild(
                wrapper
            );

            scrollMessagesToBottom();

        };

        /*
        |--------------------------------------------------------------------------
        | API REQUEST
        |--------------------------------------------------------------------------
        */

        const requestWaiResponse =
            async userMessage => {

                const payload = {

                    company_name:
                        clean(
                            companyInput.value
                        ),

                    company_description:
                        clean(
                            descriptionInput.value
                        ),

                    role:
                        selectedRole,

                    message:
                        userMessage,

                    /*
                    |--------------------------------------------------------------------------
                    | Son konuşma geçmişini backend'e gönder.
                    |--------------------------------------------------------------------------
                    */

                    messages:
                        conversationHistory.slice(
                            -8
                        )

                };

                const response =
                    await fetch(
                        demoEndpoint,
                        {
                            method:
                                'POST',

                            credentials:
                                'same-origin',

                            headers: {

                                'Content-Type':
                                    'application/json',

                                'Accept':
                                    'application/json',

                                'X-CSRF-TOKEN':
                                    csrfToken,

                                'X-Requested-With':
                                    'XMLHttpRequest'

                            },

                            body:
                                JSON.stringify(
                                    payload
                                )

                        }
                    );

                let data;

                try {

                    data =
                        await response.json();

                } catch (error) {

                    data = {
                        success: false,
                        message:
                            'WAI sunucusundan geçerli bir cevap alınamadı.'
                    };

                }

                if (!response.ok) {

                    const apiError =
                        new Error(
                            data?.message ||
                            'Demo cevabı alınamadı.'
                        );

                    apiError.status =
                        response.status;

                    apiError.data =
                        data;

                    throw apiError;

                }

                return data;

            };

        /*
        |--------------------------------------------------------------------------
        | SEND MESSAGE
        |--------------------------------------------------------------------------
        */

        const sendDemoMessage =
            async message => {

                const userMessage =
                    clean(message);

                if (!userMessage) {
                    return;
                }

                if (isSending) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Limit
                |--------------------------------------------------------------------------
                */

                if (
                    remainingMessages <= 0
                ) {

                    conversion.classList.add(
                        'show'
                    );

                    conversion.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Kullanıcı mesajını göster.
                |--------------------------------------------------------------------------
                */

                addMessage(
                    userMessage,
                    'user'
                );

                chatInput.value = '';

                setSendingState(true);

                addTyping();

                /*
                |--------------------------------------------------------------------------
                | API
                |--------------------------------------------------------------------------
                */

                try {

                    const data =
                        await requestWaiResponse(
                            userMessage
                        );

                    removeTyping();

                    if (
                        !data ||
                        data.success !== true ||
                        !clean(data.message)
                    ) {

                        throw new Error(
                            data?.message ||
                            'WAI boş cevap döndürdü.'
                        );

                    }

                    const aiMessage =
                        clean(data.message);

                    /*
                    |--------------------------------------------------------------------------
                    | AI Message
                    |--------------------------------------------------------------------------
                    */

                    addMessage(
                        aiMessage,
                        'wai'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Conversation memory
                    |--------------------------------------------------------------------------
                    */

                    conversationHistory.push(
                        {
                            role: 'user',
                            content: userMessage
                        },
                        {
                            role: 'assistant',
                            content: aiMessage
                        }
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Browser'da geçmişi büyütme.
                    |--------------------------------------------------------------------------
                    */

                    if (
                        conversationHistory.length >
                        10
                    ) {

                        conversationHistory =
                            conversationHistory.slice(
                                -10
                            );

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Backend limit
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Number.isInteger(
                            data.remaining_messages
                        )
                    ) {

                        remainingMessages =
                            Math.max(
                                0,
                                data.remaining_messages
                            );

                    } else {

                        remainingMessages =
                            Math.max(
                                0,
                                remainingMessages - 1
                            );

                    }

                    remainingElement.textContent =
                        remainingMessages;

                    /*
                    |--------------------------------------------------------------------------
                    | CTA
                    |--------------------------------------------------------------------------
                    */

                    if (
                        remainingMessages <= 2 ||
                        data.limit_reached === true
                    ) {

                        conversion.classList.add(
                            'show'
                        );

                    }

                    if (
                        remainingMessages <= 0
                    ) {

                        chatInput.placeholder =
                            'Demo mesaj hakkınız tamamlandı.';

                        conversion.classList.add(
                            'show'
                        );

                    }

                } catch (error) {

                    removeTyping();

                    console.error(
                        'WAI Demo Error:',
                        error
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 419
                    |--------------------------------------------------------------------------
                    */

                    if (
                        error.status === 419
                    ) {

                        addErrorMessage(
                            'Demo oturumunun süresi doldu. Sayfayı yenileyip tekrar deneyin.'
                        );

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 422
                    |--------------------------------------------------------------------------
                    */

                    else if (
                        error.status === 422
                    ) {

                        addErrorMessage(
                            'İşletme bilgilerinde eksik bir alan var. Bilgileri kontrol edip tekrar deneyin.'
                        );

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 429
                    |--------------------------------------------------------------------------
                    */

                    else if (
                        error.status === 429
                    ) {

                        remainingMessages = 0;

                        remainingElement.textContent =
                            '0';

                        chatInput.placeholder =
                            'Demo mesaj hakkınız tamamlandı.';

                        conversion.classList.add(
                            'show'
                        );

                        addErrorMessage(
                            error.data?.message ||
                            'Ücretsiz demo mesaj hakkınız tamamlandı.'
                        );

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 503
                    |--------------------------------------------------------------------------
                    */

                    else if (
                        error.status === 503
                    ) {

                        addErrorMessage(
                            error.message ||
                            'Yapay zekâ servisine şu anda ulaşılamıyor. Lütfen biraz sonra tekrar deneyin.'
                        );

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Genel
                    |--------------------------------------------------------------------------
                    */

                    else {

                        addErrorMessage(
                            error.message ||
                            'WAI cevap verirken geçici bir sorun oluştu. Lütfen tekrar deneyin.'
                        );

                    }

                } finally {

                    setSendingState(
                        false
                    );

                    if (
                        remainingMessages > 0
                    ) {

                        setTimeout(() => {
                            chatInput?.focus();
                        }, 100);

                    }

                }

            };

        /*
        |--------------------------------------------------------------------------
        | CHAT FORM
        |--------------------------------------------------------------------------
        */

        chatForm?.addEventListener(
            'submit',
            event => {

                event.preventDefault();

                sendDemoMessage(
                    chatInput.value
                );

            }
        );

        /*
        |--------------------------------------------------------------------------
        | QUICK MESSAGES
        |--------------------------------------------------------------------------
        */

        quickButtons.forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    sendDemoMessage(
                        button.dataset
                            .wdQuick || ''
                    );

                }
            );

        });

        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        showStep(1);

    });
</script>