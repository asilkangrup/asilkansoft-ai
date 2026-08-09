<x-filament-panels::page>

<style>
    .setup-wrap {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .setup-hero {
        position: relative;
        overflow: hidden;
        padding: 32px;
        border-radius: 24px;
        color: #ffffff;
        background:
            radial-gradient(circle at top right, rgba(59, 130, 246, .40), transparent 32%),
            linear-gradient(135deg, #111827 0%, #172554 55%, #1e3a8a 100%);
        box-shadow: 0 20px 45px rgba(15, 23, 42, .18);
    }

    .setup-hero-inner {
        position: relative;
        z-index: 2;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 32px;
        align-items: center;
    }

    .setup-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 12px;
        padding: 7px 12px;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 999px;
        background: rgba(255,255,255,.08);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
    }

    .setup-title {
        margin: 0;
        font-size: 30px;
        line-height: 1.2;
        font-weight: 800;
    }

    .setup-subtitle {
        max-width: 680px;
        margin: 12px 0 0;
        color: rgba(255,255,255,.76);
        font-size: 15px;
        line-height: 1.7;
    }

    .progress-card {
        padding: 22px;
        border: 1px solid rgba(255,255,255,.15);
        border-radius: 20px;
        background: rgba(255,255,255,.10);
        backdrop-filter: blur(10px);
    }

    .progress-top {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
    }

    .progress-label {
        color: rgba(255,255,255,.7);
        font-size: 12px;
        font-weight: 600;
    }

    .progress-value {
        margin-top: 4px;
        font-size: 34px;
        line-height: 1;
        font-weight: 800;
    }

    .progress-count {
        color: rgba(255,255,255,.75);
        font-size: 13px;
        font-weight: 600;
    }

    .progress-track {
        width: 100%;
        height: 10px;
        margin-top: 18px;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(255,255,255,.16);
    }

    .progress-bar {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #38bdf8, #22c55e);
        transition: width .4s ease;
    }

    /*
    |--------------------------------------------------------------------------
    | DENEME / PAKET KARTI
    |--------------------------------------------------------------------------
    */

    .trial-card {
        position: relative;
        overflow: hidden;
        padding: 24px;
        border: 1px solid #bfdbfe;
        border-radius: 22px;
        background:
            radial-gradient(circle at top right, rgba(59,130,246,.10), transparent 35%),
            #ffffff;
        box-shadow: 0 8px 25px rgba(15,23,42,.06);
    }

    .trial-card.expired {
        border-color: #fecaca;
        background:
            radial-gradient(circle at top right, rgba(239,68,68,.08), transparent 35%),
            #ffffff;
    }

    .trial-card.active {
        border-color: #bbf7d0;
        background:
            radial-gradient(circle at top right, rgba(34,197,94,.10), transparent 35%),
            #ffffff;
    }

    .trial-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
    }

    .trial-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 11px;
        font-weight: 800;
    }

    .trial-card.expired .trial-badge {
        background: #fee2e2;
        color: #b91c1c;
    }

    .trial-card.active .trial-badge {
        background: #dcfce7;
        color: #166534;
    }

    .trial-title {
        margin: 12px 0 0;
        color: #111827;
        font-size: 20px;
        font-weight: 800;
    }

    .trial-description {
        margin: 7px 0 0;
        max-width: 700px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.7;
    }

    .trial-number {
        flex-shrink: 0;
        text-align: right;
    }

    .trial-number strong {
        display: block;
        color: #111827;
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
    }

    .trial-number span {
        display: block;
        margin-top: 6px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 700;
    }

    .trial-progress-track {
        width: 100%;
        height: 12px;
        margin-top: 22px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5e7eb;
    }

    .trial-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3b82f6, #2563eb);
        transition: width .4s ease;
    }

    .trial-card.expired .trial-progress-bar {
        background: linear-gradient(90deg, #f97316, #ef4444);
    }

    .trial-card.active .trial-progress-bar {
        background: linear-gradient(90deg, #22c55e, #16a34a);
    }

    .trial-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-top: 16px;
    }

    .trial-info {
        color: #4b5563;
        font-size: 12px;
        font-weight: 700;
    }

    .trial-remaining {
        color: #2563eb;
        font-size: 12px;
        font-weight: 800;
    }

    .trial-card.expired .trial-remaining {
        color: #dc2626;
    }

    .trial-card.active .trial-remaining {
        color: #16a34a;
    }

    .buy-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 46px;
        padding: 0 18px;
        border-radius: 12px;
        background: #dc2626;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 800;
        transition: .2s ease;
    }

    .buy-button:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    .active-plan-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        padding: 0 16px;
        border-radius: 12px;
        background: #dcfce7;
        color: #166534;
        font-size: 12px;
        font-weight: 800;
    }

    /*
    |--------------------------------------------------------------------------
    | KURULUM TAMAMLANDI
    |--------------------------------------------------------------------------
    */

    .setup-complete {
        display: flex;
        gap: 14px;
        align-items: center;
        padding: 18px 20px;
        border: 1px solid #bbf7d0;
        border-radius: 18px;
        background: #f0fdf4;
    }

    .complete-icon {
        display: flex;
        width: 44px;
        height: 44px;
        flex: 0 0 44px;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #dcfce7;
        font-size: 22px;
    }

    .complete-title {
        margin: 0;
        color: #166534;
        font-size: 15px;
        font-weight: 800;
    }

    .complete-text {
        margin: 4px 0 0;
        color: #15803d;
        font-size: 13px;
    }

    /*
    |--------------------------------------------------------------------------
    | KURULUM ADIMLARI
    |--------------------------------------------------------------------------
    */

    .section-heading {
        margin: 4px 0 0;
    }

    .section-heading h2 {
        margin: 0;
        color: #111827;
        font-size: 21px;
        font-weight: 800;
    }

    .section-heading p {
        margin: 6px 0 0;
        color: #6b7280;
        font-size: 14px;
    }

    .steps-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .setup-step {
        position: relative;
        overflow: hidden;
        min-height: 235px;
        padding: 24px;
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #ffffff;
        box-shadow: 0 5px 20px rgba(15, 23, 42, .04);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .setup-step:hover {
        transform: translateY(-2px);
        border-color: #bfdbfe;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .09);
    }

    .setup-step.done {
        border-color: #bbf7d0;
        background: linear-gradient(145deg, #ffffff, #f0fdf4);
    }

    .step-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .step-icon {
        display: flex;
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        background: #f3f4f6;
        font-size: 24px;
    }

    .done .step-icon {
        background: #dcfce7;
    }

    .step-status {
        padding: 6px 10px;
        border-radius: 999px;
        background: #fef3c7;
        color: #92400e;
        font-size: 11px;
        font-weight: 800;
    }

    .done .step-status {
        background: #dcfce7;
        color: #166534;
    }

    .step-number {
        margin-top: 18px;
        color: #9ca3af;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .step-title {
        margin: 6px 0 0;
        color: #111827;
        font-size: 18px;
        font-weight: 800;
    }

    .step-description {
        min-height: 46px;
        margin: 8px 0 0;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.7;
    }

    .step-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        padding: 10px 15px;
        border-radius: 11px;
        background: #2563eb;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 800;
        transition: background .2s ease, transform .2s ease;
    }

    .step-action:hover {
        background: #1d4ed8;
        transform: translateX(2px);
    }

    .done .step-action {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151 !important;
    }

    /*
    |--------------------------------------------------------------------------
    | DESTEK
    |--------------------------------------------------------------------------
    */

    .support-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 22px;
        padding: 22px 24px;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        background: #f9fafb;
    }

    .support-card h3 {
        margin: 0;
        color: #111827;
        font-size: 15px;
        font-weight: 800;
    }

    .support-card p {
        margin: 5px 0 0;
        color: #6b7280;
        font-size: 13px;
    }

    .support-button {
        flex-shrink: 0;
        padding: 11px 17px;
        border-radius: 11px;
        background: #111827;
        color: #ffffff !important;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 800;
    }

    @media (max-width: 900px) {
        .setup-hero-inner {
            grid-template-columns: 1fr;
        }

        .steps-grid {
            grid-template-columns: 1fr;
        }

        .trial-head,
        .trial-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .trial-number {
            text-align: left;
        }
    }

    @media (max-width: 600px) {
        .setup-hero {
            padding: 22px;
            border-radius: 18px;
        }

        .setup-title {
            font-size: 24px;
        }

        .setup-step {
            min-height: auto;
            padding: 20px;
        }

        .support-card {
            align-items: stretch;
            flex-direction: column;
        }

        .support-button {
            text-align: center;
        }

        .trial-card {
            padding: 20px;
        }

        .buy-button {
            width: 100%;
        }
    }
