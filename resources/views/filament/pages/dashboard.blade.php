<x-filament-panels::page>

<style>
/* ==========================================================================
   WAI PREMIUM DASHBOARD
   FINAL DESIGN + PREMIUM SVG ICONS + MOBILE UX
   ========================================================================== */

.wai-dashboard {
    --wai-green: #20df78;
    --wai-green-2: #57efa0;
    --wai-green-dark: #07964c;
    --wai-green-deep: #06713a;

    --wai-black: #06110b;
    --wai-black-2: #0a1810;

    --wai-title: #0b110d;
    --wai-text: #39453d;
    --wai-muted: #6f7b73;
    --wai-muted-2: #929c96;

    --wai-white: #ffffff;
    --wai-soft: #f7faf8;
    --wai-green-soft: #effcf5;

    --wai-line: #e5eae7;
    --wai-line-dark: #d8dfdb;

    --wai-shadow:
        0 18px 55px rgba(13,31,20,.055);

    width: 100%;
    max-width: 1240px;

    margin: 0 auto;

    color: var(--wai-text);
}

.wai-dashboard *,
.wai-dashboard *::before,
.wai-dashboard *::after {
    box-sizing: border-box;
}


/* ==========================================================================
   TOP BAR
   ========================================================================== */

.wai-dashboard-top {
    margin-bottom: 17px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;
}

.wai-dashboard-date {
    display: flex;
    align-items: center;

    gap: 9px;

    color: var(--wai-muted);

    font-size: 13px;
    font-weight: 700;
}

.wai-dashboard-date i {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--wai-green);

    box-shadow:
        0 0 0 4px rgba(32,223,120,.10);
}

