<x-filament-panels::page>

    @php
        /*
        |--------------------------------------------------------------------------
        | WAI KURULUM MERKEZİ
        |--------------------------------------------------------------------------
        |
        | Backend iş mantığı değiştirilmez.
        | KurulumMerkezi.php tarafından hazırlanan mevcut veriler kullanılır.
        |
        */

        $nextStep = collect($steps)->first(
            fn (array $step) => ! $step['completed']
        );

        $nextStepIndex = collect($steps)->search(
            fn (array $step) => ! $step['completed']
        );

        $nextStepNumber = $nextStepIndex !== false
            ? $nextStepIndex + 1
            : null;

        $allCompleted = $progress === 100;

        $firstName = trim(
            explode(' ', $user->name ?? 'WAI Kullanıcısı')[0] ?? ''
        );
    @endphp

    <style>
        /*
        |--------------------------------------------------------------------------
        | WAI PREMIUM LIGHT SETUP CENTER
        |--------------------------------------------------------------------------
        */

        .wai-setup {
            --wai-green: #24d978;
            --wai-green-dark: #0f9f53;
            --wai-green-deep: #08713a;

            --wai-green-50: #f1fff7;
            --wai-green-100: #e4faed;
            --wai-green-200: #c7f4d9;

            --wai-bg: #f7f9f8;
            --wai-card: #ffffff;
            --wai-card-soft: #fafcfb;

            --wai-title: #111714;
            --wai-text: #34413a;
            --wai-muted: #758078;
            --wai-muted-2: #a0aaa4;

            --wai-border: #e4e9e6;
            --wai-border-dark: #d7ded9;

            --wai-shadow:
                0 16px 45px rgba(18, 35, 25, .065);

            --wai-shadow-soft:
                0 7px 24px rgba(18, 35, 25, .045);

            width: 100%;
            max-width: 1220px;

            margin: 0 auto;

            color: var(--wai-text);
        }

        .wai-setup *,
        .wai-setup *::before,
        .wai-setup *::after {
            box-sizing: border-box;
        }

        /*
        |--------------------------------------------------------------------------
        | SHELL
        |--------------------------------------------------------------------------
        */

        .wai-shell {
            display: flex;
            flex-direction: column;

            gap: 18px;

            padding-bottom: 30px;
        }

        /*
        |--------------------------------------------------------------------------
        | HERO
        |--------------------------------------------------------------------------
        */

        .wai-hero {
            position: relative;

            overflow: hidden;

            padding: 36px;

            border: 1px solid var(--wai-border);

            border-radius: 28px;

            background:
                radial-gradient(
                    circle at 97% 0%,
                    rgba(36, 217, 120, .11),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 4% 100%,
                    rgba(36, 217, 120, .035),
                    transparent 25%
                ),
                #ffffff;

            box-shadow: var(--wai-shadow);
        }

        .wai-hero::before {
            content: "";

            position: absolute;

            width: 420px;
            height: 420px;

            right: -250px;
            bottom: -290px;

            border-radius: 50%;

            border: 1px solid rgba(36, 217, 120, .10);

            box-shadow:
                0 0 0 55px rgba(36, 217, 120, .025);
        }

        .wai-hero-grid {
            position: relative;

            z-index: 2;

            display: grid;

            grid-template-columns:
                minmax(0, 1.15fr)
                minmax(300px, .85fr);

            gap: 45px;

            align-items: center;
        }

        /*
        |--------------------------------------------------------------------------
        | HERO COPY
        |--------------------------------------------------------------------------
        */

        .wai-kicker {
            min-height: 34px;

            padding: 0 12px;

            display: inline-flex;
            align-items: center;

            gap: 8px;

            border: 1px solid var(--wai-green-200);

            border-radius: 999px;

            color: var(--wai-green-deep);

            background: var(--wai-green-50);

            font-size: 10px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .wai-kicker-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: var(--wai-green);

            box-shadow:
                0 0 0 4px rgba(36, 217, 120, .10);
        }

        .wai-hero-title {
            max-width: 710px;

            margin: 20px 0 0;

            color: var(--wai-title);

            font-size:
                clamp(
                    38px,
                    4vw,
                    56px
                );

            line-height: .99;

            font-weight: 800;

            letter-spacing: -3px;
        }

        .wai-hero-title span {
            display: block;

            margin-top: 6px;

            color: var(--wai-green-dark);
        }

        .wai-hero-copy {
            max-width: 650px;

            margin: 18px 0 0;

            color: var(--wai-muted);

            font-size: 15px;

            line-height: 1.7;
        }

        /*
        |--------------------------------------------------------------------------
        | PROGRESS PANEL
        |--------------------------------------------------------------------------
        */

        .wai-progress {
            position: relative;

            overflow: hidden;

            padding: 24px;

            border: 1px solid var(--wai-border);

            border-radius: 21px;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #f8fbf9
                );

            box-shadow: var(--wai-shadow-soft);
        }

        .wai-progress::before {
            content: "";

            position: absolute;

            width: 170px;
            height: 170px;

            right: -75px;
            top: -90px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(36, 217, 120, .11),
                    transparent 67%
                );
        }

        .wai-progress-header {
            position: relative;

            display: flex;
            align-items: flex-end;
            justify-content: space-between;

            gap: 15px;
        }

        .wai-progress-label {
            color: var(--wai-muted);

            font-size: 10px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .wai-progress-value {
            margin-top: 7px;

            color: var(--wai-title);

            font-size: 44px;

            line-height: 1;

            font-weight: 800;

            letter-spacing: -2px;
        }

        .wai-progress-value b {
            color: var(--wai-green);

            font-weight: inherit;
        }

        .wai-progress-count {
            padding-bottom: 5px;

            color: var(--wai-muted);

            font-size: 11px;

            font-weight: 700;
        }

        .wai-progress-track {
            height: 8px;

            margin-top: 20px;

            overflow: hidden;

            border-radius: 999px;

            background: #edf1ef;
        }

        .wai-progress-bar {
            height: 100%;

            border-radius: inherit;

            background:
                linear-gradient(
                    90deg,
                    #19c96a,
                    #49e890
                );

            box-shadow:
                0 3px 12px rgba(36, 217, 120, .20);

            transition: width .4s ease;
        }

        .wai-progress-footer {
            display: flex;
            align-items: center;

            gap: 7px;

            margin-top: 12px;

            color: var(--wai-muted);

            font-size: 10px;
        }

        .wai-progress-footer i {
            width: 6px;
            height: 6px;

            border-radius: 50%;

            background:
                {{ $allCompleted ? '#24d978' : '#a2aca6' }};
        }

        /*
        |--------------------------------------------------------------------------
        | NEXT STEP
        |--------------------------------------------------------------------------
        */

        .wai-next {
            position: relative;

            overflow: hidden;

            padding: 28px 30px;

            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                auto;

            align-items: center;

            gap: 30px;

            border: 1px solid #bcefcf;

            border-radius: 23px;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(36, 217, 120, .12),
                    transparent 32%
                ),
                linear-gradient(
                    145deg,
                    #f5fff9,
                    #ecfbf2
                );

            box-shadow:
                0 15px 40px rgba(25, 120, 67, .075);
        }

        .wai-next::after {
            content: "";

            position: absolute;

            width: 230px;
            height: 230px;

            right: -120px;
            bottom: -155px;

            border-radius: 50%;

            border:
                1px solid rgba(36, 217, 120, .15);
        }

        .wai-next-label {
            position: relative;

            display: flex;
            align-items: center;

            gap: 8px;

            color: var(--wai-green-deep);

            font-size: 10px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .wai-next-label i {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: var(--wai-green);

            box-shadow:
                0 0 0 4px rgba(36,217,120,.10);
        }

        .wai-next-title {
            position: relative;

            margin: 10px 0 0;

            color: var(--wai-title);

            font-size: 25px;

            line-height: 1.1;

            font-weight: 800;

            letter-spacing: -.8px;
        }

        .wai-next-copy {
            position: relative;

            max-width: 700px;

            margin: 8px 0 0;

            color: #64756b;

            font-size: 13px;

            line-height: 1.65;
        }

        /*
        |--------------------------------------------------------------------------
        | PRIMARY BUTTON
        |--------------------------------------------------------------------------
        */

        .wai-primary-button {
            position: relative;

            z-index: 3;

            min-width: 200px;
            min-height: 56px;

            padding:
                0 10px 0 18px;

            display: inline-flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            border-radius: 14px;

            color: #06371d !important;

            background:
                linear-gradient(
                    135deg,
                    #56e996,
                    #31dc7b
                );

            box-shadow:
                0 12px 28px rgba(36, 217, 120, .18);

            text-decoration: none !important;

            font-size: 12px;

            font-weight: 800;

            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }

        .wai-primary-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 17px 35px rgba(36, 217, 120, .22);
        }

        .wai-primary-button b {
            width: 35px;
            height: 35px;

            display: grid;
            place-items: center;

            border-radius: 10px;

            background:
                rgba(255,255,255,.22);

            font-size: 13px;
        }

        /*
        |--------------------------------------------------------------------------
        | COMPLETE
        |--------------------------------------------------------------------------
        */

        .wai-ready {
            padding: 24px;

            display: grid;

            grid-template-columns:
                52px minmax(0,1fr) auto;

            align-items: center;

            gap: 17px;

            border: 1px solid #bcefcf;

            border-radius: 21px;

            background:
                linear-gradient(
                    145deg,
                    #f5fff9,
                    #ecfbf2
                );
        }

        .wai-ready-icon {
            width: 52px;
            height: 52px;

            display: grid;
            place-items: center;

            border-radius: 15px;

            color: var(--wai-green-dark);

            background: #dff8e9;

            font-size: 19px;
        }

        .wai-ready-title {
            margin: 0;

            color: var(--wai-title);

            font-size: 20px;

            font-weight: 800;
        }

        .wai-ready-copy {
            margin: 5px 0 0;

            color: var(--wai-muted);

            font-size: 12px;
        }

        .wai-ready-button {
            min-height: 48px;

            padding: 0 16px;

            display: inline-flex;
            align-items: center;

            gap: 8px;

            border-radius: 12px;

            color: #07361e !important;

            background: #55e996;

            text-decoration: none !important;

            font-size: 11px;

            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | TRIAL / PLAN
        |--------------------------------------------------------------------------
        */

        .wai-plan {
            position: relative;

            overflow: hidden;

            padding: 24px 26px;

            border: 1px solid var(--wai-border);

            border-radius: 21px;

            background: #ffffff;

            box-shadow: var(--wai-shadow-soft);
        }

        .wai-plan.plan-active {
            border-color: #c2efd3;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(36,217,120,.07),
                    transparent 32%
                ),
                #ffffff;
        }

        .wai-plan.trial-expired {
            border-color: #ffd7d7;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(235,75,75,.055),
                    transparent 32%
                ),
                #ffffff;
        }

        .wai-plan-head {
            display: flex;

            align-items: flex-start;
            justify-content: space-between;

            gap: 25px;
        }

        .wai-plan-badge {
            min-height: 30px;

            padding: 0 11px;

            display: inline-flex;
            align-items: center;

            gap: 6px;

            border-radius: 999px;

            color: var(--wai-green-deep);

            background: var(--wai-green-100);

            font-size: 9px;

            font-weight: 800;

            letter-spacing: .7px;
        }

        .trial-expired .wai-plan-badge {
            color: #b73737;

            background: #fff0f0;
        }

        .wai-plan-title {
            margin: 12px 0 0;

            color: var(--wai-title);

            font-size: 20px;

            font-weight: 800;

            letter-spacing: -.5px;
        }

        .wai-plan-description {
            max-width: 710px;

            margin: 7px 0 0;

            color: var(--wai-muted);

            font-size: 12px;

            line-height: 1.65;
        }

        .wai-plan-usage {
            flex-shrink: 0;

            text-align: right;
        }

        .wai-plan-usage strong {
            display: block;

            color: var(--wai-title);

            font-size: 28px;

            line-height: 1;

            font-weight: 800;

            letter-spacing: -1px;
        }

        .wai-plan-usage span {
            display: block;

            margin-top: 6px;

            color: var(--wai-muted);

            font-size: 10px;

            font-weight: 700;
        }

        .wai-trial-track {
            height: 7px;

            margin-top: 19px;

            overflow: hidden;

            border-radius: 999px;

            background: #edf1ef;
        }

        .wai-trial-bar {
            height: 100%;

            border-radius: inherit;

            background:
                linear-gradient(
                    90deg,
                    #19c96a,
                    #4ae890
                );
        }

        .trial-expired .wai-trial-bar {
            background:
                linear-gradient(
                    90deg,
                    #ed7b57,
                    #e65050
                );
        }

        .wai-plan-bottom {
            margin-top: 14px;

            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .wai-plan-remaining {
            color: var(--wai-green-deep);

            font-size: 11px;

            font-weight: 800;
        }

        .trial-expired .wai-plan-remaining {
            color: #c44141;
        }

        .wai-plan-info {
            max-width: 700px;

            margin-top: 5px;

            color: var(--wai-muted);

            font-size: 10px;

            line-height: 1.55;
        }

        .wai-buy-button {
            min-height: 46px;

            padding: 0 16px;

            display: inline-flex;
            align-items: center;

            gap: 8px;

            flex-shrink: 0;

            border-radius: 11px;

            color: #ffffff !important;

            background:
                linear-gradient(
                    135deg,
                    #e85252,
                    #c83b3b
                );

            text-decoration: none !important;

            font-size: 11px;

            font-weight: 800;
        }

        .wai-active-pill {
            min-height: 42px;

            padding: 0 14px;

            display: inline-flex;
            align-items: center;

            border: 1px solid #c2efd3;

            border-radius: 11px;

            color: var(--wai-green-deep);

            background: var(--wai-green-50);

            font-size: 10px;

            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | STEPS PANEL
        |--------------------------------------------------------------------------
        */

        .wai-steps-panel {
            position: relative;

            overflow: hidden;

            padding: 27px;

            border: 1px solid var(--wai-border);

            border-radius: 24px;

            background: #ffffff;

            box-shadow: var(--wai-shadow-soft);
        }

        .wai-steps-panel::before {
            content: "";

            position: absolute;

            width: 360px;
            height: 360px;

            right: -220px;
            top: -250px;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(36,217,120,.07),
                    transparent 68%
                );
        }

        /*
        |--------------------------------------------------------------------------
        | STEPS HEADER
        |--------------------------------------------------------------------------
        */

        .wai-section-head {
            position: relative;

            z-index: 2;

            display: flex;

            align-items: flex-end;
            justify-content: space-between;

            gap: 20px;

            margin-bottom: 22px;
        }

        .wai-section-kicker {
            display: inline-flex;
            align-items: center;

            gap: 7px;

            color: var(--wai-green-deep);

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1px;
        }

        .wai-section-kicker::before {
            content: "";

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: var(--wai-green);
        }

        .wai-section-title {
            margin: 8px 0 0;

            color: var(--wai-title);

            font-size: 24px;

            font-weight: 800;

            letter-spacing: -.8px;
        }

        .wai-section-copy {
            margin: 7px 0 0;

            color: var(--wai-muted);

            font-size: 12px;

            line-height: 1.55;
        }

        .wai-section-status {
            color: var(--wai-muted);

            font-size: 10px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .7px;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP LIST
        |--------------------------------------------------------------------------
        */

        .wai-step-list {
            position: relative;

            z-index: 2;

            display: flex;
            flex-direction: column;

            gap: 10px;
        }

        .wai-step {
            min-height: 116px;

            padding: 19px 20px;

            display: grid;

            grid-template-columns:
                52px
                minmax(0,1fr)
                auto;

            align-items: center;

            gap: 17px;

            border: 1px solid var(--wai-border);

            border-radius: 18px;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #fbfcfb
                );

            transition:
                transform .2s ease,
                border-color .2s ease,
                box-shadow .2s ease;
        }

        .wai-step:hover {
            transform: translateY(-1px);

            border-color: #cfe8d8;

            box-shadow:
                0 8px 22px rgba(18,35,25,.045);
        }

        .wai-step.done {
            border-color: #d4eddd;

            background:
                linear-gradient(
                    145deg,
                    #ffffff,
                    #f9fffb
                );
        }

        .wai-step.current {
            border-color: #a8eac0;

            background:
                radial-gradient(
                    circle at 100% 0%,
                    rgba(36,217,120,.08),
                    transparent 32%
                ),
                linear-gradient(
                    145deg,
                    #f8fff9,
                    #effcf4
                );

            box-shadow:
                inset 4px 0 0 var(--wai-green),
                0 8px 24px rgba(36,217,120,.065);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP NUMBER
        |--------------------------------------------------------------------------
        */

        .wai-step-number {
            width: 52px;
            height: 52px;

            display: grid;
            place-items: center;

            border: 1px solid var(--wai-border);

            border-radius: 15px;

            color: var(--wai-muted);

            background: #f7f9f8;

            font-size: 12px;

            font-weight: 800;
        }

        .wai-step.done .wai-step-number {
            border-color: #c9edd7;

            color: var(--wai-green-deep);

            background: var(--wai-green-50);
        }

        .wai-step.current .wai-step-number {
            border-color: transparent;

            color: #06351d;

            background: var(--wai-green);

            box-shadow:
                0 8px 20px rgba(36,217,120,.16);
        }

        /*
        |--------------------------------------------------------------------------
        | STEP BODY
        |--------------------------------------------------------------------------
        */

        .wai-step-body {
            min-width: 0;
        }

        .wai-step-meta {
            display: flex;
            align-items: center;

            gap: 8px;

            margin-bottom: 7px;
        }

        .wai-step-index {
            color: var(--wai-muted-2);

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .8px;
        }

        .wai-step-status {
            min-height: 23px;

            padding: 0 8px;

            display: inline-flex;
            align-items: center;

            border-radius: 999px;

            color: var(--wai-muted);

            background: #f3f5f4;

            font-size: 8px;

            font-weight: 800;
        }

        .wai-step.done .wai-step-status {
            color: var(--wai-green-deep);

            background: var(--wai-green-100);
        }

        .wai-step.current .wai-step-status {
            color: var(--wai-green-deep);

            background: #dff8e9;
        }

        .wai-step-title {
            margin: 0;

            color: var(--wai-title);

            font-size: 17px;

            line-height: 1.2;

            font-weight: 800;

            letter-spacing: -.25px;
        }

        .wai-step-description {
            max-width: 720px;

            margin: 7px 0 0;

            color: var(--wai-muted);

            font-size: 12px;

            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | STEP BUTTON
        |--------------------------------------------------------------------------
        */

        .wai-step-button {
            min-width: 170px;
            min-height: 48px;

            padding:
                0 10px 0 15px;

            display: inline-flex;
            align-items: center;
            justify-content: space-between;

            gap: 10px;

            border: 1px solid var(--wai-border-dark);

            border-radius: 12px;

            color: #46534b !important;

            background: #ffffff;

            text-decoration: none !important;

            font-size: 10px;

            font-weight: 800;

            transition:
                border-color .2s ease,
                background .2s ease;
        }

        .wai-step-button:hover {
            border-color: #ade8c3;

            color: var(--wai-green-deep) !important;

            background: var(--wai-green-50);
        }

        .wai-step.current .wai-step-button {
            border-color: transparent;

            color: #06351d !important;

            background:
                linear-gradient(
                    135deg,
                    #58ea98,
                    #35dc7d
                );

            box-shadow:
                0 9px 20px rgba(36,217,120,.12);
        }

        .wai-step-button b {
            width: 28px;
            height: 28px;

            display: grid;
            place-items: center;

            border-radius: 8px;

            background: #f2f5f3;

            font-size: 11px;
        }

        .wai-step.current .wai-step-button b {
            background:
                rgba(255,255,255,.20);
        }

        /*
        |--------------------------------------------------------------------------
        | SUPPORT
        |--------------------------------------------------------------------------
        */

        .wai-support {
            padding: 21px 22px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 24px;

            border: 1px solid var(--wai-border);

            border-radius: 18px;

            background: #ffffff;

            box-shadow: var(--wai-shadow-soft);
        }

        .wai-support-title {
            margin: 0;

            color: var(--wai-title);

            font-size: 14px;

            font-weight: 800;
        }

        .wai-support-copy {
            margin: 6px 0 0;

            color: var(--wai-muted);

            font-size: 10px;

            line-height: 1.5;
        }

        .wai-support-button {
            min-height: 44px;

            padding: 0 15px;

            display: inline-flex;
            align-items: center;

            flex-shrink: 0;

            border: 1px solid var(--wai-border-dark);

            border-radius: 11px;

            color: #48564d !important;

            background: #f8faf9;

            text-decoration: none !important;

            font-size: 10px;

            font-weight: 800;

            transition: .2s ease;
        }

        .wai-support-button:hover {
            border-color: #bcebcf;

            color: var(--wai-green-deep) !important;

            background: var(--wai-green-50);
        }

        /*
        |--------------------------------------------------------------------------
        | TABLET
        |--------------------------------------------------------------------------
        */

        @media (max-width: 950px) {

            .wai-hero-grid {
                grid-template-columns: 1fr;

                gap: 28px;
            }

            .wai-next {
                grid-template-columns: 1fr;
            }

            .wai-primary-button {
                width: fit-content;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 640px) {

            .wai-shell {
                gap: 12px;

                padding-bottom: 20px;
            }

            /*
            |----------------------------------------------------------------------
            | Hero
            |----------------------------------------------------------------------
            */

            .wai-hero {
                padding: 22px 17px;

                border-radius: 21px;
            }

            .wai-hero-grid {
                gap: 24px;
            }

            .wai-kicker {
                min-height: 31px;

                padding: 0 10px;

                font-size: 8px;
            }

            .wai-hero-title {
                margin-top: 15px;

                font-size: 36px;

                line-height: .98;

                letter-spacing: -2.2px;
            }

            .wai-hero-copy {
                margin-top: 14px;

                font-size: 12px;

                line-height: 1.65;
            }

            /*
            |----------------------------------------------------------------------
            | Progress
            |----------------------------------------------------------------------
            */

            .wai-progress {
                padding: 18px;

                border-radius: 17px;
            }

            .wai-progress-label {
                font-size: 9px;
            }

            .wai-progress-value {
                font-size: 38px;
            }

            .wai-progress-count {
                font-size: 9px;
            }

            .wai-progress-footer {
                font-size: 9px;
            }

            /*
            |----------------------------------------------------------------------
            | Next
            |----------------------------------------------------------------------
            */

            .wai-next {
                padding: 20px 17px;

                grid-template-columns: 1fr;

                gap: 18px;

                border-radius: 19px;
            }

            .wai-next-label {
                font-size: 9px;
            }

            .wai-next-title {
                font-size: 21px;
            }

            .wai-next-copy {
                font-size: 11px;
            }

            .wai-primary-button {
                width: 100%;

                min-height: 56px;

                font-size: 11px;
            }

            /*
            |----------------------------------------------------------------------
            | Ready
            |----------------------------------------------------------------------
            */

            .wai-ready {
                padding: 17px;

                grid-template-columns:
                    44px 1fr;

                border-radius: 18px;
            }

            .wai-ready-icon {
                width: 44px;
                height: 44px;
            }

            .wai-ready-title {
                font-size: 17px;
            }

            .wai-ready-copy {
                font-size: 11px;
            }

            .wai-ready-button {
                grid-column: 1 / -1;

                width: 100%;

                min-height: 48px;

                justify-content: center;

                font-size: 11px;
            }

            /*
            |----------------------------------------------------------------------
            | Plan
            |----------------------------------------------------------------------
            */

            .wai-plan {
                padding: 18px 16px;

                border-radius: 18px;
            }

            .wai-plan-head {
                display: block;
            }

            .wai-plan-badge {
                font-size: 8px;
            }

            .wai-plan-title {
                font-size: 18px;
            }

            .wai-plan-description {
                font-size: 11px;
            }

            .wai-plan-usage {
                margin-top: 18px;

                text-align: left;
            }

            .wai-plan-usage strong {
                font-size: 25px;
            }

            .wai-plan-usage span {
                font-size: 9px;
            }

            .wai-plan-bottom {
                flex-direction: column;

                align-items: stretch;

                gap: 13px;
            }

            .wai-plan-remaining {
                font-size: 10px;
            }

            .wai-plan-info {
                font-size: 9px;
            }

            .wai-buy-button,
            .wai-active-pill {
                width: 100%;

                min-height: 47px;

                justify-content: center;

                font-size: 10px;
            }

            /*
            |----------------------------------------------------------------------
            | Steps Panel
            |----------------------------------------------------------------------
            */

            .wai-steps-panel {
                padding: 19px 12px 13px;

                border-radius: 19px;
            }

            .wai-section-head {
                display: block;

                margin-bottom: 16px;

                padding: 0 4px;
            }

            .wai-section-kicker {
                font-size: 8px;
            }

            .wai-section-title {
                font-size: 20px;
            }

            .wai-section-copy {
                max-width: 310px;

                font-size: 11px;

                line-height: 1.55;
            }

            .wai-section-status {
                display: block;

                margin-top: 9px;

                font-size: 9px;
            }

            /*
            |----------------------------------------------------------------------
            | Steps
            |----------------------------------------------------------------------
            */

            .wai-step-list {
                gap: 8px;
            }

            .wai-step {
                min-height: auto;

                padding: 14px;

                grid-template-columns:
                    44px minmax(0,1fr);

                gap: 12px;

                border-radius: 15px;
            }

            .wai-step-number {
                width: 44px;
                height: 44px;

                border-radius: 12px;

                font-size: 11px;
            }

            .wai-step-meta {
                flex-wrap: wrap;

                gap: 5px;

                margin-bottom: 6px;
            }

            .wai-step-index {
                font-size: 8px;
            }

            .wai-step-status {
                min-height: 22px;

                font-size: 8px;
            }

            .wai-step-title {
                font-size: 15px;
            }

            .wai-step-description {
                font-size: 11px;

                line-height: 1.55;
            }

            .wai-step-button {
                grid-column: 1 / -1;

                width: 100%;

                min-height: 48px;

                margin-top: 2px;

                font-size: 10px;
            }

            /*
            |----------------------------------------------------------------------
            | Support
            |----------------------------------------------------------------------
            */

            .wai-support {
                padding: 17px;

                flex-direction: column;

                align-items: stretch;

                gap: 13px;

                border-radius: 16px;
            }

            .wai-support-title {
                font-size: 13px;
            }

            .wai-support-copy {
                font-size: 10px;
            }

            .wai-support-button {
                width: 100%;

                min-height: 46px;

                justify-content: center;

                font-size: 10px;
            }
        }

        @media (max-width: 380px) {

            .wai-hero-title {
                font-size: 33px;
            }

            .wai-progress-count {
                max-width: 90px;

                text-align: right;
            }
        }
    </style>

    <div class="wai-setup">

        <div class="wai-shell">

            {{-- =====================================================
                 HERO
            ====================================================== --}}

            <section class="wai-hero">

                <div class="wai-hero-grid">

                    <div>

                        <div class="wai-kicker">

                            <span class="wai-kicker-dot"></span>

                            WAI · Kurulum Merkezi

                        </div>

                        <h1 class="wai-hero-title">

                            Hoş geldiniz{{ $firstName ? ', '.$firstName : '' }}.

                            <span>
                                WAI'nizi hazırlayalım.
                            </span>

                        </h1>

                        <p class="wai-hero-copy">
                            Yapay zekâ çalışanınızı birkaç adımda işletmenize
                            göre hazırlayın. Kurulum tamamlandığında WAI,
                            WhatsApp müşterilerinizle konuşmaya hazır olacak.
                        </p>

                    </div>

                    <div class="wai-progress">

                        <div class="wai-progress-header">

                            <div>

                                <div class="wai-progress-label">
                                    Kurulum ilerlemesi
                                </div>

                                <div class="wai-progress-value">
                                    <b>%</b>{{ $progress }}
                                </div>

                            </div>

                            <div class="wai-progress-count">
                                {{ $completedCount }} / {{ $totalSteps }} tamamlandı
                            </div>

                        </div>

                        <div class="wai-progress-track">

                            <div
                                class="wai-progress-bar"
                                style="width: {{ $progress }}%;"
                            ></div>

                        </div>

                        <div class="wai-progress-footer">

                            <i></i>

                            @if ($allCompleted)

                                Tüm kurulum adımları tamamlandı.

                            @else

                                Tamamlanmayı bekleyen
                                {{ $totalSteps - $completedCount }}
                                adım bulunuyor.

                            @endif

                        </div>

                    </div>

                </div>

            </section>

            {{-- =====================================================
                 NEXT STEP
            ====================================================== --}}

            @if (! $allCompleted && $nextStep)

                <section class="wai-next">

                    <div>

                        <div class="wai-next-label">

                            <i></i>

                            Sıradaki Adım ·
                            {{ $nextStepNumber }} / {{ $totalSteps }}

                        </div>

                        <h2 class="wai-next-title">
                            {{ $nextStep['title'] }}
                        </h2>

                        <p class="wai-next-copy">
                            {{ $nextStep['description'] }}
                        </p>

                    </div>

                    <a
                        href="{{ $nextStep['url'] }}"
                        class="wai-primary-button"
                    >

                        <span>
                            Devam Et
                        </span>

                        <b>
                            →
                        </b>

                    </a>

                </section>

            @else

                <section class="wai-ready">

                    <div class="wai-ready-icon">
                        ✓
                    </div>

                    <div>

                        <h2 class="wai-ready-title">
                            WAI hazır.
                        </h2>

                        <p class="wai-ready-copy">
                            Kurulumunuz tamamlandı.
                            Yapay zekânız müşterilerinizle konuşmaya hazır.
                        </p>

                    </div>

                    <a
                        href="{{ url('/admin/gelen-kutusu') }}"
                        class="wai-ready-button"
                    >
                        Gelen Kutusuna Git
                        <span>→</span>
                    </a>

                </section>

            @endif

            {{-- =====================================================
                 TRIAL / SUBSCRIPTION
            ====================================================== --}}

            @if ($bot)

                <section
                    class="
                        wai-plan
                        {{ $trialCompleted ? 'trial-expired' : '' }}
                        {{ $subscriptionActive ? 'plan-active' : '' }}
                    "
                >

                    <div class="wai-plan-head">

                        <div>

                            <div class="wai-plan-badge">

                                @if ($subscriptionActive)

                                    ✓ PAKET AKTİF

                                @elseif ($trialCompleted)

                                    ! DENEME TAMAMLANDI

                                @else

                                    ✦ ÜCRETSİZ DENEME

                                @endif

                            </div>

                            <h3 class="wai-plan-title">
                                {{ $planTitle }}
                            </h3>

                            <p class="wai-plan-description">
                                {{ $planDescription }}
                            </p>

                        </div>

                        <div class="wai-plan-usage">

                            @if ($subscriptionActive)

                                <strong>
                                    Aktif
                                </strong>

                                <span>
                                    WhatsApp AI kullanımı
                                </span>

                            @else

                                <strong>
                                    {{ $trialMessagesUsed }}
                                    /
                                    {{ $trialMessageLimit }}
                                </strong>

                                <span>
                                    AI cevabı kullanıldı
                                </span>

                            @endif

                        </div>

                    </div>

                    @if (! $subscriptionActive)

                        <div class="wai-trial-track">

                            <div
                                class="wai-trial-bar"
                                style="width: {{ $trialProgress }}%;"
                            ></div>

                        </div>

                        <div class="wai-plan-bottom">

                            <div>

                                @if ($trialCompleted)

                                    <div class="wai-plan-remaining">
                                        Ücretsiz kullanım hakkınız tamamlandı.
                                    </div>

                                    <div class="wai-plan-info">
                                        WhatsApp bağlantınız korunur.
                                        Paketinizi aktifleştirdiğinizde
                                        WAI müşterilerinize yeniden cevap vermeye başlar.
                                    </div>

                                @else

                                    <div class="wai-plan-remaining">

                                        {{ $trialMessagesRemaining }}
                                        ücretsiz AI cevabı kaldı

                                    </div>

                                    <div class="wai-plan-info">
                                        Sayaç yalnızca WhatsApp üzerinden
                                        başarıyla gönderilen yapay zekâ cevaplarında azalır.
                                    </div>

                                @endif

                            </div>

                            @if ($trialCompleted)

                                <a
                                    href="https://wa.me/905382399098?text={{ urlencode('Merhaba, WhatsApp Yapay Zeka paketini satın almak istiyorum. Paketler hakkında bilgi alabilir miyim?') }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="wai-buy-button"
                                >
                                    Paketi Satın Al
                                    <span>→</span>
                                </a>

                            @endif

                        </div>

                    @else

                        <div class="wai-plan-bottom">

                            <div>

                                <div class="wai-plan-remaining">
                                    Yapay zekânız aktif
                                </div>

                                <div class="wai-plan-info">
                                    WAI WhatsApp üzerinden müşterilerinize
                                    yanıt vermeye devam ediyor.
                                </div>

                            </div>

                            <div class="wai-active-pill">
                                ✓ Paket Aktif
                            </div>

                        </div>

                    @endif

                </section>

            @endif

            {{-- =====================================================
                 STEPS
            ====================================================== --}}

            <section class="wai-steps-panel">

                <div class="wai-section-head">

                    <div>

                        <div class="wai-section-kicker">
                            WAI Kurulumu
                        </div>

                        <h2 class="wai-section-title">
                            Kurulum Adımları
                        </h2>

                        <p class="wai-section-copy">
                            WAI'nizi kullanıma hazırlamak için aşağıdaki
                            adımları sırayla tamamlayın.
                        </p>

                    </div>

                    <div class="wai-section-status">

                        {{ $completedCount }} tamamlandı
                        ·
                        {{ $totalSteps - $completedCount }} kaldı

                    </div>

                </div>

                <div class="wai-step-list">

                    @foreach ($steps as $index => $step)

                        @php
                            $isCurrent =
                                ! $step['completed']
                                &&
                                $nextStepIndex !== false
                                &&
                                $index === $nextStepIndex;
                        @endphp

                        <article
                            class="
                                wai-step
                                {{ $step['completed'] ? 'done' : '' }}
                                {{ $isCurrent ? 'current' : '' }}
                            "
                        >

                            <div class="wai-step-number">

                                @if ($step['completed'])

                                    ✓

                                @elseif ($isCurrent)

                                    →

                                @else

                                    {{ str_pad(
                                        $index + 1,
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ) }}

                                @endif

                            </div>

                            <div class="wai-step-body">

                                <div class="wai-step-meta">

                                    <span class="wai-step-index">
                                        Adım {{ $index + 1 }}
                                    </span>

                                    <span class="wai-step-status">

                                        @if ($step['completed'])

                                            ✓ Tamamlandı

                                        @elseif ($isCurrent)

                                            Sıradaki Adım

                                        @else

                                            Bekliyor

                                        @endif

                                    </span>

                                </div>

                                <h3 class="wai-step-title">
                                    {{ $step['title'] }}
                                </h3>

                                <p class="wai-step-description">
                                    {{ $step['description'] }}
                                </p>

                            </div>

                            <a
                                href="{{ $step['url'] }}"
                                class="wai-step-button"
                            >

                                <span>
                                    {{ $step['button'] }}
                                </span>

                                <b>
                                    →
                                </b>

                            </a>

                        </article>

                    @endforeach

                </div>

            </section>

            {{-- =====================================================
                 SUPPORT
            ====================================================== --}}

            <div class="wai-support">

                <div>

                    <h3 class="wai-support-title">
                        Kurulum sırasında yardıma mı ihtiyacınız var?
                    </h3>

                    <p class="wai-support-copy">
                        AsilkanSoft destek ekibi WAI kurulum sürecinin
                        her aşamasında size yardımcı olabilir.
                    </p>

                </div>

                <a
                    class="wai-support-button"
                    href="https://wa.me/905382399098?text={{ urlencode('Merhaba, WAI kurulumu için destek almak istiyorum.') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Destek Al
                </a>

            </div>

        </div>

    </div>

</x-filament-panels::page>