</style>


<div class="setup-wrap">

    {{-- ÜST KARŞILAMA --}}
    <section class="setup-hero">

        <div class="setup-hero-inner">

            <div>

                <div class="setup-kicker">
                    ✦ ASİLKANSOFT AI
                </div>

                <h2 class="setup-title">
                    Hoş geldiniz, {{ $user->name }} 👋
                </h2>

                <p class="setup-subtitle">
                    Yapay zekâ asistanınızı kullanıma hazırlamak yalnızca birkaç adım sürer.
                    Kurulumu tamamlayın ve WhatsApp üzerinden müşterilerinizle otomatik görüşmeye başlayın.
                </p>

            </div>


            <div class="progress-card">

                <div class="progress-top">

                    <div>

                        <div class="progress-label">
                            KURULUM İLERLEMESİ
                        </div>

                        <div class="progress-value">
                            %{{ $progress }}
                        </div>

                    </div>

                    <div class="progress-count">
                        {{ $completedCount }} / {{ $totalSteps }} tamamlandı
                    </div>

                </div>

                <div class="progress-track">

                    <div
                        class="progress-bar"
                        style="width: {{ $progress }}%;"
                    ></div>

                </div>

            </div>

        </div>

    </section>


    {{-- ÜCRETSİZ DENEME / PAKET --}}
    @if ($bot)

        <section
            class="trial-card
                {{ $trialCompleted ? 'expired' : '' }}
                {{ $subscriptionActive ? 'active' : '' }}"
        >

            <div class="trial-head">

                <div>

                    <div class="trial-badge">

                        @if ($subscriptionActive)
                            ✓ ÜCRETLİ PAKET
                        @elseif ($trialCompleted)
                            ⚠ DENEME TAMAMLANDI
                        @else
                            🎁 ÜCRETSİZ DENEME
                        @endif

                    </div>

                    <h3 class="trial-title">
                        {{ $planTitle }}
                    </h3>

                    <p class="trial-description">
                        {{ $planDescription }}
                    </p>

                </div>


                <div class="trial-number">

                    @if ($subscriptionActive)

                        <strong>
                            Aktif
                        </strong>

                        <span>
                            WhatsApp AI kullanımı
                        </span>

                    @else

                        <strong>
                            {{ $trialMessagesUsed }} / {{ $trialMessageLimit }}
                        </strong>

                        <span>
                            WhatsApp AI cevabı kullanıldı
                        </span>

                    @endif

                </div>

            </div>


            @if (! $subscriptionActive)

                <div class="trial-progress-track">

                    <div
                        class="trial-progress-bar"
                        style="width: {{ $trialProgress }}%;"
                    ></div>

                </div>


                <div class="trial-footer">

                    <div>

                        @if ($trialCompleted)

    <a
        href="https://wa.me/905392389098?text={{ urlencode('Merhaba, WhatsApp Yapay Zeka paketini satın almak istiyorum. Paketler hakkında bilgi alabilir miyim?') }}"
        target="_blank"
        rel="noopener noreferrer"
        class="buy-button"
    >
        Paketi Satın Al
        <span>→</span>
    </a>