.wai-dashboard-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.wai-dashboard-action {
    min-height: 46px;

    padding: 0 16px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 9px;

    border: 1px solid var(--wai-line);
    border-radius: 13px;

    color: #445148 !important;
    background: #fff;

    text-decoration: none !important;

    font-size: 13px;
    font-weight: 800;

    box-shadow:
        0 7px 20px rgba(13,31,20,.035);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.wai-dashboard-action:hover {
    transform: translateY(-1px);

    border-color: #cae7d4;

    box-shadow:
        0 11px 26px rgba(13,31,20,.055);
}

.wai-dashboard-action svg {
    width: 17px;
    height: 17px;
}

.wai-dashboard-action.primary {
    border-color: transparent;

    color: #062d19 !important;

    background:
        linear-gradient(
            135deg,
            #65f0a3,
            #29df7c
        );

    box-shadow:
        0 12px 30px rgba(32,223,120,.16);
}


/* ==========================================================================
   HERO
   ========================================================================== */

.wai-dashboard-hero {
    position: relative;

    overflow: hidden;

    min-height: 350px;

    padding: 40px;

    border: 1px solid var(--wai-line);
    border-radius: 30px;

    background:
        radial-gradient(
            circle at 91% 25%,
            rgba(32,223,120,.13),
            transparent 26%
        ),
        radial-gradient(
            circle at 100% 100%,
            rgba(32,223,120,.09),
            transparent 26%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfdfc
        );

    box-shadow: var(--wai-shadow);
}

.wai-dashboard-hero::before {
    content: "";

    position: absolute;

    width: 470px;
    height: 470px;

    right: -145px;
    top: -165px;

    opacity: .48;

    background-image:
        repeating-radial-gradient(
            ellipse at center,
            rgba(32,223,120,.18) 0,
            rgba(32,223,120,.18) 1px,
            transparent 1px,
            transparent 16px
        );

    transform: rotate(-15deg);

    pointer-events: none;
}

.wai-dashboard-hero-grid {
    position: relative;
    z-index: 2;

    display: grid;

    grid-template-columns:
        minmax(0,1.28fr)
        minmax(320px,.72fr);

    gap: 50px;

    align-items: center;
}

.wai-dashboard-eyebrow {
    min-height: 34px;

    padding: 0 13px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border:
        1px solid #c8eed7;

    border-radius: 999px;

    color: var(--wai-green-deep);

    background:
        var(--wai-green-soft);

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .9px;

    text-transform: uppercase;
}

.wai-dashboard-eyebrow i {
    width: 8px;
    height: 8px;

    border-radius: 50%;

    background: var(--wai-green);

    box-shadow:
        0 0 0 4px rgba(32,223,120,.12);
}

.wai-dashboard-title {
    max-width: 770px;

    margin: 20px 0 0;

    color: var(--wai-title);

    font-size:
        clamp(
            42px,
            5vw,
            65px
        );

    line-height: .96;

    font-weight: 850;

    letter-spacing: -3.7px;
}

.wai-dashboard-title span {
    color: var(--wai-green-dark);
}

.wai-dashboard-copy {
    max-width: 690px;

    margin: 19px 0 0;

    color: var(--wai-muted);

    font-size: 16px;

    line-height: 1.72;
}


/* ==========================================================================
   MINI STATS
   ========================================================================== */

.wai-hero-mini-stats {
    margin-top: 28px;

    display: flex;
    flex-wrap: wrap;

    gap: 10px;
}

.wai-hero-mini-stat {
    min-height: 44px;

    padding: 0 13px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border: 1px solid var(--wai-line);
    border-radius: 12px;

    color: #536058;

    background:
        rgba(255,255,255,.72);

    font-size: 12px;
    font-weight: 750;
}

.wai-hero-mini-stat strong {
    color: var(--wai-title);

    font-weight: 900;
}

.wai-hero-mini-stat.green strong {
    color: var(--wai-green-dark);
}


/* ==========================================================================
   AI STATUS
   ========================================================================== */

.wai-ai-status {
    position: relative;

    overflow: hidden;

    padding: 26px;

    border-radius: 24px;

    color: #fff;

    background:
        radial-gradient(
            circle at 70% 25%,
            rgba(52,255,140,.22),
            transparent 29%
        ),
        linear-gradient(
            140deg,
            #04120c,
            #081c12 63%,
            #06150e
        );

    box-shadow:
        0 28px 70px rgba(0,22,12,.16);
}

.wai-ai-status::before {
    content: "";

    position: absolute;

    width: 260px;
    height: 260px;

    right: -120px;
    bottom: -150px;

    border-radius: 50%;

    border:
        1px solid rgba(75,255,153,.11);

    box-shadow:
        0 0 0 38px rgba(75,255,153,.025),
        0 0 0 75px rgba(75,255,153,.015);
}

.wai-ai-status-top {
    position: relative;
    z-index: 2;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;
}

.wai-ai-status-label {
    color: #a9c3b2;

    font-size: 11px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.wai-ai-live {
    min-height: 29px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    border:
        1px solid rgba(50,255,140,.14);

    border-radius: 999px;

    color: #48f091;

    background:
        rgba(32,223,120,.08);

    font-size: 10px;
    font-weight: 900;
}

.wai-ai-live i {
    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--wai-green);

    box-shadow:
        0 0 0 4px rgba(32,223,120,.10);
}

.wai-ai-orb {
    position: relative;
    z-index: 2;

    width: 106px;
    height: 106px;

    margin: 28px auto 18px;

    display: grid;
    place-items: center;

    border-radius: 50%;

    color: #fff;

    background:
        radial-gradient(
            circle at 32% 25%,
            #8affbd,
            #21df78 31%,
            #0a9b52 68%,
            #08703c
        );

    box-shadow:
        inset 0 0 28px rgba(255,255,255,.25),
        0 0 0 9px rgba(44,255,138,.035),
        0 0 60px rgba(32,223,120,.38);
}

.wai-ai-orb::before,
.wai-ai-orb::after {
    content: "";

    position: absolute;

    left: 50%;
    top: 50%;

    border:
        1px solid rgba(72,255,153,.21);

    border-radius: 50%;

    transform:
        translate(-50%,-50%)
        rotate(-18deg);
}

.wai-ai-orb::before {
    width: 158px;
    height: 64px;
}

.wai-ai-orb::after {
    width: 140px;
    height: 86px;

    transform:
        translate(-50%,-50%)
        rotate(30deg);
}

.wai-ai-orb span {
    font-size: 29px;
    font-weight: 900;
}

.wai-ai-status-title {
    position: relative;
    z-index: 2;

    color: #f0fff5;

    text-align: center;

    font-size: 20px;
    font-weight: 850;
}

.wai-ai-status-copy {
    position: relative;
    z-index: 2;

    margin-top: 7px;

    color: #a8beb0;

    text-align: center;

    font-size: 12px;

    line-height: 1.55;
}

.wai-ai-status-button {
    position: relative;
    z-index: 2;

    min-height: 52px;

    margin-top: 20px;

    padding: 0 15px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    border-radius: 13px;

    color: #0c140f !important;

    background: #fff;

    text-decoration: none !important;

    font-size: 12px;
    font-weight: 850;

    box-shadow:
        0 13px 34px rgba(0,0,0,.16);
}


/* ==========================================================================
   KPI GRID
   ========================================================================== */

.wai-kpi-grid {
    margin-top: 18px;

    display: grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap: 12px;
}

.wai-kpi {
    position: relative;

    min-height: 180px;

    padding: 21px;

    overflow: hidden;

    border: 1px solid var(--wai-line);
    border-radius: 20px;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfb
        );

    box-shadow:
        0 11px 35px rgba(13,31,20,.035);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.wai-kpi:hover {
    transform: translateY(-2px);

    border-color: #d3e6da;

    box-shadow:
        0 16px 40px rgba(13,31,20,.055);
}

.wai-kpi::after {
    content: "";

    position: absolute;

    width: 140px;
    height: 140px;

    right: -75px;
    bottom: -85px;

    border-radius: 50%;

    background:
        radial-gradient(
            circle,
            rgba(32,223,120,.08),
            transparent 65%
        );
}

.wai-kpi-top {
    position: relative;
    z-index: 2;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}

.wai-kpi-icon {
    position: relative;

    width: 50px;
    height: 50px;

    display: grid;
    place-items: center;

    border: 1px solid #d8f1e2;
    border-radius: 15px;

    color: var(--wai-green-dark);

    background:
        linear-gradient(
            145deg,
            #f3fff8,
            #eafbf1
        );

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,.9),
        0 9px 22px rgba(32,223,120,.07);
}

.wai-kpi-icon::after {
    content: "";

    position: absolute;

    inset: 5px;

    border-radius: 11px;

    border:
        1px solid rgba(32,223,120,.07);

    pointer-events: none;
}

.wai-kpi-icon svg {
    position: relative;
    z-index: 2;

    width: 24px;
    height: 24px;

    display: block;
}

.wai-kpi-badge {
    min-height: 29px;

    padding: 0 10px;

    display: inline-flex;
    align-items: center;

    border-radius: 999px;

    color: #637067;

    background:
        linear-gradient(
            145deg,
            #f5f7f6,
            #eef2ef
        );

    font-size: 10px;
    font-weight: 850;
}

.wai-kpi-number {
    position: relative;
    z-index: 2;

    margin-top: 20px;

    color: var(--wai-title);

    font-size: 38px;
    line-height: 1;

    font-weight: 850;

    letter-spacing: -2px;
}

.wai-kpi-title {
    position: relative;
    z-index: 2;

    margin-top: 9px;

    color: #465249;

    font-size: 13px;
    font-weight: 850;
}

.wai-kpi-description {
    position: relative;
    z-index: 2;

    margin-top: 6px;

    color: #808c84;

    font-size: 11px;

    line-height: 1.5;
}


/* ==========================================================================
   MAIN CARDS
   ========================================================================== */

.wai-main-grid {
    margin-top: 18px;

    display: grid;

    grid-template-columns:
        minmax(0,1.28fr)
        minmax(320px,.72fr);

    gap: 14px;
}

.wai-card {
    border: 1px solid var(--wai-line);
    border-radius: 24px;

    background: #fff;

    box-shadow: var(--wai-shadow);
}

.wai-card-head {
    padding: 24px 24px 0;

    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 18px;
}

.wai-card-kicker {
    color: var(--wai-green-dark);

    font-size: 10px;
    font-weight: 900;

    letter-spacing: .8px;

    text-transform: uppercase;
}

.wai-card-title {
    margin-top: 7px;

    color: var(--wai-title);

    font-size: 23px;
    font-weight: 850;

    letter-spacing: -.65px;
}

.wai-card-subtitle {
    margin-top: 6px;

    color: var(--wai-muted);

    font-size: 12px;

    line-height: 1.55;
}

.wai-card-link {
    min-height: 38px;

    padding: 0 11px;

    display: inline-flex;
    align-items: center;

    gap: 8px;

    border: 1px solid var(--wai-line);
    border-radius: 11px;

    color: #5d6961 !important;

    background: #fff;

    text-decoration: none !important;

    font-size: 11px;
    font-weight: 800;

    white-space: nowrap;
}


/* ==========================================================================
   ACTIVITY
   ========================================================================== */

.wai-activity-body {
    padding: 23px 24px 24px;
}

.wai-activity-highlight {
    position: relative;

    overflow: hidden;

    padding: 22px;

    border-radius: 18px;

    color: #fff;

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(32,223,120,.18),
            transparent 34%
        ),
        linear-gradient(
            135deg,
            #07150e,
            #0b2015
        );
}

.wai-activity-highlight-top {
    display: flex;
    align-items: center;

    gap: 11px;
}

.wai-activity-orb {
    width: 46px;
    height: 46px;

    display: grid;
    place-items: center;

    flex: 0 0 46px;

    border-radius: 14px;

    color: #052e18;

    background:
        linear-gradient(
            135deg,
            #72f4aa,
            #2fdf80
        );

    font-size: 16px;
    font-weight: 900;
}

.wai-activity-highlight strong {
    display: block;

    color: #f2fff7;

    font-size: 15px;
}

.wai-activity-highlight span {
    display: block;

    margin-top: 3px;

    color: #9db5a5;

    font-size: 11px;
}

.wai-activity-grid {
    margin-top: 15px;

    display: grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap: 10px;
}

.wai-activity-item {
    min-height: 102px;

    padding: 16px;

    border: 1px solid var(--wai-line);
    border-radius: 15px;

    background: var(--wai-soft);
}

.wai-activity-item strong {
    display: block;

    color: var(--wai-title);

    font-size: 25px;
    line-height: 1;

    font-weight: 850;
}

.wai-activity-item span {
    display: block;

    margin-top: 8px;

    color: #66736b;

    font-size: 12px;
    font-weight: 750;
}

.wai-activity-item small {
    display: block;

    margin-top: 5px;

    color: #939d97;

    font-size: 10px;

    line-height: 1.4;
}


/* ==========================================================================
   PREMIUM CHANNEL LOGOS
   ========================================================================== */

.wai-channel-summary-body {
    padding: 20px 24px 24px;
}

.wai-channel-summary-item {
    min-height: 72px;

    padding: 12px 0;

    display: flex;
    align-items: center;

    gap: 12px;

    border-bottom:
        1px solid var(--wai-line);
}

.wai-channel-summary-item:last-child {
    border-bottom: 0;
}

.wai-channel-logo {
    width: 46px;
    height: 46px;

    display: grid;
    place-items: center;

    flex: 0 0 46px;

    overflow: hidden;

    border-radius: 14px;

    box-shadow:
        0 8px 20px rgba(13,31,20,.08);
}

.wai-channel-logo svg {
    width: 25px;
    height: 25px;

    display: block;
}