@endif

                    </div>


                    @if ($trialCompleted)

                        <a
                            href="#"
                            class="buy-button"
                        >
                            Paketi Satın Al
                            <span>→</span>
                        </a>

                    @endif

                </div>

            @else

                <div class="trial-footer">

                    <div>

                        <div class="trial-remaining">
                            Yapay zekânız aktif
                        </div>

                        <div class="trial-info">
                            WhatsApp üzerinden müşterilerinize yanıt vermeye devam ediyor.
                        </div>

                    </div>

                    <div class="active-plan-button">
                        ✓ Paket Aktif
                    </div>

                </div>

            @endif

        </section>

    @endif


    {{-- KURULUM TAMAMLANDI --}}
    @if ($progress === 100)

        <div class="setup-complete">

            <div class="complete-icon">
                ✓
            </div>

            <div>

                <h3 class="complete-title">
                    Kurulumunuz tamamlandı
                </h3>

                <p class="complete-text">
                    Yapay zekâ sisteminiz kullanıma hazır.
                </p>

            </div>

        </div>

    @endif


    {{-- KURULUM ADIMLARI --}}
    <div class="section-heading">

        <h2>
            Kurulum Adımları
        </h2>

        <p>
            Sisteminizin eksik adımlarını aşağıdan tamamlayabilirsiniz.
        </p>

    </div>


    <div class="steps-grid">

        @foreach ($steps as $index => $step)

            <article class="setup-step {{ $step['completed'] ? 'done' : '' }}">

                <div class="step-top">

                    <div class="step-icon">
                        {{ $step['icon'] }}
                    </div>

                    <div class="step-status">
                        {{ $step['completed'] ? '✓ Tamamlandı' : 'Bekliyor' }}
                    </div>

                </div>


                <div class="step-number">
                    Adım {{ $index + 1 }}
                </div>


                <h3 class="step-title">
                    {{ $step['title'] }}
                </h3>


                <p class="step-description">
                    {{ $step['description'] }}
                </p>


                <a
                    class="step-action"
                    href="{{ $step['url'] }}"
                >
                    {{ $step['button'] }}

                    <span>
                        →
                    </span>
                </a>

            </article>

        @endforeach

    </div>


    {{-- DESTEK --}}
    <div class="support-card">

        <div>

            <h3>
                Kurulum sırasında yardıma mı ihtiyacınız var?
            </h3>

            <p>
                AsilkanSoft destek ekibi kurulum sürecinin her aşamasında yanınızda.
            </p>

        </div>

        <a
            class="support-button"
            href="#"
        >
            Destek Al
        </a>

    </div>

</div>

</x-filament-panels::page>