.wai-channel-logo.whatsapp {
    color: #fff;

    background:
        linear-gradient(
            145deg,
            #2bf27e,
            #10b95d
        );

    box-shadow:
        0 8px 22px rgba(18,196,96,.22);
}

.wai-channel-logo.instagram {
    color: #fff;

    background:
        radial-gradient(
            circle at 30% 100%,
            #ffd600 0%,
            #ff7a00 27%,
            #ff0169 52%,
            #d300c5 74%,
            #7638fa 100%
        );

    box-shadow:
        0 8px 22px rgba(193,48,190,.18);
}

.wai-channel-logo.facebook {
    color: #fff;

    background:
        linear-gradient(
            145deg,
            #5caaff,
            #1877f2
        );

    box-shadow:
        0 8px 22px rgba(24,119,242,.19);
}

.wai-channel-logo.web {
    color: var(--wai-green-dark);

    border: 1px solid #c9eed7;

    background:
        linear-gradient(
            145deg,
            #f4fff8,
            #e8fbf0
        );

    box-shadow:
        0 8px 20px rgba(32,223,120,.09);
}

.wai-channel-summary-info {
    min-width: 0;

    flex: 1;
}

.wai-channel-summary-info strong {
    display: block;

    color: #29342d;

    font-size: 13px;
}

.wai-channel-summary-info span {
    display: block;

    margin-top: 4px;

    color: #929c96;

    font-size: 10px;
}

.wai-channel-state {
    min-height: 28px;

    padding: 0 9px;

    display: inline-flex;
    align-items: center;

    border-radius: 999px;

    color: #6e7972;

    background: #f0f3f1;

    font-size: 10px;
    font-weight: 850;
}

.wai-channel-state.active {
    color: var(--wai-green-deep);

    background: #eafaf0;
}


/* ==========================================================================
   QUICK ACTIONS
   ========================================================================== */

.wai-quick-card {
    margin-top: 18px;

    padding: 24px;

    border: 1px solid var(--wai-line);
    border-radius: 24px;

    background: #fff;

    box-shadow: var(--wai-shadow);
}

.wai-quick-title {
    color: var(--wai-title);

    font-size: 20px;
    font-weight: 850;
}

.wai-quick-copy {
    margin-top: 6px;

    color: var(--wai-muted);

    font-size: 12px;
}

.wai-quick-grid {
    margin-top: 17px;

    display: grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap: 10px;
}

.wai-quick-action {
    min-height: 82px;

    padding: 14px;

    display: flex;
    align-items: center;

    gap: 11px;

    border: 1px solid var(--wai-line);
    border-radius: 15px;

    color: #465249 !important;

    background: #fbfcfb;

    text-decoration: none !important;
}

.wai-quick-icon {
    width: 44px;
    height: 44px;

    display: grid;
    place-items: center;

    flex: 0 0 44px;

    border: 1px solid #d8f1e2;
    border-radius: 13px;

    color: var(--wai-green-dark);

    background:
        linear-gradient(
            145deg,
            #f3fff8,
            #eafbf1
        );
}

.wai-quick-icon svg {
    width: 21px;
    height: 21px;
}

.wai-quick-action strong {
    display: block;

    font-size: 12px;
    font-weight: 850;
}

.wai-quick-action span {
    display: block;

    margin-top: 4px;

    color: #8b968f;

    font-size: 10px;
}


/* ==========================================================================
   NOTE
   ========================================================================== */

.wai-dashboard-note {
    position: relative;

    margin-top: 18px;

    overflow: hidden;

    padding: 25px;

    display: grid;

    grid-template-columns:
        54px minmax(0,1fr) auto;

    gap: 16px;

    align-items: center;

    border: 1px solid #cee9d8;
    border-radius: 21px;

    background:
        radial-gradient(
            circle at 90% 50%,
            rgba(32,223,120,.10),
            transparent 25%
        ),
        linear-gradient(
            135deg,
            #f7fcf9,
            #f0faf4
        );
}

.wai-dashboard-note-icon {
    width: 54px;
    height: 54px;

    display: grid;
    place-items: center;

    border-radius: 16px;

    color: #06331b;

    background:
        linear-gradient(
            135deg,
            #72f4aa,
            #31df80
        );

    font-size: 18px;
    font-weight: 900;
}

.wai-dashboard-note strong {
    display: block;

    color: #172119;

    font-size: 14px;
}

.wai-dashboard-note p {
    margin: 6px 0 0;

    color: #66736b;

    font-size: 12px;

    line-height: 1.55;
}

.wai-dashboard-note a {
    min-height: 45px;

    padding: 0 14px;

    display: inline-flex;
    align-items: center;

    gap: 9px;

    border-radius: 12px;

    color: #06331b !important;

    background: #fff;

    text-decoration: none !important;

    font-size: 11px;
    font-weight: 850;
}


/* ==========================================================================
   TABLET
   ========================================================================== */

@media (max-width: 1050px) {

    .wai-dashboard-hero-grid,
    .wai-main-grid {
        grid-template-columns: 1fr;
    }

    .wai-ai-status {
        max-width: 520px;
    }

    .wai-kpi-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .wai-quick-grid {
        grid-template-columns:
            1fr 1fr;
    }

    .wai-dashboard-note {
        grid-template-columns:
            54px minmax(0,1fr);
    }

    .wai-dashboard-note a {
        grid-column:
            1 / -1;

        width: 100%;

        justify-content: center;
    }
}


/* ==========================================================================
   MOBILE PREMIUM UX
   ========================================================================== */

@media (max-width: 700px) {

    .wai-dashboard-top {
        display: block;
    }

    .wai-dashboard-date {
        font-size: 12px;
    }

    .wai-dashboard-actions {
        margin-top: 12px;

        width: 100%;
    }

    .wai-dashboard-action {
        flex: 1;

        min-height: 48px;

        padding: 0 10px;

        font-size: 12px;
    }


    .wai-dashboard-hero {
        min-height: 0;

        padding: 23px 17px 18px;

        border-radius: 22px;
    }

    .wai-dashboard-hero::before {
        width: 260px;
        height: 260px;

        right: -120px;
        top: -100px;

        opacity: .35;
    }

    .wai-dashboard-hero-grid {
        gap: 25px;
    }

    .wai-dashboard-eyebrow {
        min-height: 31px;

        font-size: 10px;
    }

    .wai-dashboard-title {
        margin-top: 18px;

        font-size: 39px;

        line-height: .97;

        letter-spacing: -2.6px;
    }

    .wai-dashboard-copy {
        margin-top: 16px;

        font-size: 14px;

        line-height: 1.65;
    }

    .wai-hero-mini-stats {
        margin-top: 21px;

        display: grid;

        grid-template-columns:
            1fr 1fr;

        gap: 8px;
    }

    .wai-hero-mini-stat {
        min-height: 47px;

        padding: 0 10px;

        font-size: 11px;
    }


    .wai-ai-status {
        width: 100%;

        padding: 20px;

        border-radius: 19px;
    }

    .wai-ai-status-title {
        font-size: 18px;
    }

    .wai-ai-status-copy {
        font-size: 12px;
    }

    .wai-ai-status-button {
        min-height: 54px;

        font-size: 12px;
    }


    .wai-kpi-grid {
        margin-top: 14px;

        grid-template-columns:
            1fr 1fr;

        gap: 9px;
    }

    .wai-kpi {
        min-height: 170px;

        padding: 16px;

        border-radius: 17px;
    }

    .wai-kpi-icon {
        width: 46px;
        height: 46px;

        border-radius: 14px;
    }

    .wai-kpi-icon svg {
        width: 22px;
        height: 22px;
    }

    .wai-kpi-number {
        font-size: 32px;
    }

    .wai-kpi-title {
        font-size: 12px;
    }

    .wai-kpi-description {
        font-size: 10px;
    }


    .wai-main-grid {
        margin-top: 14px;

        gap: 10px;
    }

    .wai-card {
        border-radius: 20px;
    }

    .wai-card-head {
        padding: 19px 17px 0;

        display: block;
    }

    .wai-card-title {
        font-size: 21px;
    }

    .wai-card-subtitle {
        font-size: 12px;
    }

    .wai-card-link {
        width: 100%;

        min-height: 43px;

        margin-top: 12px;

        justify-content: center;
    }

    .wai-activity-body {
        padding: 17px;
    }

    .wai-activity-grid {
        grid-template-columns:
            1fr 1fr;

        gap: 8px;
    }

    .wai-activity-item strong {
        font-size: 23px;
    }

    .wai-activity-item span {
        font-size: 11px;
    }

    .wai-channel-summary-body {
        padding: 15px 17px 17px;
    }

    .wai-channel-logo {
        width: 48px;
        height: 48px;

        flex: 0 0 48px;
    }

    .wai-channel-logo svg {
        width: 26px;
        height: 26px;
    }

    .wai-channel-summary-info strong {
        font-size: 13px;
    }

    .wai-channel-summary-info span {
        font-size: 10px;
    }


    .wai-quick-card {
        margin-top: 14px;

        padding: 18px 12px 12px;

        border-radius: 20px;
    }

    .wai-quick-grid {
        grid-template-columns: 1fr;

        gap: 8px;
    }

    .wai-quick-action {
        min-height: 68px;
    }


    .wai-dashboard-note {
        margin-top: 14px;

        padding: 17px;

        grid-template-columns:
            46px minmax(0,1fr);

        gap: 12px;

        border-radius: 19px;
    }

    .wai-dashboard-note-icon {
        width: 46px;
        height: 46px;
    }

    .wai-dashboard-note strong {
        font-size: 13px;
    }

    .wai-dashboard-note p {
        font-size: 11px;
    }

    .wai-dashboard-note a {
        grid-column:
            1 / -1;

        min-height: 49px;

        justify-content: center;
    }
}


@media (max-width: 380px) {

    .wai-dashboard-title {
        font-size: 35px;
    }

    .wai-hero-mini-stats,
    .wai-kpi-grid,
    .wai-activity-grid {
        grid-template-columns: 1fr;
    }
}
</style>


@php
    $bot = $this->getBot();

    $conversationCount =
        $this->getConversationCount();

    $unreadCount =
        $this->getUnreadCount();

    $productCount =
        $this->getProductCount();

    $orderCount =
        $this->getOrderCount();

    $whatsAppConnected =
        $this->getWhatsAppConnected();

    $connectedChannelCount =
        $this->getConnectedChannelCount();
@endphp


<div class="wai-dashboard">

    {{-- TOP --}}

    <div class="wai-dashboard-top">

        <div class="wai-dashboard-date">
            <i></i>
            WAI Genel Bakış
        </div>

        <div class="wai-dashboard-actions">

            <a
                href="{{ $this->getInboxUrl() }}"
                class="wai-dashboard-action"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M4 5h16v12H8l-4 3V5Z"/>
                    <path d="M8 9h8"/>
                    <path d="M8 13h5"/>
                </svg>

                Gelen Kutusu
            </a>

            <a
                href="{{ $this->getChannelUrl() }}"
                class="wai-dashboard-action primary"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 3v6"/>
                    <path d="M12 15v6"/>
                    <path d="M3 12h6"/>
                    <path d="M15 12h6"/>
                </svg>

                Kanalları Yönet
            </a>

        </div>

    </div>


    {{-- HERO --}}

    <section class="wai-dashboard-hero">

        <div class="wai-dashboard-hero-grid">

            <div>

                <div class="wai-dashboard-eyebrow">
                    <i></i>
                    WAI · İşletme Merkezi
                </div>

                <h1 class="wai-dashboard-title">
                    Hoş geldiniz,
                    <span>{{ $this->getUserName() }}</span>
                </h1>

                <p class="wai-dashboard-copy">
                    İşletmenizin müşteri iletişimini, yapay zekâ durumunu
                    ve satış operasyonunu tek ekrandan takip edin.
                    WAI çalışırken önemli gelişmeleri burada görebilirsiniz.
                </p>

                <div class="wai-hero-mini-stats">

                    <div class="wai-hero-mini-stat green">
                        <strong>{{ $connectedChannelCount }}</strong>
                        kanal aktif
                    </div>

                    <div class="wai-hero-mini-stat">
                        <strong>
                            {{ number_format($conversationCount, 0, ',', '.') }}
                        </strong>
                        müşteri
                    </div>

                    <div class="wai-hero-mini-stat">
                        <strong>
                            {{ number_format($orderCount, 0, ',', '.') }}
                        </strong>
                        sipariş
                    </div>

                </div>

            </div>


            <aside class="wai-ai-status">

                <div class="wai-ai-status-top">

                    <div class="wai-ai-status-label">
                        WAI Durumu
                    </div>

                    <div class="wai-ai-live">
                        <i></i>
                        {{ $bot ? 'Aktif' : 'Kurulum Bekliyor' }}
                    </div>

                </div>

                <div class="wai-ai-orb">
                    <span>AI</span>
                </div>

                <div class="wai-ai-status-title">

                    @if ($bot)
                        WAI çalışmaya hazır.
                    @else
                        WAI'nizi oluşturmaya başlayın.
                    @endif

                </div>

                <div class="wai-ai-status-copy">

                    @if ($bot)
                        {{ $bot->name ?: 'Yapay zekâ çalışanınız' }}
                        işletmeniz için hazır.
                    @else
                        İşletmenize özel yapay zekâ çalışanınızı
                        birkaç adımda oluşturabilirsiniz.
                    @endif

                </div>

                <a
                    href="{{ $this->getBotUrl() }}"
                    class="wai-ai-status-button"
                >
                    <span>
                        {{ $bot
                            ? 'AI Çalışanını Yönet'
                            : 'WAI Oluştur'
                        }}
                    </span>

                    <b>→</b>
                </a>

            </aside>

        </div>

    </section>


    {{-- KPI --}}

    <section class="wai-kpi-grid">

        {{-- TOPLAM MÜŞTERİ --}}

        <article class="wai-kpi">

            <div class="wai-kpi-top">

                <div class="wai-kpi-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M7.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/>
                        <path d="M16.5 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
                        <path d="M2.5 20c.5-4.1 2.3-6.2 5-6.2s4.5 2.1 5 6.2"/>
                        <path d="M13 19.5c.5-3.2 1.8-4.9 4-4.9 2.3 0 3.7 1.7 4.2 4.9"/>
                    </svg>
                </div>

                <div class="wai-kpi-badge">
                    CRM
                </div>

            </div>

            <div class="wai-kpi-number">
                {{ number_format($conversationCount, 0, ',', '.') }}
            </div>

            <div class="wai-kpi-title">
                Toplam Müşteri
            </div>

            <div class="wai-kpi-description">
                WAI ile görüşme geçmişi bulunan müşteriler.
            </div>

        </article>


        {{-- OKUNMAMIŞ MESAJ --}}

        <article class="wai-kpi">

            <div class="wai-kpi-top">

                <div class="wai-kpi-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 5h16v12H8l-4 3V5Z"/>
                        <path d="m7.5 9 4.5 3.5L16.5 9"/>
                        <circle cx="18.5" cy="5.5" r="2.5" fill="currentColor" stroke="none"/>
                    </svg>
                </div>

                <div class="wai-kpi-badge">
                    Canlı
                </div>

            </div>

            <div class="wai-kpi-number">
                {{ number_format($unreadCount, 0, ',', '.') }}
            </div>

            <div class="wai-kpi-title">
                Okunmamış Mesaj
            </div>

            <div class="wai-kpi-description">
                Ekibinizin kontrol etmesi gereken yeni mesajlar.
            </div>

        </article>


        {{-- SİPARİŞ --}}

        <article class="wai-kpi">

            <div class="wai-kpi-top">

                <div class="wai-kpi-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M5 8h14l-1 12H6L5 8Z"/>
                        <path d="M8.5 8V6a3.5 3.5 0 0 1 7 0v2"/>
                        <path d="M9 12h6"/>
                        <path d="M9 15h4"/>
                    </svg>
                </div>

                <div class="wai-kpi-badge">
                    Satış
                </div>

            </div>

            <div class="wai-kpi-number">
                {{ number_format($orderCount, 0, ',', '.') }}
            </div>

            <div class="wai-kpi-title">
                Sipariş
            </div>

            <div class="wai-kpi-description">
                WAI sistemindeki toplam sipariş kaydı.
            </div>

        </article>


        {{-- ÜRÜN / HİZMET --}}

        <article class="wai-kpi">

            <div class="wai-kpi-top">

                <div class="wai-kpi-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m12 3 8 4.5-8 4.5-8-4.5L12 3Z"/>
                        <path d="m4 12 8 4.5 8-4.5"/>
                        <path d="m4 16.5 8 4.5 8-4.5"/>
                    </svg>
                </div>

                <div class="wai-kpi-badge">
                    Katalog
                </div>

            </div>

            <div class="wai-kpi-number">
                {{ number_format($productCount, 0, ',', '.') }}
            </div>

            <div class="wai-kpi-title">
                Ürün / Hizmet
            </div>

            <div class="wai-kpi-description">
                WAI'nin müşterilere sunabildiği kayıtlı içerikler.
            </div>

        </article>

    </section>


    {{-- MAIN --}}

    <section class="wai-main-grid">

        <article class="wai-card">

            <div class="wai-card-head">

                <div>

                    <div class="wai-card-kicker">
                        WAI Aktivitesi
                    </div>

                    <div class="wai-card-title">
                        İşletmenizin genel durumu
                    </div>

                    <div class="wai-card-subtitle">
                        Mevcut operasyon verilerinizin hızlı özeti.
                    </div>

                </div>

                <a
                    href="{{ $this->getInboxUrl() }}"
                    class="wai-card-link"
                >
                    Gelen Kutusu →
                </a>

            </div>

            <div class="wai-activity-body">

                <div class="wai-activity-highlight">

                    <div class="wai-activity-highlight-top">

                        <div class="wai-activity-orb">
                            W
                        </div>

                        <div>

                            <strong>
                                WAI işletmenizin iletişim merkezinde.
                            </strong>

                            <span>
                                Müşteri görüşmeleri ve operasyonlar
                                tek panelde toplanıyor.
                            </span>

                        </div>

                    </div>

                </div>


                <div class="wai-activity-grid">

                    <div class="wai-activity-item">
                        <strong>
                            {{ number_format($conversationCount, 0, ',', '.') }}
                        </strong>
                        <span>Müşteri kaydı</span>
                        <small>Görüşme geçmişi bulunan müşteriler.</small>
                    </div>

                    <div class="wai-activity-item">
                        <strong>
                            {{ number_format($unreadCount, 0, ',', '.') }}
                        </strong>
                        <span>Bekleyen mesaj</span>
                        <small>Ekibinizin kontrolünü bekliyor.</small>
                    </div>

                    <div class="wai-activity-item">
                        <strong>
                            {{ number_format($orderCount, 0, ',', '.') }}
                        </strong>
                        <span>Sipariş</span>
                        <small>Sistemde kayıtlı satış işlemleri.</small>
                    </div>

                    <div class="wai-activity-item">
                        <strong>
                            {{ $connectedChannelCount }}
                        </strong>
                        <span>Aktif kanal</span>
                        <small>WAI'ye bağlı iletişim kanalı.</small>
                    </div>

                </div>

            </div>

        </article>


        {{-- WAI NETWORK --}}

        <article class="wai-card">

            <div class="wai-card-head">

                <div>

                    <div class="wai-card-kicker">
                        Kanallar
                    </div>

                    <div class="wai-card-title">
                        WAI Network
                    </div>

                    <div class="wai-card-subtitle">
                        Müşterilerinizin size ulaştığı kanallar.
                    </div>

                </div>

                <a
                    href="{{ $this->getChannelUrl() }}"
                    class="wai-card-link"
                >
                    Yönet →
                </a>

            </div>


            <div class="wai-channel-summary-body">

                {{-- WHATSAPP --}}

                <div class="wai-channel-summary-item">

                    <div class="wai-channel-logo whatsapp">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <path d="M20 11.5a8 8 0 0 1-11.9 7L4 19.8l1.2-3.9A8 8 0 1 1 20 11.5Z"/>
                            <path d="M8.6 8.3c.2-.4.4-.4.7-.4h.4c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.1.4 0 .6.6 1.1 1.4 1.9 2.5 2.4.3.2.5.1.7-.1l.7-.9c.2-.2.4-.3.7-.2l1.7.8"/>
                        </svg>

                    </div>

                    <div class="wai-channel-summary-info">

                        <strong>
                            WhatsApp
                        </strong>

                        <span>
                            Ana müşteri iletişim kanalı
                        </span>

                    </div>

                    <div class="
                        wai-channel-state
                        {{ $whatsAppConnected ? 'active' : '' }}
                    ">
                        {{ $whatsAppConnected
                            ? 'Aktif'
                            : 'Bağlı Değil'
                        }}
                    </div>

                </div>


                {{-- INSTAGRAM --}}

                <div class="wai-channel-summary-item">

                    <div class="wai-channel-logo instagram">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.9"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
                            <circle cx="12" cy="12" r="4"/>
                            <circle cx="17.3" cy="6.7" r="1"/>
                        </svg>

                    </div>

                    <div class="wai-channel-summary-info">

                        <strong>
                            Instagram
                        </strong>

                        <span>
                            Instagram DM
                        </span>

                    </div>

                    <div class="wai-channel-state">
                        Yakında
                    </div>

                </div>


                {{-- MESSENGER --}}

                <div class="wai-channel-summary-item">

                    <div class="wai-channel-logo facebook">

                        <svg
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M12 3C6.9 3 3 6.7 3 11.5c0 2.7 1.2 5 3.3 6.6V21l3-1.6c.9.3 1.8.5 2.7.5 5.1 0 9-3.7 9-8.4S17.1 3 12 3Zm.9 11.4-2.3-2.5-4.5 2.5 5-5.3 2.4 2.5L18 9.1l-5.1 5.3Z"/>
                        </svg>

                    </div>

                    <div class="wai-channel-summary-info">

                        <strong>
                            Messenger
                        </strong>

                        <span>
                            Facebook mesajları
                        </span>

                    </div>

                    <div class="wai-channel-state">
                        Yakında
                    </div>

                </div>


                {{-- WEB CHAT --}}

                <div class="wai-channel-summary-item">

                    <div class="wai-channel-logo web">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                        >
                            <circle cx="12" cy="12" r="8.5"/>
                            <path d="M3.5 12h17"/>
                            <path d="M12 3.5c2.1 2.3 3.2 5.1 3.2 8.5S14.1 18.2 12 20.5"/>
                            <path d="M12 3.5C9.9 5.8 8.8 8.6 8.8 12s1.1 6.2 3.2 8.5"/>
                        </svg>

                    </div>

                    <div class="wai-channel-summary-info">

                        <strong>
                            Web Chat
                        </strong>

                        <span>
                            Web sitesi görüşmeleri
                        </span>

                    </div>

                    <div class="wai-channel-state">
                        Yakında
                    </div>

                </div>

            </div>

        </article>

    </section>


    {{-- QUICK ACTIONS --}}

    <section class="wai-quick-card">

        <div class="wai-quick-title">
            Hızlı İşlemler
        </div>

        <div class="wai-quick-copy">
            WAI'de en çok kullanacağınız alanlara hızlıca ulaşın.
        </div>


        <div class="wai-quick-grid">

            <a
                href="{{ $this->getInboxUrl() }}"
                class="wai-quick-action"
            >
                <div class="wai-quick-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 5h16v12H8l-4 3V5Z"/>
                        <path d="M8 9h8"/>
                        <path d="M8 13h5"/>
                    </svg>
                </div>

                <div>
                    <strong>Gelen Kutusu</strong>
                    <span>Müşterileri görüntüle</span>
                </div>
            </a>


            <a
                href="{{ $this->getBotUrl() }}"
                class="wai-quick-action"
            >
                <div class="wai-quick-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 3 5 7v10l7 4 7-4V7l-7-4Z"/>
                        <circle cx="9" cy="11" r="1"/>
                        <circle cx="15" cy="11" r="1"/>
                        <path d="M9 15h6"/>
                    </svg>
                </div>

                <div>
                    <strong>AI Çalışanı</strong>
                    <span>WAI ayarlarını düzenle</span>
                </div>
            </a>


            <a
                href="{{ $this->getChannelUrl() }}"
                class="wai-quick-action"
            >
                <div class="wai-quick-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 3v6"/>
                        <path d="M12 15v6"/>
                        <path d="M3 12h6"/>
                        <path d="M15 12h6"/>
                    </svg>
                </div>

                <div>
                    <strong>Kanallar</strong>
                    <span>Bağlantıları yönet</span>
                </div>
            </a>


            <a
                href="/admin/test-sohbeti"
                class="wai-quick-action"
            >
                <div class="wai-quick-icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 6h16v11H9l-5 3V6Z"/>
                        <path d="M8 10h8"/>
                        <path d="M8 13h5"/>
                        <path d="m16 15 1.5 1.5L20 14"/>
                    </svg>
                </div>

                <div>
                    <strong>Test Sohbeti</strong>
                    <span>WAI'yi hemen test et</span>
                </div>
            </a>

        </div>

    </section>


    {{-- NOTE --}}

    <section class="wai-dashboard-note">

        <div class="wai-dashboard-note-icon">
            ✦
        </div>

        <div>

            <strong>
                WAI büyüdükçe Genel Bakış da sizinle büyüyecek.
            </strong>

            <p>
                Lead skoru, satış pipeline'ı, yapay zekâ performansı,
                dönüşüm oranı ve gelir analitiği gibi gelişmiş metrikler
                yeni WAI modülleri aktif oldukça burada gösterilecek.
            </p>

        </div>

        <a href="{{ $this->getChannelUrl() }}">
            WAI Network'ü Gör →
        </a>

    </section>

</div>

</x-filament-panels::page>